<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\WikiMap\WikiMap;

class PageDisplay implements BeforePageDisplayHook, BeforeInitializeHook {
	private Config $config;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private TempUserConfig $tempUserConfig;

	public function __construct(
		Config $config,
		CheckUserPermissionManager $checkUserPermissionManager,
		TempUserConfig $tempUserConfig
	) {
		$this->config = $config;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->tempUserConfig = $tempUserConfig;
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
		if ( $out->getTitle()->isSpecial( 'Block' ) ) {
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
