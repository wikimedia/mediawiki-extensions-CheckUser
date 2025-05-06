<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Permissions\Authority;
use MediaWiki\Preferences\PreferencesFactory;
use Wikimedia\Timestamp\ConvertibleTimestamp;

trait TemporaryAccountAutoRevealTrait {
	/**
	 * Check whether auto-reveal mode is available. It is available if GlobalPreferences
	 * is loaded.
	 *
	 * @return bool Auto-reveal mode is available
	 */
	protected function isAutoRevealAvailable() {
		return $this->getPreferencesFactory() instanceof GlobalPreferencesFactory;
	}

	/**
	 * Check whether the expiry time for auto-reveal mode is valid. A valid expiry is in the future
	 * and less than 1 day in the future.
	 *
	 * @param int $expiry
	 * @return bool Expiry is valid
	 */
	private function isAutoRevealExpiryValid( $expiry ) {
		$nowInSeconds = ConvertibleTimestamp::time();
		$oneDayInSeconds = 86400;
		return ( $expiry > ConvertibleTimestamp::time() ) &&
			( $expiry <= ( $nowInSeconds + $oneDayInSeconds ) );
	}

	/**
	 * @return bool Auto-reveal mode is on
	 */
	protected function isAutoRevealOn() {
		if ( !$this->isAutoRevealAvailable() ) {
			return false;
		}

		// @phan-suppress-next-line PhanUndeclaredMethod
		$globalPreferences = $this->getPreferencesFactory()->getGlobalPreferencesValues(
			$this->getAuthority()->getUser(),
			// Load from the database, not the cache, since we're using it for access.
			true
		);
		return $globalPreferences &&
			isset( $globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] ) &&
			$this->isAutoRevealExpiryValid( $globalPreferences[Preferences::ENABLE_IP_AUTO_REVEAL] );
	}

	/**
	 * If GlobalPreferences is loaded, then the user may be using auto-reveal. In that case,
	 * add whether auto-reveal mode is on or off, to avoid further API calls to determine this.
	 *
	 * @param array &$results The API results
	 */
	protected function addAutoRevealStatusToResults( array &$results ) {
		if ( $this->isAutoRevealAvailable() ) {
			$results['autoReveal'] = $this->isAutoRevealOn();
		}
	}

	abstract protected function getAuthority(): Authority;

	abstract protected function getPreferencesFactory(): PreferencesFactory;
}
