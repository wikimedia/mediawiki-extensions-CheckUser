<?php

namespace MediaWiki\CheckUser\HookHandler;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\User\UserIdentity;

class GlobalPreferencesHandler implements GlobalPreferencesSetGlobalPreferencesHook {
	private TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		TemporaryAccountLoggerFactory $loggerFactory
	) {
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		$wasEnabled = (bool)( $oldPreferences['checkuser-temporary-account-enable'] ?? false );
		$wasDisabled = !$wasEnabled;

		$willEnable = (bool)( $newPreferences['checkuser-temporary-account-enable'] ?? false );
		$willDisable = !$willEnable;

		if (
			( $wasDisabled && $willEnable ) ||
			( $wasEnabled && $willDisable )
		) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnable ) {
				$logger->logGlobalAccessEnabled( $user );
			} else {
				$logger->logGlobalAccessDisabled( $user );
			}
		}
	}

}
