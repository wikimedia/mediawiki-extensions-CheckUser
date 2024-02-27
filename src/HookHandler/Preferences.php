<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserIdentity;

class Preferences implements GetPreferencesHook {

	/** @var string */
	public const INVESTIGATE_TOUR_SEEN = 'checkuser-investigate-tour-seen';

	/** @var string */
	public const INVESTIGATE_FORM_TOUR_SEEN = 'checkuser-investigate-form-tour-seen';

	private PermissionManager $permissionManager;
	private TemporaryAccountLoggerFactory $loggerFactory;

	/**
	 * @param PermissionManager $permissionManager
	 * @param TemporaryAccountLoggerFactory $loggerFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		TemporaryAccountLoggerFactory $loggerFactory
	) {
		$this->permissionManager = $permissionManager;
		$this->loggerFactory = $loggerFactory;
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
				// The following message is generated here:
				// * prefs-checkuser-tempaccount
				'section' => 'personal/checkuser-tempaccount',
			];
		}
	}

	/**
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( UserIdentity $user, array &$modifiedOptions, array $originalOptions ) {
		$wasEnabled = !empty( $originalOptions['checkuser-temporary-account-enable'] );
		$wasDisabled = !$wasEnabled;

		$willEnable = !empty( $modifiedOptions['checkuser-temporary-account-enable'] );
		$willDisable = isset( $modifiedOptions['checkuser-temporary-account-enable'] ) &&
			!$modifiedOptions['checkuser-temporary-account-enable'];

		if (
			( $wasDisabled && $willEnable ) ||
			( $wasEnabled && $willDisable )
		) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnable ) {
				$logger->logAccessEnabled( $user );
			} else {
				$logger->logAccessDisabled( $user );
			}
		}
	}

}
