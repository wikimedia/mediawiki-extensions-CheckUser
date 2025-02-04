<?php
namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\Options\UserOptionsLookup;

/**
 * Perform CheckUser-related permission checks.
 */
class CheckUserPermissionManager {
	private UserOptionsLookup $userOptionsLookup;

	public function __construct( UserOptionsLookup $userOptionsLookup ) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Check whether the given Authority is allowed to view IP addresses for temporary accounts.
	 * @param Authority $authority The user attempting to view IP addresses for temporary accounts.
	 * @return CheckUserPermissionStatus
	 */
	public function canAccessTemporaryAccountIPAddresses( Authority $authority ): CheckUserPermissionStatus {
		// If the user isn't authorized to view temporary account IP data without having to accept the
		// agreement, ensure they have relevant rights and have accepted the agreement.
		if ( !$authority->isAllowed( 'checkuser-temporary-account-no-preference' ) ) {
			if ( !$authority->isAllowed( 'checkuser-temporary-account' ) ) {
				return CheckUserPermissionStatus::newPermissionError( 'checkuser-temporary-account' );
			}

			if ( !$this->userOptionsLookup->getOption( $authority->getUser(), 'checkuser-temporary-account-enable' ) ) {
				return CheckUserPermissionStatus::newFatal(
					'checkuser-tempaccount-reveal-ip-permission-error-description'
				);
			}
		}

		$block = $authority->getBlock();
		if ( $block !== null && $block->isSitewide() ) {
			return CheckUserPermissionStatus::newBlockedError( $block );
		}

		return CheckUserPermissionStatus::newGood();
	}
}
