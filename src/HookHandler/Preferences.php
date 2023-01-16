<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;

class Preferences implements GetPreferencesHook {

	/** @var string */
	public const INVESTIGATE_TOUR_SEEN = 'checkuser-investigate-tour-seen';

	/** @var string */
	public const INVESTIGATE_FORM_TOUR_SEEN = 'checkuser-investigate-form-tour-seen';

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
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::INVESTIGATE_TOUR_SEEN] = [
			'type' => 'api',
		];

		$preferences[self::INVESTIGATE_FORM_TOUR_SEEN] = [
			'type' => 'api',
		];

		if ( $this->permissionManager->userHasRight( $user, 'checkuser-temporary-account' ) ) {
			// TODO: Users with the 'checkuser' right who haven't set a preference
			// should have the option checked by default. See T327061
			$preferences['checkuser-temporary-account-enable'] = [
				'type' => 'toggle',
				'label-message' => 'checkuser-tempaccount-enable-preference',
				'section' => 'personal/checkuser-tempaccount',
			];
		}
	}

}
