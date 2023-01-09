<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;

class PageDisplay implements BeforePageDisplayHook {
	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		PermissionManager $permissionManager
	) {
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$out->getRequest()->getVal( 'action' ) !== 'history' &&
			$out->getRequest()->getIntOrNull( 'diff' ) === null &&
			!( $out->getTitle() &&
				( $out->getTitle()->isSpecialPage() )
			)
		) {
			return;
		}

		$user = $out->getUser();

		if (
			!$this->permissionManager->userHasRight( $user, 'checkuser-temporary-account' )
			// TODO: Check preference is enabled, after T325451
		) {
			return;
		}

		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'ext.checkUser.styles' );
	}

}
