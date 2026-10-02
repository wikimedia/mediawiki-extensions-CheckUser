<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Finds the accounts that share a browser version with a given account.
 *
 * The browser version comes from the fullVersionList Client Hints value, which
 * cu_useragent_clienthints holds as one row for each brand, such as
 * "Google Chrome 131.0.6778.86".
 *
 * The service does no permission check, no hidden user filtering and no access logging.
 * Doing these is the caller's responsibility, as necessary. Only present the results
 * to users with the necessary CheckUser rights.
 */
class CheckUserSharedBrowserVersionAccountsLookup implements CheckUserQueryInterface {

	/**
	 * A fullVersionList value that more accounts than this use is too common to be a useful
	 * signal, so the service ignores it.
	 */
	private const int MAX_CLUSTER_SIZE = 50;

	/** The maximum number of events of the target to read from each CheckUser table. */
	private const int MAX_TARGET_EVENTS_PER_TABLE = 100;

	/** The maximum number of fullVersionList values of the target that the service looks at. */
	private const int MAX_BROWSER_VALUES = 20;

	/** A fullVersionList value with more map rows than this is rejected without a lookup. */
	private const int MAX_MAP_ROWS_PER_VALUE = 1000;

	/** The maximum number of map rows to resolve to accounts in one call. */
	private const int MAX_RESOLVED_MAP_ROWS = 3000;

	/** The number of rows to read from cu_useragent_clienthints_map at a time. */
	private const int MAP_BATCH_SIZE = 250;

