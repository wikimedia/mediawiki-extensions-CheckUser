<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\User\UserIdentity;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {
	private UserImpactLookup $userImpactLookup;

	/**
	 * @param UserImpactLookup $userImpactLookup
	 */
	public function __construct( UserImpactLookup $userImpactLookup ) {
		$this->userImpactLookup = $userImpactLookup;
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
		$userName = $user->getName();
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

		// Add information retreived from the UserImpact lookup
		$userInfo = array_merge( $userInfo, $this->getDataFromUserImpact( $user ) );

		return $userInfo;
	}
}
