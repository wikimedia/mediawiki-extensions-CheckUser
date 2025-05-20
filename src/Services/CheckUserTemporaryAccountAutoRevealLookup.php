<?php

namespace MediaWiki\CheckUser\Services;

use GlobalPreferences\GlobalPreferencesFactory;
use GlobalPreferences\Storage;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Permissions\Authority;
use MediaWiki\Preferences\PreferencesFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class CheckUserTemporaryAccountAutoRevealLookup {

	private PreferencesFactory $preferencesFactory;

	public function __construct( PreferencesFactory $preferencesFactory ) {
		$this->preferencesFactory = $preferencesFactory;
	}

	/**
	 * Check whether auto-reveal mode is available. It is available if GlobalPreferences
	 * is loaded.
	 *
	 * @return bool Auto-reveal mode is available
	 */
	public function isAutoRevealAvailable(): bool {
		return $this->preferencesFactory instanceof GlobalPreferencesFactory;
	}

	/**
	 * Check whether the expiry time for auto-reveal mode is valid. A valid expiry is in the future
	 * and less than 1 day in the future.
	 *
	 * @param int $expiry
	 * @return bool Expiry is valid
	 */
	private function isAutoRevealExpiryValid( int $expiry ): bool {
		$nowInSeconds = ConvertibleTimestamp::time();
		$oneDayInSeconds = 86400;
		return ( $expiry > ConvertibleTimestamp::time() ) &&
			( $expiry <= ( $nowInSeconds + $oneDayInSeconds ) );
	}

	/**
	 * @param Authority $authority
	 * @return bool Auto-reveal mode is on
	 */
	public function isAutoRevealOn( Authority $authority ): bool {
		if ( !$this->isAutoRevealAvailable() ) {
			return false;
		}

		// @phan-suppress-next-line PhanUndeclaredMethod
		$globalPreferences = $this->preferencesFactory->getGlobalPreferencesValues(
			$authority->getUser(),
			// Load from the database, not the cache, since we're using it for access.
			Storage::SKIP_CACHE
		);

		return $globalPreferences &&
			isset( $globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ) &&
			$this->isAutoRevealExpiryValid( $globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] );
	}
}
