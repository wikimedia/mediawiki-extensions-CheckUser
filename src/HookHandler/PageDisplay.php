<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Hook\BeforePageDisplayHook;
use Mediawiki\Permissions\PermissionManager;
use MediaWiki\User\UserOptionsLookup;

class PageDisplay implements BeforePageDisplayHook {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserOptionsLookup */
	protected $userOptionsLookup;

	/**
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$out->getRequest()->getVal( 'action' ) !== 'history' &&
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

		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'ext.checkUser.styles' );
	}

}
