<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use GlobalPreferences\Hook\GlobalPreferencesSetGlobalPreferencesHook;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GlobalPreferencesHandler implements GlobalPreferencesSetGlobalPreferencesHook {
	public function __construct(
		private readonly TemporaryAccountLoggerFactory $loggerFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onGlobalPreferencesSetGlobalPreferences(
		UserIdentity $user,
		array $oldPreferences,
		array $newPreferences
	): void {
		// IP reveal access
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

		// IP auto-reveal mode. Preference values arrive as strings from the API, so cast to int.
		$timeNow = ConvertibleTimestamp::time();
		$oldExpiry = (int)( $oldPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ?? 0 );
		$newExpiry = (int)( $newPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ?? 0 );

		$needToLog = $oldExpiry !== $newExpiry;
		$willEnableAutoReveal = $newExpiry > $timeNow;
		$willDisableAutoReveal = !$willEnableAutoReveal;

		if ( $needToLog ) {
			$logger = $this->loggerFactory->getLogger();
			if ( $willEnableAutoReveal ) {
				$logger->logAutoRevealAccessEnabled( $user, $newExpiry );
			} else {
				$logger->logAutoRevealAccessDisabled( $user );
			}
		}
	}

}
