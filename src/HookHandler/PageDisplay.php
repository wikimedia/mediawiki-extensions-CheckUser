<?php

namespace MediaWiki\CheckUser\HookHandler;

use Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;

class PageDisplay implements BeforePageDisplayHook {
	/** @var Config */
	private $config;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	protected $userOptionsLookup;

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
			!( $out->getTitle() &&
				( $out->getTitle()->isSpecialPage() )
			)
		) {
			return;
		}

		$user = $out->getUser();

		if (
			!$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account' )
			|| !$this->userOptionsLookup->getOption(
				$user,
				'checkuser-temporary-account-enable'
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
	}

}
