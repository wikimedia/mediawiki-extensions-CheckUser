<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentity;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {
	private UserImpactLookup $userImpactLookup;
	private ExtensionRegistry $extensionRegistry;

	/**
	 * @param UserImpactLookup $userImpactLookup
	 */
	public function __construct(
		UserImpactLookup $userImpactLookup,
		ExtensionRegistry $extensionRegistry
	) {
		$this->userImpactLookup = $userImpactLookup;
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * This function is a light shim for UserImpactLookup->getUserImpact.
	 *
	 * @param UserIdentity $user
	 * @return array Array of data points related to the user pulled from the UserImpact
	 * 				 or an empty array if no user impact data can be found
	 */
	private function getDataFromUserImpact( UserIdentity $user ) {
		$userData = [];
		$userImpact = $this->userImpactLookup->getUserImpact( $user );

		// Function is not guaranteed to return a UserImpact
		if ( !$userImpact ) {
			return $userData;
		}

		// TODO: Additional data points from the user impact can be added here as necessary
		$userData[ 'totalEditCount' ] = $userImpact->getTotalEditsCount();

		return $userData;
	}

	/**
	 * @param UserIdentity $user
	 * @return array array containing aggregated user information
	 */
	public function getUserInfo( UserIdentity $user ) {
		$userInfo = [];

		// Add information retrieved from the UserImpact lookup
		$userInfo = array_merge( $userInfo, $this->getDataFromUserImpact( $user ) );

		if ( $userInfo && $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ? $centralAuthUser->getGlobalEditCount() : 0;
		}
		return $userInfo;
	}
}
