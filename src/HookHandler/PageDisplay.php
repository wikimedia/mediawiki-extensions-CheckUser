<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\WikiMap\WikiMap;

class PageDisplay implements BeforePageDisplayHook, BeforeInitializeHook {
	private Config $config;
	private PermissionManager $permissionManager;
	protected UserOptionsLookup $userOptionsLookup;

	/**
	 * @param Config $config
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		Config $config,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->config = $config;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$action = $out->getRequest()->getVal( 'action' );
		if (
			$action !== 'history' &&
			$action !== 'info' &&
			$out->getRequest()->getRawVal( 'diff' ) === null &&
			$out->getRequest()->getRawVal( 'oldid' ) === null &&
			!( $out->getTitle() &&
				( $out->getTitle()->isSpecialPage() )
			)
		) {
			return;
		}

		$user = $out->getUser();

		if (
			!$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account-no-preference' ) &&
			(
				!$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account' )
				|| !$this->userOptionsLookup->getOption(
					$user,
					'checkuser-temporary-account-enable'
				)
			)
		) {
			return;
		}

		// If the user is blocked
		if ( $user->getBlock() ) {
			return;
		}

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
			$url = WikiMap::getForeignURL(
				$globalContributionsCentralWikiId,
				'Special:GlobalContributions',
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
