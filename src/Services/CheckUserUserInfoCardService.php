<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\TitleFactory;
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
	private GlobalContributionsPagerFactory $globalContributionsPagerFactory;
	private IConnectionProvider $dbProvider;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private UserFactory $userFactory;
	private StatsFactory $statsFactory;
	private InterwikiLookup $interwikiLookup;
	private UserEditTracker $userEditTracker;
	private MessageLocalizer $messageLocalizer;
	private TitleFactory $titleFactory;

	/**
	 * @param UserImpactLookup|null $userImpactLookup
	 * @param ExtensionRegistry $extensionRegistry
	 * @param UserRegistrationLookup $userRegistrationLookup
	 * @param UserGroupManager $userGroupManager
	 * @param GlobalContributionsPagerFactory $globalContributionsPagerFactory
	 * @param IConnectionProvider $dbProvider
	 * @param StatsFactory $statsFactory
	 * @param CheckUserPermissionManager $checkUserPermissionManager
	 * @param UserFactory $userFactory
	 * @param InterwikiLookup $interwikiLookup
	 * @param UserEditTracker $userEditTracker
	 * @param MessageLocalizer $messageLocalizer
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		?UserImpactLookup $userImpactLookup,
		ExtensionRegistry $extensionRegistry,
		UserRegistrationLookup $userRegistrationLookup,
		UserGroupManager $userGroupManager,
		GlobalContributionsPagerFactory $globalContributionsPagerFactory,
		IConnectionProvider $dbProvider,
		StatsFactory $statsFactory,
		CheckUserPermissionManager $checkUserPermissionManager,
		UserFactory $userFactory,
		InterwikiLookup $interwikiLookup,
		UserEditTracker $userEditTracker,
		MessageLocalizer $messageLocalizer,
		TitleFactory $titleFactory
	) {
		$this->userImpactLookup = $userImpactLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->userRegistrationLookup = $userRegistrationLookup;
		$this->userGroupManager = $userGroupManager;
		$this->globalContributionsPagerFactory = $globalContributionsPagerFactory;
		$this->dbProvider = $dbProvider;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->userFactory = $userFactory;
		$this->statsFactory = $statsFactory;
		$this->interwikiLookup = $interwikiLookup;
		$this->userEditTracker = $userEditTracker;
		$this->messageLocalizer = $messageLocalizer;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * Check if the user's local or global page is known
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function userPageIsKnown( UserIdentity $user ): bool {
		$userPageTitle = $this->titleFactory->makeTitle( NS_USER, $user->getName() );
		return $userPageTitle->isKnown();
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
		$hasUserImpactData = count( $userInfo );

		$userInfo['name'] = $user->getName();
		$userInfo['localRegistration'] = $this->userRegistrationLookup->getRegistration( $user );
		$userInfo['firstRegistration'] = $this->userRegistrationLookup->getFirstRegistration( $user );
		$userInfo['userPageIsKnown'] = $this->userPageIsKnown( $user );

		$groups = $this->userGroupManager->getUserGroups( $user );
		sort( $groups );
		$groupMessages = [];
		foreach ( $groups as $group ) {
			if ( $this->messageLocalizer->msg( "group-$group" )->exists() ) {
				$groupMessages[] = $this->messageLocalizer->msg( "group-$group" )->text();
			}
		}
		$userInfo['groups'] = '';
		if ( $groupMessages ) {
			$userInfo['groups'] = $this->messageLocalizer->msg( 'checkuser-userinfocard-groups' )
				->useDatabase( true )
				->params( Message::listParam( $groupMessages, ListType::COMMA ) )
				->text();
		}

		if ( !isset( $userInfo['totalEditCount'] ) ) {
			$userInfo['totalEditCount'] = $this->userEditTracker->getUserEditCount( $user );
		}

		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralAuthUser = CentralAuthUser::getInstance( $user );
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ? $centralAuthUser->getGlobalEditCount() : 0;
			$globalGroups = $centralAuthUser->getActiveGlobalGroups();
			sort( $globalGroups );
			$globalGroupMessages = [];
			foreach ( $globalGroups as $group ) {
				if ( $this->messageLocalizer->msg( "group-$group" )->exists() ) {
					$globalGroupMessages[] = $this->messageLocalizer->msg( "group-$group" )
						->text();
				}
			}
			$userInfo['globalGroups'] = '';
			if ( $globalGroupMessages ) {
				$userInfo['globalGroups'] = $this->messageLocalizer->msg( 'checkuser-userinfocard-global-groups' )
					->useDatabase( true )
					->params( Message::listParam( $globalGroupMessages, ListType::COMMA ) )
					->text();
			}
		}

		// Fetch the list of active wikis for a user by looking at permissions-gated
		// global contributions. Note that this means that a user can perform publicly
		// logged actions on other wikis but this will not appear in the "active wikis"
		// list. When T397710 is done, we would be able to also fetch active wikis
		// by looking at log entries as well. For now, making this edit based is fine.
		$globalContributionsPager = $this->globalContributionsPagerFactory->createPager(
			RequestContext::getMain(),
			[],
			$user
		);
		$globalContributionsPager->doQuery();
		$activeWikiIds = [];
		foreach ( $globalContributionsPager->getResult() as $result ) {
			$activeWikiIds[$result->sourcewiki] = true;
		}
		$userInfo['activeWikis'] = [];
		$activeWikiIds = array_keys( $activeWikiIds );
		sort( $activeWikiIds );
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

		$blocks = [];
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			try {
				$centralAuthUser = CentralAuthUser::getInstance( $user );
				$blocks = $centralAuthUser->getBlocks();
			} catch ( LocalUserNotFoundException $e ) {
				LoggerFactory::getInstance( 'CheckUser' )->info(
					'Unable to get CentralAuthUser for user {user}', [
						'user' => $user->getName(),
					]
				);
			}
		}
		$userInfo['activeLocalBlocksAllWikis'] = array_sum( array_map( 'count', $blocks ) );

		$blockLogEntriesCount = $dbr->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				// Perform the same transformation on the username as is done in User::getTitleKey()
				'log_title' => str_replace( ' ', '_', $user->getName() ),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		// Subtract the count of active local blocks (local blocks are on the 0 index, set by CentralAuthUser) to get
		// the past blocks count.
		$userInfo['pastBlocksOnLocalWiki'] = $blockLogEntriesCount - count( $blocks[0] ?? [] );

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
