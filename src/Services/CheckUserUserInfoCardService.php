<?php

namespace MediaWiki\CheckUser\Services;

use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\Cache\GenderCache;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\LocalUserNotFoundException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use Psr\Log\LoggerInterface;
use Wikimedia\Message\ListType;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;

/**
 * A service for methods that interact with user info card components
 */
class CheckUserUserInfoCardService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserUserInfoCardCentralWikiId',
	];

	private const PAGER_ITERATION_LIMIT = 20;

	public function __construct(
		private readonly ?UserImpactLookup $userImpactLookup,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserGroupManager $userGroupManager,
		private readonly ?GlobalContributionsPagerFactory $globalContributionsPagerFactory,
		private readonly IConnectionProvider $dbProvider,
		private readonly StatsFactory $statsFactory,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly UserFactory $userFactory,
		private readonly InterwikiLookup $interwikiLookup,
		private readonly UserEditTracker $userEditTracker,
		private readonly MessageLocalizer $messageLocalizer,
		private readonly TitleFactory $titleFactory,
		private readonly GenderCache $genderCache,
		private readonly ServiceOptions $options,
		private readonly LoggerInterface $logger
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
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
		$userInfo['gender'] = $this->genderCache->getGenderOf( $user );
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
			$userInfo['globalEditCount'] = $centralAuthUser->isAttached() ?
				CentralAuthServices::getEditCounter()->getCountFromWikis( $centralAuthUser ) :
				0;
			$globalGroups = $centralAuthUser->getGlobalGroups();
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

		$userInfo['activeWikis'] = [];
		if ( $this->globalContributionsPagerFactory instanceof GlobalContributionsPagerFactory ) {
			$activeWikiIds = [];
			$activeWikisStart = microtime( true );
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
			$offset = '';
			$iterations = 0;
			do {
				// Iterate over results until we have gone through every wiki's rows.
				$globalContributionsPager->setOffset( $offset );
				// Set the highest possible limit here, to reduce the number of times we need to
				// iterate over the pager results.
				$globalContributionsPager->setLimit( 5000 );
				$globalContributionsPager->doQuery();
				foreach ( $globalContributionsPager->getResult() as $result ) {
					$activeWikiIds[$result->sourcewiki] = true;
				}
				$queryOptions = $globalContributionsPager->getPagingQueries();
				$offset = $queryOptions['next']['offset'] ?? '';
				$iterations++;
			} while ( $offset !== '' && $iterations < self::PAGER_ITERATION_LIMIT );
			if ( $iterations === self::PAGER_ITERATION_LIMIT ) {
				// Diagnostic logging, in case we need to increase the iterations.
				$this->logger->info(
					'UserInfoCard returned incomplete activeWikis for {user} due to reaching pager iteration limits', [
						'user' => $user->getName(),
					]
				);
			}
			$activeWikiIds = array_keys( $activeWikiIds );
			sort( $activeWikiIds );
			foreach ( $activeWikiIds as $wikiId ) {
				$interWiki = $this->interwikiLookup->fetch(
					rtrim( $wikiId, 'wiki' )
				);
				if ( !$interWiki ) {
					continue;
				}
				$userInfo['activeWikis'][$wikiId] = $interWiki->getUrl(
					'Special:Contributions/' . $this->getUserTitleKey( $user )
				);
			}
			$this->statsFactory->withComponent( 'CheckUser' )
				->getTiming( 'userinfocardservice_active_wikis' )
				->setLabel( 'reached_paging_limit', $iterations === self::PAGER_ITERATION_LIMIT ? '1' : '0' )
				->observe( ( microtime( true ) - $activeWikisStart ) * 1000 );
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
			} catch ( LocalUserNotFoundException ) {
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
				'log_title' => $this->getUserTitleKey( $user ),
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( $authority->isAllowed( 'suppressionlog' ) ) {
			$blockLogEntriesCount += $dbr->newSelectQueryBuilder()
				->select( 'log_id' )
				->from( 'logging' )
				->where( [
					'log_type' => 'suppress',
					'log_action' => 'block',
					'log_namespace' => NS_USER,
					'log_title' => $this->getUserTitleKey( $user ),
				] )
				->caller( __METHOD__ )
				->fetchRowCount();
		}
		// Subtract the count of active local blocks (local blocks are on the 0 index, set by CentralAuthUser) to get
		// the past blocks count.
		// In case the user doesn't have suppressionlog rights, ensure that the value displayed here is at least 0.
		$userInfo['pastBlocksOnLocalWiki'] = max( 0, $blockLogEntriesCount - count( $blocks[0] ?? [] ) );

		$authorityPermissionStatus =
			$this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses( $authority );
		$userPermissionStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$this->userFactory->newFromUserIdentity( $user )
		);

		$userInfo['canAccessTemporaryAccountIpAddresses'] = $authorityPermissionStatus->isGood() &&
			$userPermissionStatus->isGood();

		// Generate a URL to the Special:CentralAuth page for the user being viewed, preferring to have the
		// URL be on a central wiki if one is defined.
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralWikiId = $this->options->get( 'CheckUserUserInfoCardCentralWikiId' );
			if ( $centralWikiId ) {
				$interWiki = $this->interwikiLookup->fetch( rtrim( $centralWikiId, 'wiki' ) );
				if ( $interWiki ) {
					$userInfo['specialCentralAuthUrl'] = $interWiki->getURL(
						'Special:CentralAuth/' . $this->getUserTitleKey( $user )
					);
				}
			}

			$userInfo['specialCentralAuthUrl'] ??= SpecialPage::getTitleFor(
				'CentralAuth', $this->getUserTitleKey( $user )
			)->getLinkURL();
		}

		$this->statsFactory->withComponent( 'CheckUser' )
			->getTiming( 'userinfocardservice_get_user_info' )
			->setLabel( 'with_user_impact', $hasUserImpactData ? '1' : '0' )
			->observe( ( microtime( true ) - $start ) * 1000 );

		return $userInfo;
	}

	/**
	 * Get the username in a form that can be used in a DB query
	 *
	 * This performs the same transformation on the username as done in
	 * User::getTitleKey().
	 *
	 * @param UserIdentity $userIdentity
	 * @return string
	 */
	private function getUserTitleKey( UserIdentity $userIdentity ): string {
		return str_replace( ' ', '_', $userIdentity->getName() );
	}

}
