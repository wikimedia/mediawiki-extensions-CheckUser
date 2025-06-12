<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {
	private ?UserImpactLookup $userImpactLookup;
	private ExtensionRegistry $extensionRegistry;
	private UserOptionsLookup $userOptionsLookup;
	private UserRegistrationLookup $userRegistrationLookup;
	private UserGroupManager $userGroupManager;
	private CheckUserCentralIndexLookup $checkUserCentralIndexLookup;
	private IConnectionProvider $dbProvider;
	private StatsFactory $statsFactory;

	/**
	 * @param UserImpactLookup|null $userImpactLookup
	 * @param ExtensionRegistry $extensionRegistry
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserRegistrationLookup $userRegistrationLookup
	 * @param UserGroupManager $userGroupManager
	 * @param CheckUserCentralIndexLookup $checkUserCentralIndexLookup
	 * @param IConnectionProvider $dbProvider
	 * @param StatsFactory $statsFactory
	 */
	public function __construct(
		?UserImpactLookup $userImpactLookup,
		ExtensionRegistry $extensionRegistry,
		UserOptionsLookup $userOptionsLookup,
		UserRegistrationLookup $userRegistrationLookup,
		UserGroupManager $userGroupManager,
		CheckUserCentralIndexLookup $checkUserCentralIndexLookup,
		IConnectionProvider $dbProvider,
		StatsFactory $statsFactory
	) {
		$this->userImpactLookup = $userImpactLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userRegistrationLookup = $userRegistrationLookup;
		$this->userGroupManager = $userGroupManager;
		$this->checkUserCentralIndexLookup = $checkUserCentralIndexLookup;
		$this->dbProvider = $dbProvider;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * This function is a light shim for UserImpactLookup->getUserImpact.
	 *
	 * @param UserIdentity $user
	 * @return array Array of data points related to the user pulled from the UserImpact
	 * 				 or an empty array if no user impact data can be found
	 */
	private function getDataFromUserImpact( UserIdentity $user ): array {
		$userData = [];
		$userImpact = $this->userImpactLookup->getUserImpact( $user );

		// Function is not guaranteed to return a UserImpact
		if ( !$userImpact ) {
			return $userData;
		}

		$userData['name'] = $user->getName();
		$userData['gender'] = $this->userOptionsLookup->getOption( $user, 'gender' );
		$userData['localRegistration'] = $this->userRegistrationLookup->getRegistration( $user );
		$userData['firstRegistration'] = $this->userRegistrationLookup->getFirstRegistration( $user );
		$userData['groups'] = $this->userGroupManager->getUserGroups( $user );
		$userData['totalEditCount'] = $userImpact->getTotalEditsCount();
		$userData['thanksGiven'] = $userImpact->getGivenThanksCount();
		$userData['thanksReceived'] = $userImpact->getReceivedThanksCount();
		$userData['editCountByDay'] = $userImpact->getEditCountByDay();
		$userData['revertedEditCount'] = $userImpact->getRevertedEditCount();
		$userData['newArticlesCount'] = $userImpact->getTotalArticlesCreatedCount();

		return $userData;
	}

	/**
	 * @param Authority $authority
	 * @param UserIdentity $user
	 * @return array array containing aggregated user information
	 */
	public function getUserInfo( Authority $authority, UserIdentity $user ): array {
		$start = microtime( true );

		// GrowthExperiments is unavailable, don't attempt to return any data (T394070)
		// In the future, we may try to return data that's available without having
		// the GrowthExperiments impact store available.
		if ( !$this->userImpactLookup ) {
			return [];
		}
		$userInfo = $this->getDataFromUserImpact( $user );
		if ( !$userInfo ) {
			// There should always be user impact data. If there isn't, there's a problem
			// relating to fetching data for the account and we should just return the
			// empty array, instead of adding more data points below.
			return $userInfo;
		}

		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ? $centralAuthUser->getGlobalEditCount() : 0;
		}
		$userInfo['activeWikis'] = $this->checkUserCentralIndexLookup->getActiveWikisForUser( $user );

		$dbr = $this->dbProvider->getReplicaDatabase();
		if ( $authority->isAllowed( 'checkuser-log' ) ) {
			$rows = $dbr->newSelectQueryBuilder()
				->select( 'cul_timestamp' )
				->from( 'cu_log' )
				->where( [ 'cul_target_id' => $user->getId() ] )
				->caller( __METHOD__ )
				->orderBy( 'cul_timestamp', SelectQueryBuilder::SORT_DESC )
				->fetchFieldValues();
			$userInfo['checkUserChecks'] = count( $rows );
			if ( $rows ) {
				$userInfo['checkUserLastCheck'] = $rows[0];
			}
		}

		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			try {
				$centralAuthUser = CentralAuthUser::getInstance( $user );
				$blocks = $centralAuthUser->getBlocks();
			} catch ( LocalUserNotFoundException $e ) {
				$blocks = [];
			}
			$userInfo['activeLocalBlocksAllWikis'] = array_sum( array_map( 'count', $blocks ) );
		}

		$userInfo['pastBlocksOnLocalWiki'] = $dbr->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => $user->getName(),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();

		$this->statsFactory->withComponent( 'CheckUser' )
			->getTiming( 'userinfocardservice_get_user_info' )
			->observe( ( microtime( true ) - $start ) * 1000 );

		return $userInfo;
	}
}
