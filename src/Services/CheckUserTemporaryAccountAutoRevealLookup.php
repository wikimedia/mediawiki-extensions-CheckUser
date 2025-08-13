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
	private CheckUserPermissionManager $checkUserPermissionManager;

	public function __construct(
		PreferencesFactory $preferencesFactory,
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		$this->preferencesFactory = $preferencesFactory;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
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
	 * @param mixed $expiry Expiry should be in a UNIX timestamp format
	 * @return bool Expiry is valid
	 */
	public function isAutoRevealExpiryValid( mixed $expiry ): bool {
		if ( !is_numeric( $expiry ) ) {
			return false;
		}

		$expiry = intval( $expiry );
		$nowInSeconds = ConvertibleTimestamp::time();
		$oneDayInSeconds = 86400;
		return ( $expiry > ConvertibleTimestamp::time() ) &&
			( $expiry <= ( $nowInSeconds + $oneDayInSeconds ) );
	}

	/**
	 * Returns true if:
	 * * IP auto-reveal is enabled on the wiki,
	 * * The given $authority has the rights to use the feature, and
	 * * The given $authority has turned on the feature
	 *
	 * @param Authority $authority
	 * @return bool Auto-reveal mode is on
	 */
	public function isAutoRevealOn( Authority $authority ): bool {
		if (
			!$this->isAutoRevealAvailable() ||
			!$this->checkUserPermissionManager->canAutoRevealIPAddresses( $authority )->isGood()
		) {
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
