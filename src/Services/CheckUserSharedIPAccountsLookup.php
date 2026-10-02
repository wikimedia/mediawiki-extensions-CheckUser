<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Finds the accounts that acted from an IP address that a given account also used.
 *
 * The service does no permission check, no hidden user filtering and no access logging.
 * Doing these is the caller's responsibility, as necessary. Only present the results
 * to users with the necessary CheckUser rights.
 */
class CheckUserSharedIPAccountsLookup implements CheckUserQueryInterface {

	/** The maximum number of IP addresses of the target that the service looks at. */
	private const int MAX_TARGET_IPS = 20;

	/** The maximum number of rows to read for each IP address and each CheckUser table. */
	private const int MAX_ROWS_PER_IP = 100;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly CheckUserLookupUtils $checkUserLookupUtils,
		private readonly ActorNormalization $actorNormalization,
		private readonly UserIdentityLookup $userIdentityLookup,
	) {
	}

	/**
	 * Get the accounts that acted from an IP address that the given account also used.
	 *
	 * Anonymous actors and the target are not in the result. Temporary accounts are.
	 *
	 * The service reads only a limited number of IP addresses of the target, and a limited
	 * number of rows for each IP address. When it reaches one of these limits, 'complete' is
	 * false, and more accounts can share an IP address with the target.
	 *
	 * @param UserIdentity $target
	 * @return array{users: UserIdentity[], complete: bool} Local users, in no specific order
	 */
	public function getUsersOnSharedIPs( UserIdentity $target ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$targetActorId = $this->actorNormalization->findActorId( $target, $dbr );
		if ( !$targetActorId ) {
			return [ 'users' => [], 'complete' => true ];
		}

		[ $ipTargets, $complete ] = $this->getActorIPs( $dbr, $targetActorId );

		$actorIds = [];
		foreach ( $ipTargets as $ipTarget ) {
			foreach ( self::RESULT_TABLES as $table ) {
				[ $ipActorIds, $ipComplete ] = $this->getOtherActorIdsOnIP( $dbr, $ipTarget, $targetActorId, $table );
				$complete = $complete && $ipComplete;
				foreach ( $ipActorIds as $actorId ) {
					$actorIds[$actorId] = true;
				}
			}
		}
		if ( !$actorIds ) {
			return [ 'users' => [], 'complete' => $complete ];
		}

		$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder( $dbr )
			->registered()
			->where( [ 'actor_id' => array_keys( $actorIds ) ] )
			->caller( __METHOD__ )
			->fetchUserIdentities();
		$userIdentities = [ ...$userIdentities ];

		return [ 'users' => $userIdentities, 'complete' => $complete ];
	}

	/**
	 * Get the most recent IP addresses that the given actor used, as targets for
	 * CheckUserLookupUtils::getIPTargetExpr().
	 *
	 * IPv6 addresses become their /64 range, as a common practice in other places.
	 *
	 * @param IReadableDatabase $dbr
	 * @param int $targetActorId
	 * @return array{0: string[], 1: bool} IP addresses and ranges, and false if the actor used
	 *   more IP addresses than the service looks at
	 */
	private function getActorIPs( IReadableDatabase $dbr, int $targetActorId ): array {
		$complete = true;
		$latestTimestampByIPHex = [];
		foreach ( self::RESULT_TABLES as $table ) {
			$prefix = self::RESULT_TABLE_TO_PREFIX[$table];
			$ipHexField = $prefix . 'ip_hex';
			$timestampField = $prefix . 'timestamp';

			$rows = $dbr->newSelectQueryBuilder()
				->select( [ 'ip_hex' => $ipHexField, 'timestamp' => "MAX($timestampField)" ] )
				->from( $table )
				->useIndex( $this->checkUserLookupUtils->getIndexName( null, $table ) )
				->where( [ $prefix . 'actor' => $targetActorId ] )
				->andWhere( $dbr->expr( $ipHexField, '!=', null ) )
				->groupBy( $ipHexField )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( self::MAX_TARGET_IPS + 1 )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $rows->numRows() > self::MAX_TARGET_IPS ) {
				$complete = false;
			}

			foreach ( $rows as $row ) {
				$ipHex = (string)$row->ip_hex;
				$timestamp = (string)$row->timestamp;
				if (
					!isset( $latestTimestampByIPHex[$ipHex] ) ||
					$latestTimestampByIPHex[$ipHex] < $timestamp
				) {
					$latestTimestampByIPHex[$ipHex] = $timestamp;
				}
			}
		}

		arsort( $latestTimestampByIPHex );

		$ipTargets = [];
		foreach ( array_keys( $latestTimestampByIPHex ) as $ipHex ) {
			$ip = IPUtils::formatHex( $ipHex );
			if ( IPUtils::isIPv6( $ip ) ) {
				[ $rangeStart ] = IPUtils::parseRange( $ip . '/64' );
				$ip = IPUtils::formatHex( $rangeStart ) . '/64';
			}
			if ( !isset( $ipTargets[$ip] ) && count( $ipTargets ) >= self::MAX_TARGET_IPS ) {
				$complete = false;
				break;
			}
			$ipTargets[$ip] = true;
		}

		return [ array_keys( $ipTargets ), $complete ];
	}

	/**
	 * Get the actors that acted from the given IP address or range, in one CheckUser table.
	 *
	 * The query reads at most self::MAX_ROWS_PER_IP + 1 rows. And excludes rows for the current
	 * target actor.
	 *
	 * For a range, the rows come from the highest IP addresses in the range first, not from the
	 * most recent events. When the limit cuts such a sample, the result is marked as incomplete.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $ipTarget An IP address or range
	 * @param int $targetActorId The actor which is the original one in the current search
	 * @param string $table One of self::RESULT_TABLES
	 * @return array{0: int[], 1: bool} Actor IDs, and false if more rows match than the limit
	 */
	private function getOtherActorIdsOnIP(
		IReadableDatabase $dbr,
		string $ipTarget,
		int $targetActorId,
		string $table
	): array {
		$ipExpr = $this->checkUserLookupUtils->getIPTargetExpr( $ipTarget, false, $table );
		if ( $ipExpr === null ) {
			return [ [], true ];
		}

		$prefix = self::RESULT_TABLE_TO_PREFIX[$table];
		$actorIds = $dbr->newSelectQueryBuilder()
			->select( [ 'actor' => $prefix . 'actor' ] )
			->from( $table )
			->useIndex( $this->checkUserLookupUtils->getIndexName( false, $table ) )
			->where( $ipExpr )
			->andWhere( $dbr->expr( $prefix . 'actor', '!=', $targetActorId ) )
			->orderBy( [ $prefix . 'ip_hex', $prefix . 'timestamp' ], SelectQueryBuilder::SORT_DESC )
			->limit( self::MAX_ROWS_PER_IP + 1 )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$complete = count( $actorIds ) <= self::MAX_ROWS_PER_IP;
		$actorIds = array_map( intval( ... ), $actorIds );
		$actorIds = array_filter( $actorIds, static fn ( $id ) => $id > 0 );
		return [ array_unique( $actorIds ), $complete ];
	}
}