	/**
	 * Matches the GREASE brands that Chromium adds to the brand list, such as
	 * "Not_A Brand 24.0.0.0" or "Not/A)Brand 99.0.0.0". These do not identify a browser.
	 */
	private const string GREASE_BRAND_REGEX = '/^[^a-z0-9]*not[^a-z0-9]+a[^a-z0-9]+brand[^a-z0-9]/i';

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly CheckUserLookupUtils $checkUserLookupUtils,
		private readonly ActorNormalization $actorNormalization,
		private readonly UserIdentityLookup $userIdentityLookup,
	) {
	}

	/**
	 * Get the accounts that share a fullVersionList Client Hints value with the given account.
	 *
	 * Each such value makes a cluster. The service ignores a cluster with more than
	 * self::MAX_CLUSTER_SIZE accounts, because a browser version that many accounts share is not
	 * a useful signal. An ignored cluster does not make the result incomplete.
	 *
	 * The service reads a limited number of events and values of the target, and resolves a
	 * limited number of map rows. When it reaches one of these limits, 'complete' is false, and
	 * more accounts can share a browser version with the target.
	 *
	 * Anonymous actors and the target are not in the result. Temporary accounts are.
	 *
	 * @param UserIdentity $target
	 * @return array{users: UserIdentity[], complete: bool} Local users, in no specific order
	 */
	public function getUsersWithSharedBrowserVersion( UserIdentity $target ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$targetActorId = $this->actorNormalization->findActorId( $target, $dbr );
		if ( !$targetActorId ) {
			return [ 'users' => [], 'complete' => true ];
		}

		[ $uachIds, $complete ] = $this->getTargetBrowserValueIds( $dbr, $targetActorId );

		$users = [];
		$resolvedRows = 0;
		foreach ( $uachIds as $uachId ) {
			$cluster = $this->getClusterForBrowserValue( $dbr, $uachId, $target, $resolvedRows );
			if ( $cluster === null ) {
				$complete = false;
				continue;
			}
			$users += $cluster;
		}

		return [ 'users' => array_values( $users ), 'complete' => $complete ];
	}

	/**
	 * Get the IDs of the fullVersionList values that the given actor used.
	 *
	 * @param IReadableDatabase $dbr
	 * @param int $targetActorId
	 * @return array{0: int[], 1: bool} Row IDs of cu_useragent_clienthints (the most frequent
	 *   value first), and whether the result is complete, i.e. all events and client hints were read
	 */
	private function getTargetBrowserValueIds( IReadableDatabase $dbr, int $targetActorId ): array {
		$complete = true;
		$referenceIdsByType = [];

		// Grab the client hints for the actions by the user
		foreach ( UserAgentClientHintsManager::IDENTIFIER_TO_TABLE_NAME_MAP as $type => $table ) {
			$prefix = self::RESULT_TABLE_TO_PREFIX[$table];
			$referenceIdField = UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$type];

			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'reference_id' => $referenceIdField ] )
				->from( $table )
				->useIndex( $this->checkUserLookupUtils->getIndexName( null, $table ) )
				->where( [ $prefix . 'actor' => $targetActorId ] )
				->orderBy( [ $prefix . 'ip_hex', $prefix . 'timestamp' ] )
				->limit( self::MAX_TARGET_EVENTS_PER_TABLE + 1 )
				->caller( __METHOD__ );

			if ( $referenceIdField === 'cuc_this_oldid' || $referenceIdField === 'cule_log_id' ) {
				// These are 0 when the row has no related revision or log entry. Such a row has no Client Hints data.
				$queryBuilder->andWhere( $dbr->expr( $referenceIdField, '!=', 0 ) );
			}

			$referenceIdValues = $queryBuilder->fetchFieldValues();
			if ( count( $referenceIdValues ) > self::MAX_TARGET_EVENTS_PER_TABLE ) {
				$complete = false;
			}

			$referenceIds = [];
			foreach ( $referenceIdValues as $referenceId ) {
				$referenceIds[(int)$referenceId] = true;
			}
			if ( $referenceIds ) {
				$referenceIdsByType[$type] = array_keys( $referenceIds );
			}
		}

		// Count the frequency of the client hints that we found
		$useCountByUachId = [];
		foreach ( $referenceIdsByType as $type => $referenceIds ) {
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'uachm_uach_id', 'use_count' => 'COUNT(*)' ] )
				->from( 'cu_useragent_clienthints_map' )
				->where( [
					'uachm_reference_type' => $type,
					'uachm_reference_id' => $referenceIds,
				] )
				->groupBy( 'uachm_uach_id' )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $rows as $row ) {
				$uachId = (int)$row->uachm_uach_id;
				$useCountByUachId[$uachId] = ( $useCountByUachId[$uachId] ?? 0 ) + (int)$row->use_count;
			}
		}

		if ( !$useCountByUachId ) {
			return [ [], $complete ];
		}

		// Filter the client hints to only be of the 'fullVersionList' type, and drop fake browser names
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'uach_id', 'uach_value' ] )
			->from( 'cu_useragent_clienthints' )
			->where( [
				'uach_id' => array_keys( $useCountByUachId ),
				'uach_name' => 'fullVersionList',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rankedIds = [];
		foreach ( $rows as $row ) {
			if ( preg_match( self::GREASE_BRAND_REGEX, $row->uach_value ) ) {
				continue;
			}
			$rankedIds[(int)$row->uach_id] = $useCountByUachId[(int)$row->uach_id];
		}
		// The most used value first
		ksort( $rankedIds );
		arsort( $rankedIds, SORT_NUMERIC );

		if ( count( $rankedIds ) > self::MAX_BROWSER_VALUES ) {
			$complete = false;
		}

		return [ array_slice( array_keys( $rankedIds ), 0, self::MAX_BROWSER_VALUES ), $complete ];
	}

	/**
	 * Get the accounts that used the given fullVersionList value.
	 *
	 * @param IReadableDatabase $dbr
	 * @param int $uachId Row ID of cu_useragent_clienthints
	 * @param UserIdentity $target The account to keep out of the result
	 * @param int &$resolvedRows The number of map rows that the call resolved so far
	 * @return array<int,UserIdentity>|null Local users, keyed by user ID. An empty array if the
	 *   cluster is too large. Null if the cluster does not fit in the remaining row budget.
	 */
	private function getClusterForBrowserValue(
		IReadableDatabase $dbr,
		int $uachId,
		UserIdentity $target,
		int &$resolvedRows
	): ?array {
		// Count the map rows first to reject the browsers that are too common
		$mapRowCount = $dbr->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [ 'uachm_uach_id' => $uachId ] )
			->limit( self::MAX_MAP_ROWS_PER_VALUE + 1 )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( $mapRowCount > self::MAX_MAP_ROWS_PER_VALUE ) {
			return [];
		}
		// Read a cluster completely or not at all. A part of a cluster cannot show if the
		// cluster is too large.
		if ( $resolvedRows + $mapRowCount > self::MAX_RESOLVED_MAP_ROWS ) {
			return null;
		}

		$users = [];
		$cursorType = null;
		$cursorId = null;
		while ( true ) {
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'uachm_reference_type', 'uachm_reference_id' ] )
				->from( 'cu_useragent_clienthints_map' )
				->where( [ 'uachm_uach_id' => $uachId ] )
				->orderBy( [ 'uachm_reference_type', 'uachm_reference_id' ] )
				->limit( self::MAP_BATCH_SIZE )
				->caller( __METHOD__ );

			if ( $cursorType !== null ) {
				$queryBuilder->andWhere( $dbr->buildComparison( '>', [
					'uachm_reference_type' => $cursorType,
					'uachm_reference_id' => $cursorId,
				] ) );
			}

			$rows = $queryBuilder->fetchResultSet();
			$rowCount = $rows->numRows();
			if ( !$rowCount ) {
				break;
			}

			$referenceIdsByType = [];
			foreach ( $rows as $row ) {
				$cursorType = (int)$row->uachm_reference_type;
				$cursorId = (int)$row->uachm_reference_id;
				$referenceIdsByType[$cursorType][] = $cursorId;
			}
			$resolvedRows += $rowCount;

			$actorIds = $this->getActorIdsForReferenceIds( $dbr, $referenceIdsByType );
			$users += $this->getUsersForActorIds( $dbr, $actorIds, $target );

			if ( count( $users ) > self::MAX_CLUSTER_SIZE ) {
				return [];
			}

			if ( $rowCount < self::MAP_BATCH_SIZE ) {
				break;
			}
		}

		return $users;
	}

	/**
	 * Find the actor of each Client Hints reference ID.
	 *
	 * The reference ID is a revision ID, a log entry ID or a cu_private_event row ID
	 *
	 * @param IReadableDatabase $dbr
	 * @param array<int,int[]> $referenceIdsByType Reference IDs, keyed by reference type
	 * @return int[] Actor IDs
	 */
	private function getActorIdsForReferenceIds( IReadableDatabase $dbr, array $referenceIdsByType ): array {
		$actorIds = [];

		$revisionIds = $referenceIdsByType[UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES] ?? [];
		if ( $revisionIds ) {
			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'rev_id', 'rev_actor' ] )
				->from( 'revision' )
				->where( [ 'rev_id' => $revisionIds ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			$missingRevisionIds = array_fill_keys( $revisionIds, true );
			foreach ( $rows as $row ) {
				$actorIds[(int)$row->rev_actor] = true;
				unset( $missingRevisionIds[(int)$row->rev_id] );
			}

			if ( $missingRevisionIds ) {
				// The revision of a deleted page is in the archive table. These revisions matter
				// here, because a bad actor often edits a page that's later deleted
				$archivedActorIds = $dbr->newSelectQueryBuilder()
					->select( 'ar_actor' )
					->from( 'archive' )
					->where( [ 'ar_rev_id' => array_keys( $missingRevisionIds ) ] )
					->caller( __METHOD__ )
					->fetchFieldValues();
				foreach ( $archivedActorIds as $actorId ) {
					$actorIds[(int)$actorId] = true;
				}
			}
		}

		$logIds = $referenceIdsByType[UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT] ?? [];
		if ( $logIds ) {
			$logActorIds = $dbr->newSelectQueryBuilder()
				->select( 'log_actor' )
				->from( 'logging' )
				->where( [ 'log_id' => $logIds ] )
				->caller( __METHOD__ )
				->fetchFieldValues();
			foreach ( $logActorIds as $actorId ) {
				$actorIds[(int)$actorId] = true;
			}
		}

		$privateEventIds = $referenceIdsByType[UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT] ?? [];
		if ( $privateEventIds ) {
			$privateActorIds = $dbr->newSelectQueryBuilder()
				->select( 'cupe_actor' )
				->from( self::PRIVATE_LOG_EVENT_TABLE )
				->where( [ 'cupe_id' => $privateEventIds ] )
				// cupe_actor is NULL for the actions of logged out users.
				->andWhere( $dbr->expr( 'cupe_actor', '!=', null ) )
				->caller( __METHOD__ )
				->fetchFieldValues();
			foreach ( $privateActorIds as $actorId ) {
				$actorIds[(int)$actorId] = true;
			}
		}

		return array_keys( $actorIds );
	}

	/**
	 * Find the account of each actor, and remove the actors that are not accounts.
	 *
	 * @param IReadableDatabase $dbr
	 * @param int[] $actorIds
	 * @param UserIdentity $target The account to keep out of the result
	 * @return array<int,UserIdentity> Local users, keyed by user ID
	 */
	private function getUsersForActorIds(
		IReadableDatabase $dbr,
		array $actorIds,
		UserIdentity $target
	): array {
		if ( !$actorIds ) {
			return [];
		}

		$users = [];
		$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder( $dbr )
			->registered()
			->where( [ 'actor_id' => $actorIds ] )
			->caller( __METHOD__ )
			->fetchUserIdentities();
		foreach ( $userIdentities as $userIdentity ) {
			if ( $userIdentity->getId() !== $target->getId() ) {
				$users[$userIdentity->getId()] = $userIdentity;
			}
		}

		return $users;
	}
}
