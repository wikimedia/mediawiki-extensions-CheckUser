<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\WikiMap\WikiMap;

class PageDisplay implements BeforePageDisplayHook, BeforeInitializeHook {
	private Config $config;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private UserOptionsLookup $userOptionsLookup;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;

	public function __construct(
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager,
		TempUserConfig $tempUserConfig,
		UserOptionsLookup $userOptionsLookup,
		ExtensionRegistry $extensionRegistry
	) {
		$this->config = $config;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->tempUserConfig = $tempUserConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// There is no need for the JS modules for temporary account IP reveal
		// if the wiki does not have temporary accounts enabled or known.
		if ( !$this->tempUserConfig->isKnown() ) {
			return;
		}

		// Exclude loading the JS module on pages which do not use it.
		$action = $out->getRequest()->getVal( 'action' );
		if (
			$action !== 'history' &&
			$action !== 'info' &&
			$out->getRequest()->getRawVal( 'diff' ) === null &&
			$out->getRequest()->getRawVal( 'oldid' ) === null &&
			!( $out->getTitle() && $out->getTitle()->isSpecialPage() )
		) {
			return;
		}

		// Exclude the JS code for temporary account IP reveal if the user does not have permission to use it.
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$out->getAuthority()
		);
		if ( !$permStatus->isGood() ) {
			return;
		}

		// All checks passed, so add the JS code needed for temporary account IP reveal.

		// Config needed for a js-added message on Special:Block
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Block' ) ) {
			$out->addJSConfigVars( [
				'wgCUDMaxAge' => $this->config->get( 'CUDMaxAge' )
			] );
		}

		// Show the temporary accounts onboarding dialog if the user has never seen the dialog before, and
		// the user is viewing any of the history page, Special:Watchlist, or Special:RecentChanges.
		if (
			$this->config->get( 'CheckUserEnableTempAccountsOnboardingDialog' ) &&
			(
				$action === 'history' ||
				$title->isSpecial( 'Watchlist' ) ||
				$title->isSpecial( 'Recentchanges' )
			)
		) {
			$userHasSeenDialog = $this->userOptionsLookup->getBoolOption(
				$out->getUser(), Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN
			);
			if ( !$userHasSeenDialog ) {
				$out->addHtml( '<div id="ext-checkuser-tempaccountsonboarding-app"></div>' );
				$out->addModules( 'ext.checkUser.tempAccountOnboarding' );
				$out->addModuleStyles( 'ext.checkUser.styles' );
				$out->addModuleStyles( 'ext.checkUser.images' );
				$out->addJsConfigVars( [
					'wgCheckUserIPInfoExtensionLoaded' => $this->extensionRegistry->isLoaded( 'IPInfo' ),
				] );
			}
		}

		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'ext.checkUser.styles' );
		$out->addJSConfigVars( [
			'wgCheckUserTemporaryAccountMaxAge' => $this->config->get( 'CheckUserTemporaryAccountMaxAge' ),
			'wgCheckUserSpecialPagesWithoutIPRevealButtons' =>
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ),
		] );
	}

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWikiEntryPoint ) {
		// Is there a central wiki defined for the Special:GlobalContributions feature?
		// If so, redirect the user there, preserving the query parameters.
		$globalContributionsCentralWikiId = $this->config->get( 'CheckUserGlobalContributionsCentralWikiId' );
		if ( $globalContributionsCentralWikiId &&
			$output->getTitle()->isSpecial( 'GlobalContributions' ) &&
			$globalContributionsCentralWikiId !== WikiMap::getCurrentWikiId() ) {
			// Note: Use the canonical (English) name for the namespace and page
			// since non-English aliases would likely not be recognized by the central wiki.
			$page = "Special:GlobalContributions";
			$slashPos = strpos( $title->getText(), '/' );
			if ( $slashPos !== false ) {
				$page .= substr(
					$title->getText(),
					$slashPos
				);
			}

			$url = WikiMap::getForeignURL(
				$globalContributionsCentralWikiId,
				$page,
			);
			$queryValues = $output->getRequest()->getQueryValuesOnly();
			// Don't duplicate the title, as we have this already from ::getForeignURL above
			if ( isset( $queryValues['title'] ) ) {
				unset( $queryValues['title'] );
			}
			$url = wfAppendQuery( $url, $queryValues );
			$output->redirect( $url );
		}
	}
}
