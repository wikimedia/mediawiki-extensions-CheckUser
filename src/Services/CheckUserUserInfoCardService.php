<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use Wikimedia\Message\ListType;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {
	private ?UserImpactLookup $userImpactLookup;
	private ExtensionRegistry $extensionRegistry;
	private UserRegistrationLookup $userRegistrationLookup;
	private UserGroupManager $userGroupManager;
	private CheckUserCentralIndexLookup $checkUserCentralIndexLookup;
	private IConnectionProvider $dbProvider;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private UserFactory $userFactory;
	private StatsFactory $statsFactory;
	private InterwikiLookup $interwikiLookup;
	private UserEditTracker $userEditTracker;
	private MessageLocalizer $messageLocalizer;

	/**
	 * @param UserImpactLookup|null $userImpactLookup
	 * @param ExtensionRegistry $extensionRegistry
	 * @param UserRegistrationLookup $userRegistrationLookup
	 * @param UserGroupManager $userGroupManager
	 * @param CheckUserCentralIndexLookup $checkUserCentralIndexLookup
	 * @param IConnectionProvider $dbProvider
	 * @param StatsFactory $statsFactory
	 * @param CheckUserPermissionManager $checkUserPermissionManager
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		?UserImpactLookup $userImpactLookup,
		ExtensionRegistry $extensionRegistry,
		UserRegistrationLookup $userRegistrationLookup,
		UserGroupManager $userGroupManager,
		CheckUserCentralIndexLookup $checkUserCentralIndexLookup,
		IConnectionProvider $dbProvider,
		StatsFactory $statsFactory,
		CheckUserPermissionManager $checkUserPermissionManager,
		UserFactory $userFactory,
		InterwikiLookup $interwikiLookup,
		UserEditTracker $userEditTracker,
		MessageLocalizer $messageLocalizer
	) {
		$this->userImpactLookup = $userImpactLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->userRegistrationLookup = $userRegistrationLookup;
		$this->userGroupManager = $userGroupManager;
		$this->checkUserCentralIndexLookup = $checkUserCentralIndexLookup;
		$this->dbProvider = $dbProvider;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->userFactory = $userFactory;
		$this->statsFactory = $statsFactory;
		$this->interwikiLookup = $interwikiLookup;
		$this->userEditTracker = $userEditTracker;
		$this->messageLocalizer = $messageLocalizer;
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
		// GrowthExperiments UserImpactLookup service is unavailable, don't attempt to
		// retrieve data from it (T394070)
		if ( !$this->userImpactLookup ) {
			return [];
		}

		$userImpact = $this->userImpactLookup->getUserImpact( $user );
		// Function is not guaranteed to return a UserImpact
		if ( !$userImpact ) {
			return $userData;
		}
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
		if ( !$user->isRegistered() ) {
			return [];
		}
		$start = microtime( true );
		$userInfo = $this->getDataFromUserImpact( $user );
		$hasUserImpactData = count( $userInfo ) > 0;

		$userInfo['name'] = $user->getName();
		$userInfo['localRegistration'] = $this->userRegistrationLookup->getRegistration( $user );
		$userInfo['firstRegistration'] = $this->userRegistrationLookup->getFirstRegistration( $user );

		$groups = $this->userGroupManager->getUserGroups( $user );
		$groupMessages = [];
		foreach ( $groups as $group ) {
			if ( $this->messageLocalizer->msg( "group-$group" )->exists() ) {
				$groupMessages[] = $this->messageLocalizer->msg( "group-$group" )->text();
			}
		}
		$userInfo['groups'] = '';
		if ( $groupMessages ) {
			$userInfo['groups'] = $this->messageLocalizer->msg( 'checkuser-userinfocard-groups' )
				->params( Message::listParam( $groupMessages, ListType::COMMA ) )
				->text();
		}

		if ( !isset( $userInfo['totalEditCount'] ) ) {
			$userInfo['totalEditCount'] = $this->userEditTracker->getUserEditCount( $user );
		}

		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ? $centralAuthUser->getGlobalEditCount() : 0;
		}
		$activeWikiIds = $this->checkUserCentralIndexLookup->getActiveWikisForUser( $user );
		$userInfo['activeWikis'] = [];
		foreach ( $activeWikiIds as $wikiId ) {
			$interWiki = $this->interwikiLookup->fetch(
				rtrim( $wikiId, 'wiki' )
			);
			if ( !$interWiki ) {
				continue;
			}
			$userInfo['activeWikis'][ $wikiId ] = $interWiki->getUrl(
				'Special:Contributions/' . str_replace( ' ', '_', $user->getName() )
			);
		}

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

		$authorityPermissionStatus =
			$this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses( $authority );
		$userPermissionStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$this->userFactory->newFromUserIdentity( $user )
		);
		if ( $authorityPermissionStatus->isGood() && $userPermissionStatus->isGood() ) {
			$userInfo['canAccessTemporaryAccountIPAddresses'] = true;
		}

		$this->statsFactory->withComponent( 'CheckUser' )
			->getTiming( 'userinfocardservice_get_user_info' )
			->setLabel( 'with_user_impact', $hasUserImpactData ? '1' : '0' )
			->observe( ( microtime( true ) - $start ) * 1000 );

		return $userInfo;
	}
}
