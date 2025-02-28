<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserOptionsLookup;

class PageDisplay implements BeforePageDisplayHook {
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

		$this->addTemporaryAccountsOnboardingDialog( $out );

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

		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'ext.checkUser.styles' );
		$out->addJSConfigVars( [
			'wgCheckUserTemporaryAccountMaxAge' => $this->config->get( 'CheckUserTemporaryAccountMaxAge' ),
			'wgCheckUserSpecialPagesWithoutIPRevealButtons' =>
				$this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ),
		] );
	}

	/**
	 * Show the temporary accounts onboarding dialog if the user has never seen the dialog before,
	 * has permissions to reveal IPs (ignoring the preference check) and the user is
	 * viewing any of the history page, Special:Watchlist, or Special:RecentChanges.
	 *
	 * @return void
	 */
	private function addTemporaryAccountsOnboardingDialog( OutputPage $out ) {
		if ( !$this->config->get( 'CheckUserEnableTempAccountsOnboardingDialog' ) ) {
			return;
		}

		$action = $out->getRequest()->getVal( 'action' );
		$title = $out->getTitle();
		if (
			$action !== 'history' &&
			!$title->isSpecial( 'Watchlist' ) &&
			!$title->isSpecial( 'Recentchanges' )
		) {
			return;
		}

		if ( !$out->getAuthority()->isAllowedAny(
			'checkuser-temporary-account-no-preference', 'checkuser-temporary-account'
		) ) {
			return;
		}

		$block = $out->getAuthority()->getBlock();
		if ( $block !== null && $block->isSitewide() ) {
			return;
		}

		$userHasSeenDialog = $this->userOptionsLookup->getBoolOption(
			$out->getUser(), Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN
		);
		if ( !$userHasSeenDialog ) {
			$out->addHtml( '<div id="ext-checkuser-tempaccountsonboarding-app"></div>' );
			$out->addModules( 'ext.checkUser.tempAccountOnboarding' );
			$out->addModuleStyles( 'ext.checkUser.styles' );
			$out->addModuleStyles( 'ext.checkUser.images' );

			// Allow the dialog to hide the IPInfo step and/or IPInfo preference depending on the
			// rights of the user and if IPInfo is installed.
			$ipInfoLoaded = $this->extensionRegistry->isLoaded( 'IPInfo' );
			$out->addJsConfigVars( [
				'wgCheckUserIPInfoExtensionLoaded' => $ipInfoLoaded,
				'wgCheckUserUserHasIPInfoRight' => $ipInfoLoaded &&
					$out->getAuthority()->isAllowed( 'ipinfo' ),
			] );
		}
	}
}
