<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use InvalidArgumentException;
use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;

class CheckUserGlobalContributionsLookup implements CheckUserQueryInterface {

	use ContributionsRangeTrait;

	private IConnectionProvider $dbProvider;
	private ExtensionRegistry $extensionRegistry;
	private CentralIdLookup $centralIdLookup;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private Config $config;
	private RevisionStore $revisionStore;
	private CheckUserApiRequestAggregator $apiRequestAggregator;
	private WANObjectCache $wanCache;
	private StatsFactory $statsFactory;

	/**
	 * Prometheus counter metric name for API lookup errors.
	 */
	public const API_LOOKUP_ERROR_METRIC_NAME = 'checkuser_globalcontributions_api_lookup_error';

	/**
	 * Prometheus counter metric name for tracking external permissions cache hits.
	 */
	public const EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME = 'checkuser_external_permissions_cache_hit';

	/**
	 * Prometheus counter metric name for tracking external permissions cache misses.
	 */
	public const EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME = 'checkuser_external_permissions_cache_miss';

	public function __construct(
		IConnectionProvider $dbProvider,
		ExtensionRegistry $extensionRegistry,
		CentralIdLookup $centralIdLookup,
		CheckUserLookupUtils $checkUserLookupUtils,
		Config $config,
		RevisionStore $revisionStore,
		CheckUserApiRequestAggregator $apiRequestAggregator,
		WANObjectCache $wanCache,
		StatsFactory $statsFactory
	) {
		$this->dbProvider = $dbProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->centralIdLookup = $centralIdLookup;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->config = $config;
		$this->revisionStore = $revisionStore;
		$this->apiRequestAggregator = $apiRequestAggregator;
		$this->wanCache = $wanCache;
		$this->statsFactory = $statsFactory;
	}

	/**
	 * Ensure CentralAuth is loaded
	 *
	 * @throws LogicException if CentralAuth is not loaded, as it's a dependency
	 */
	public function checkCentralAuthEnabled() {
		if ( !$this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			throw new LogicException(
				"CentralAuth authentication is needed but not available"
			);
		}
	}

	/**
	 * Get an array of wikis, where the target has made contributions that are publicly visible.
	 * In case of the local wiki, also include contributions that may be non-public but can be seen
	 * by the passed authority.
	 *
	 * @param string $target Name of the actor to check
	 * @param Authority $authority
	 * @param string|null $timeCutoff If set, only return wikis where user was active since this timestamp
	 * @return list<string>
	 */
	public function getPubliclyKnownActiveWikis(
		string $target, Authority $authority, ?string $timeCutoff = null
	): array {
		$allActiveWikis = $this->getActiveWikis( $target, $authority, $timeCutoff );

		$resultingWikis = [];
		foreach ( $allActiveWikis as $wiki ) {
			$dbr = $this->dbProvider->getReplicaDatabase( $wiki );

			$dbConditions = [
				'actor_name' => $target
			];

			if ( $timeCutoff !== null ) {
				$dbConditions[] = $dbr->expr( 'rev_timestamp', '>=', $dbr->timestamp( $timeCutoff ) );
			}

			// List non-public revisions only from the local wiki, where we can easily check for
			// permissions. For remote wikis, we assume that we're not allowed to see non-public revisions.
			if (
				!WikiMap::isCurrentWikiDbDomain( $wiki ) ||
				!$authority->isAllowed( 'deletedhistory' )
			) {
				$dbConditions[] = $dbr->bitAnd(
						'rev_deleted', RevisionRecord::DELETED_USER
					) . ' = 0';
			} elseif (
				!$authority->isAllowed( 'suppressrevision' ) &&
				!$authority->isAllowed( 'viewsuppressed' )
			) {
				$dbConditions[] = $dbr->bitAnd(
						'rev_deleted', RevisionRecord::SUPPRESSED_USER
					) . ' != ' . RevisionRecord::SUPPRESSED_USER;
			}

			$hasVisibleActions = (bool)$dbr->newSelectQueryBuilder()
				->select( '1' )
				->from( 'revision' )
				->join( 'actor', null, 'rev_actor = actor_id' )
				->where( $dbConditions )
				->limit( 1 )
				->caller( __METHOD__ )
				->fetchField();

			if ( $hasVisibleActions ) {
				$resultingWikis[] = $wiki;
			}
		}

		return $resultingWikis;
	}

	/**
	 * Get an array of active wikis from either cuci_temp_edit or cuci_user.
	 *
	 * NOTE: These tables describe all activity of the user, some of which may not be publicly visible
	 * (e.g. suppressed revisions). If you want to display the list of active wikis, either filter
	 * the return value of this method or use {@link getPubliclyKnownActiveWikis}.
	 *
	 * @param string $target
	 * @param Authority $authority
	 * @param string|null $timeCutoff If set, only return wikis where user was active since this timestamp
	 * @return string[]
	 */
	public function getActiveWikis( string $target, Authority $authority, ?string $timeCutoff = null ) {
		$this->checkCentralAuthEnabled();

		$activeWikis = [];
		$cuciDb = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );

		if ( $this->isValidIPOrQueryableRange( $target, $this->config ) ) {
			$targetIPConditions = $this->checkUserLookupUtils->getIPTargetExprForColumn(
				$target,
				'cite_ip_hex'
			);
			if ( $targetIPConditions === null ) {
				// Invalid IPs are treated as usernames so we should only ever reach
				// this condition if the IP range is out of limits
				throw new LogicException(
					"Attempted IP range lookup with a range outside of the limit: $target\n
					Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
				);
			}
			$conditions = [ $targetIPConditions ];
			if ( $timeCutoff !== null ) {
				$conditions[] = $cuciDb->expr( 'cite_timestamp', '>=', $cuciDb->timestamp( $timeCutoff ) );
			}
			$activeWikisResult = $cuciDb->newSelectQueryBuilder()
				// T397318: 'cite_timestamp' is selected to satisfy Postgres requirement where all ORDER BY
				// fields must be present in SELECT list.
				->select( [ 'ciwm_wiki', 'timestamp' => 'MAX(cite_timestamp)' ] )
				->from( 'cuci_temp_edit' )
				->where( $conditions )
				->join( 'cuci_wiki_map', null, 'cite_ciwm_id = ciwm_id' )
				->groupBy( 'ciwm_wiki' )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchResultSet();
			$activeWikis = array_map( static fn ( $row ) => $row->ciwm_wiki, iterator_to_array( $activeWikisResult ) );
		} else {
			$centralId = $this->centralIdLookup->centralIdFromName( $target, $authority );
			if ( !$centralId ) {
				throw new InvalidArgumentException( "No central id found for $target" );
			}
			$conditions = [ 'ciu_central_id' => $centralId ];
			if ( $timeCutoff !== null ) {
				$conditions[] = $cuciDb->expr( 'ciu_timestamp', '>=', $cuciDb->timestamp( $timeCutoff ) );
			}
			$activeWikisResult = $cuciDb->newSelectQueryBuilder()
				// T397318: 'ciu_timestamp' is selected to satisfy Postgres requirement where all ORDER BY
				// fields must be present in SELECT list.
				->select( [ 'ciwm_wiki', 'timestamp' => 'MAX(ciu_timestamp)' ] )
				->from( 'cuci_user' )
				->where( $conditions )
				->join( 'cuci_wiki_map', null, 'ciu_ciwm_id = ciwm_id' )
				->groupBy( 'ciwm_wiki' )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchResultSet();
			$activeWikis = array_map( static fn ( $row ) => $row->ciwm_wiki, iterator_to_array( $activeWikisResult ) );
		}

		return $activeWikis;
	}

	/**
	 * Get the global contributions count of the target by looping through the
	 * active wikis and querying for revisions. This only deals with IPs,
	 * as registered accounts are handled by CentralAuthUser->getGlobalEditCount
	 *
	 * @param string $target
	 * @param string[] $activeWikis
	 * @return int global contribution count
	 */
	public function getAnonymousUserGlobalContributionCount( string $target, array $activeWikis ) {
		$ipConditions = $this->checkUserLookupUtils->getIPTargetExpr(
			$target,
			false,
			self::CHANGES_TABLE
		);
		if ( $ipConditions === null ) {
			// Invalid IPs are treated as usernames so we should only ever reach
			// this condition if the IP range is out of limits
			throw new LogicException(
				"Attempted IP range lookup with a range outside of the limit: $target\n
				Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
			);
		}

		$revisionCount = 0;
		foreach ( $activeWikis as $wikiId ) {
			$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );
			// Get results; no need to check access permissions. See T386186#10595606
			$countResult = $dbr->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( self::CHANGES_TABLE )
				->where( $ipConditions )
				->andWhere( $dbr->expr( 'cuc_this_oldid', '!=', 0 ) )
				->caller( __METHOD__ )
				->fetchField();

			$revisionCount += $countResult;
		}

		return $revisionCount;
	}

	/**
	 * Return a count of contributions across wikis. IPs are limited to 90 days of data
	 * but registered accounts have a total count through CentralAuth.
	 *
	 * @param string $target
	 * @param Authority $authority
	 * @return int global contribution count
	 */
	public function getGlobalContributionsCount( string $target, Authority $authority ) {
		$this->checkCentralAuthEnabled();

		if ( $this->isValidIPOrQueryableRange( $target, $this->config ) ) {
			$activeWikis = $this->getActiveWikis( $target, $authority );
			return $this->getAnonymousUserGlobalContributionCount( $target, $activeWikis );
		} else {
			$centralUser = CentralAuthUser::getInstanceByName( $target );
			return $centralUser->getGlobalEditCount();
		}
	}

	/**
	 * @param int $centralId
	 * @param string[] $wikiIds
	 * @param User $user
	 * @return array array containing 1. an array of permissions results 2. a bool indicating if any errors occurred
	 */
	public function getAndUpdateExternalWikiPermissions(
		int $centralId,
		array $wikiIds,
		User $user,
		WebRequest $request
	) {
		$externalApiLookupError = false;
		$numWikisWithUnknownPermissions = 0;

		$permissions = $this->wanCache->getMultiWithUnionSetCallback(
			$this->wanCache->makeMultiKeys(
				$wikiIds,
				function ( $wikiId ) use ( $centralId ) {
					return $this->wanCache->makeGlobalKey(
						'globalcontributions-ext-permissions',
						$centralId,
						$wikiId
					);
				}
			),
			$this->wanCache::TTL_MONTH,
			function (
				array $wikisWithUnknownPermissions,
				array &$ttls
			) use ( $user, $request, &$externalApiLookupError, &$numWikisWithUnknownPermissions ) {
				$numWikisWithUnknownPermissions = count( $wikisWithUnknownPermissions );

				$foundPermissions = array_fill_keys( $wikisWithUnknownPermissions, false );
				$lookedUpPermissions = $this->apiRequestAggregator->execute(
					$user,
					[
						'action' => 'query',
						'prop' => 'info',
						'intestactions' => 'checkuser-temporary-account|checkuser-temporary-account-no-preference' .
							'|deletedtext|deletedhistory|suppressrevision|viewsuppressed',
						// We need to check against a title, but it doesn't actually matter if the title exists
						'titles' => 'Test Title',
						// Using `full` level checks blocks as well
						'intestactionsdetail' => 'full',
						'format' => 'json',
					],
					$wikisWithUnknownPermissions,
					$request,
					CheckUserApiRequestAggregator::AUTHENTICATE_CENTRAL_AUTH
				);

				foreach ( $wikisWithUnknownPermissions as $wikiId ) {
					if ( !isset( $lookedUpPermissions[$wikiId]['query']['pages'][0]['actions'] ) ) {
						// The API lookup failed, so assume the user does not have IP reveal rights.
						$externalApiLookupError = true;
						$ttls[$wikiId] = $this->wanCache::TTL_UNCACHEABLE;
						$this->statsFactory->getCounter( self::API_LOOKUP_ERROR_METRIC_NAME )
							->increment();
						continue;
					}
					$foundPermissions[$wikiId] = $lookedUpPermissions[$wikiId]['query']['pages'][0]['actions'];
					$ttls[$wikiId] = $this->wanCache::TTL_MONTH;
					$this->statsFactory->getCounter( self::EXTERNAL_PERMISSIONS_CACHE_MISS_METRIC_NAME )
						->increment();
				}

				return $foundPermissions;
			},
			[
				'checkKeys' => [ $this->wanCache->makeGlobalKey(
					'globalcontributions-ext-permissions',
					$centralId
				) ],
			]
		);

		$numWikisWithKnownPermissions = count( $wikiIds ) - $numWikisWithUnknownPermissions;
		if ( $numWikisWithKnownPermissions > 0 ) {
			$this->statsFactory->getCounter( self::EXTERNAL_PERMISSIONS_CACHE_HIT_METRIC_NAME )
				->incrementBy( $numWikisWithKnownPermissions );
		}

		return [
			'externalApiLookupError' => $externalApiLookupError,
			// Since the cache preserves order, it is safe to combine the arrays
			'permissions' => array_combine( $wikiIds, $permissions )
		];
	}

	/**
	 * Get the byte length of a list of revisions on the given wiki.
	 *
	 * @param string $wikiId
	 * @param int[] $revisionIds
	 * @return int[] Map of revision ID to byte length
	 */
	public function getRevisionSizes( string $wikiId, array $revisionIds ): array {
		if ( count( $revisionIds ) === 0 ) {
			return [];
		}

		$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );

		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'rev_id', 'rev_len' ] )
			->from( 'revision' )
			->where( [ 'rev_id' => $revisionIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$revisionSizes = array_fill_keys( $revisionIds, 0 );

		foreach ( $res as $row ) {
			$revisionSizes[$row->rev_id] = (int)$row->rev_len;
		}

		return $revisionSizes;
	}
}
