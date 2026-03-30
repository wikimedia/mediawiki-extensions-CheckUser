<?php

namespace MediaWiki\Extension\CheckUser\Services;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use StatusValue;
use stdClass;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Given an IP, return every known temporary account that has edited from it.
 *
 * Note that in WMF production, using this service outside of Extension:CheckUser
 * requires consultation with Trust & Safety Product.
 */
class CheckUserTemporaryAccountsByIPLookup implements CheckUserQueryInterface {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserMaximumRowCount',
	];
	private const int MAX_BATCH_SIZE = 250;

	public function __construct(
		private readonly ServiceOptions $serviceOptions,
		private readonly IConnectionProvider $connectionProvider,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly TempUserConfig $tempUserConfig,
		private readonly UserFactory $userFactory,
		private readonly PermissionManager $permissionManager,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly CheckUserLookupUtils $checkUserLookupUtils,
		private readonly ReadOnlyMode $readOnlyMode,
	) {
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * @param string $ip The IP address to use in the lookup
	 * @param Authority $authority The authority making the request
	 * @param bool $shouldLog Should a log entry be created to show that this data was accessed? By default,
	 *   create a log entry. Classes that extend AbstractTemporaryAccountHandler don't need to set this to true,
	 *   because AbstractTemporaryAccountHandler creates a log entry.
	 * @param int|null $limit The maximum number of rows to fetch.
	 * @return StatusValue<string[]> A good status will have a list of account names or empty list if none were found;
	 *  a bad status will have the relevant permission error encountered
	 * @throws InvalidArgumentException If the $ip could not be parsed as a valid IP or range
	 */
	public function get( string $ip, Authority $authority, bool $shouldLog = true, ?int $limit = null ): StatusValue {
		// TODO: Use a trait for permissions, to avoid duplication with
		// AbstractTemporaryAccountHandler::checkPermissions
		$status = $this->checkPermissions( $authority );

		if ( !$status->isGood() ) {
			return $status;
		}

		if ( $shouldLog ) {
			if ( $this->readOnlyMode->isReadOnly() ) {
				return StatusValue::newFatal( 'readonlytext', $this->readOnlyMode->getReason() );
			}

			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$authority->getUser(),
					$ip,
					TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP
				)
			);
		}

		$allAccounts = $this->getTempAccountsFromIPAddress( $ip, $limit );

		// If the user can see hidden accounts, return the result
		if ( $authority->isAllowed( 'hideuser' ) ) {
			return StatusValue::newGood( $allAccounts );
		}

		// Don't return hidden accounts to authorities who cannot view them
		$accounts = [];
		foreach ( $allAccounts as $account ) {
			if ( !$this->userFactory->newFromName( $account )->isHidden() ) {
				$accounts[] = $account;
			}
		}
		return StatusValue::newGood( $accounts );
	}

	/**
	 * Given an IP address or range, return all temporary accounts associated with
	 * it. This function should be called from a wrapper so that `checkPermissions()`
	 * can be run if necessary.
	 *
	 * @param string $ip The IP address or range to use in the lookup
	 * @param int|null $limit The maximum number of rows to fetch.
	 * @return string[]
	 * @throws InvalidArgumentException if the provided IP is invalid
	 */
	private function getTempAccountsFromIPAddress( string $ip, ?int $limit = null ): array {
		if ( !IPUtils::isIPAddress( $ip ) ) {
			throw new InvalidArgumentException( "Invalid IP $ip passed" );
		}

		$limit = $this->getQueryLimit( $limit );

		$cuChangesAccounts = $this->getTempAccountsFromCuChanges( $ip, $limit );
		$cuLogEventAccounts = $this->getTempAccountsFromCuLogEvent( $ip, $limit );

		return $this->mergeAndSortByLatestTimeStamp( $limit, $cuChangesAccounts, $cuLogEventAccounts );
	}

	/**
	 * Find temporary accounts that have edited from the given IP or IP range,
	 * using the cu_changes table (which logs edits and similar actions).
	 *
	 * @param string $ip
	 * @param int $limit Maximum number of rows to scan
	 * @return array Map of username => most recent timestamp
	 */
	private function getTempAccountsFromCuChanges( string $ip, int $limit ): array {
		$ipConds = $this->checkUserLookupUtils->getIPTargetExpr( $ip, false, self::CHANGES_TABLE );
		if ( $ipConds === null ) {
			throw new InvalidArgumentException( "Unable to acquire subquery for $ip" );
		}

		$actorIdToNameMap = $this->collectTempAccountActorIdsBatched(
			$ipConds,
			'cu_changes',
			$limit
		);
		if ( !$actorIdToNameMap ) {
			return [];
		}

		$actorIdToTimestampMap = $this->getActorsMostRecentActionTimestamps(
			$actorIdToNameMap,
			$ipConds,
			'cu_changes'
		);

		$result = [];
		foreach ( $actorIdToTimestampMap as $actorId => $timestamp ) {
			$result[$actorIdToNameMap[$actorId]] = $timestamp;
		}

		return $result;
	}

	/**
	 * Find temporary accounts that have performed logged actions (e.g. blocks, deletions)
	 * from the given IP or IP range
	 *
	 * @param string $ip
	 * @param int $limit Maximum number of rows to scan
	 * @return array Map of username => most recent timestamp
	 */
	private function getTempAccountsFromCuLogEvent( string $ip, int $limit ): array {
		$ipConds = $this->checkUserLookupUtils->getIPTargetExpr( $ip, false, self::LOG_EVENT_TABLE );
		if ( $ipConds === null ) {
			throw new InvalidArgumentException( "Unable to acquire subquery for $ip" );
		}

		$actorIdToNameMap = $this->collectTempAccountActorIdsBatched(
			$ipConds,
			'cu_log_event',
			$limit
		);
		if ( !$actorIdToNameMap ) {
			return [];
		}

		$actorIdToTimestampMap = $this->getActorsMostRecentActionTimestamps(
			$actorIdToNameMap,
			$ipConds,
			'cu_log_event'
		);

		$result = [];
		foreach ( $actorIdToTimestampMap as $actorId => $timestamp ) {
			$result[$actorIdToNameMap[$actorId]] = $timestamp;
		}

		return $result;
	}

	/**
	 * Walk a CheckUser table (cu_changes or cu_log_event) in small batches,
	 * collecting the actor IDs of temporary accounts that match the given IP range.
	 *
	 * @param IExpression $ipConds WHERE conditions for the IP range
	 * @param string $tableName 'cu_changes' or 'cu_log_event'
	 * @param int $limit Maximum number of rows to scan
	 * @return array<int,string> Map of actor ID (int) => actor name (string)
	 */
	private function collectTempAccountActorIdsBatched(
		IExpression $ipConds,
		string $tableName,
		int $limit
	): array {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$tableName];
		$actorField = $prefix . 'actor';
		$ipHexField = $prefix . 'ip_hex';
		$timestampField = $prefix . 'timestamp';

		$actorIds = [];
		$remainingRowsToScan = $limit;
		$cursorIpHex = null;
		$cursorTimestamp = null;

		while ( $remainingRowsToScan > 0 ) {
			$requestedRowCount = min( self::MAX_BATCH_SIZE, $remainingRowsToScan );
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->fields( [
					'actor_id' => $actorField,
					'actor_name' => 'actor_name',
					'ip_hex' => $ipHexField,
					'timestamp' => $timestampField,
				] )
				->table( $tableName )
				->join( 'actor', null, "actor_id=$actorField" )
				->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
				->where( $ipConds )
				// Exclude already-found actors so we don't waste scan budget on their rows at
				// other ip_hex values further down the index.
				->andWhere( $actorIds ? $dbr->expr( $actorField, '!=', array_keys( $actorIds ) ) : [] )
				// DESC matches a reverse index scan on (ip_hex, timestamp) — no filesort, newest rows first.
				->orderBy( [ $ipHexField, $timestampField ], SelectQueryBuilder::SORT_DESC )
				->limit( $requestedRowCount )
				->caller( __METHOD__ );

			// Skip past the last row seen in the previous batch.
			if ( $cursorIpHex !== null && $cursorTimestamp !== null ) {
				$queryBuilder->where(
					$dbr->buildComparison( '<', [ $ipHexField => $cursorIpHex, $timestampField => $cursorTimestamp ] )
				);
			}

			$rows = $queryBuilder->fetchResultSet();
			$rowsFetched = $rows->numRows();
			foreach ( $rows as $row ) {
				/** @var stdClass $row */
				$actorIds[(int)$row->actor_id] = $row->actor_name;
				$cursorIpHex = $row->ip_hex;
				$cursorTimestamp = $row->timestamp;
			}

			if ( $rowsFetched === $requestedRowCount && $cursorIpHex !== null && $cursorTimestamp !== null ) {
				$actorIds += $this->fetchPossibleMissingActorIds(
					$ipConds,
					$tableName,
					$cursorIpHex,
					$cursorTimestamp
				);
			}

			$remainingRowsToScan -= $rowsFetched;
			if ( !$rowsFetched || $rowsFetched < $requestedRowCount ) {
				break;
			}

		}

		return $actorIds;
	}

	/**
	 * Fetch actor IDs that share the exact same (ip_hex, timestamp) as the last row of the current batch.
	 *
	 * If a batch boundary falls in the middle of a tie group, the next iteration's
	 * strict cursor would skip the remaining tied rows. This point-lookup captures them.
	 *
	 * @param IExpression $ipConds WHERE conditions for the IP range
	 * @param string $tableName 'cu_changes' or 'cu_log_event'
	 * @param string $cursorIpHex Hex IP of the last row fetched in the current batch
	 * @param string $cursorTimestamp Timestamp of the last row fetched in the current batch
	 * @return array<int,string> Map of actor ID (int) => actor name (string)
	 */
	private function fetchPossibleMissingActorIds(
		IExpression $ipConds,
		string $tableName,
		string $cursorIpHex,
		string $cursorTimestamp
	): array {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$tableName];
		$actorField = $prefix . 'actor';
		$ipHexField = $prefix . 'ip_hex';
		$timestampField = $prefix . 'timestamp';

		$tieRows = $dbr->newSelectQueryBuilder()
			->select( [ 'actor_id' => $actorField, 'actor_name' => 'actor_name' ] )
			->table( $tableName )
			->join( 'actor', null, "actor_id=$actorField" )
			->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
			->where( $ipConds )
			->where( [ $ipHexField => $cursorIpHex, $timestampField => $cursorTimestamp ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$actorIdToNameMap = [];
		foreach ( $tieRows as $row ) {
			/** @var stdClass $row */
			$actorIdToNameMap[(int)$row->actor_id] = $row->actor_name;
		}
		return $actorIdToNameMap;
	}

	/**
	 * For each user ID, find when that user last acted from the given IP range.
	 *
	 * @param array $actorIds User IDs as keys (from collectTempAccountActorIdsBatched)
	 * @param IExpression $ipConds WHERE conditions for the IP range
	 * @param string $tableName 'cu_changes' or 'cu_log_event'
	 * @return array Map of user ID (int) => latest timestamp (string)
	 */
	private function getActorsMostRecentActionTimestamps(
		array $actorIds,
		IExpression $ipConds,
		string $tableName
	): array {
		$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$tableName];
		$actorField = $prefix . 'actor';
		$timestampField = $prefix . 'timestamp';

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$timestampRows = $dbr->newSelectQueryBuilder()
			->fields( [
				'actor_id' => $actorField,
				'timestamp' => "MAX($timestampField)",
			] )
			->table( $tableName )
			->where( [ $actorField => array_keys( $actorIds ) ] )
			->where( $ipConds )
			->groupBy( $actorField )
			->caller( __METHOD__ )
			->fetchResultSet();

		$actorIdToTimestampMap = [];
		foreach ( $timestampRows as $row ) {
			/** @var stdClass $row */
			$actorIdToTimestampMap[(int)$row->actor_id] = (string)$row->timestamp;
		}

		return $actorIdToTimestampMap;
	}

	/**
	 * Given a temporary account, return the count of temporary accounts that
	 * have shared the same IPs.
	 *
	 * Since this is an aggregate, a permissions check is not needed.
	 *
	 * @param UserIdentity $user The temporary account to start lookup with
	 * @param int|null $limit The maximum number of rows to fetch
	 * @return int Final count, up to the limit if one is passed
	 */
	public function getAggregateActiveTempAccountCount( UserIdentity $user, ?int $limit = null ): int {
		$accounts = $this->getActiveTempAccounts( $user, $limit );
		return $limit ? min( $limit, count( $accounts ) ) : count( $accounts );
	}

	/**
	 * Given a temporary account, return the names of the temporary accounts
	 * that have shared the same IPs.
	 *
	 * This checks that the Authority can view temporary account IPs and
	 * removes any names that the Authority is not allowed to see.
	 *
	 * @param Authority $authority The authority making the request
	 * @param UserIdentity $user The temporary account to start lookup with
	 * @param int|null $limit The maximum number of names to fetch
	 * @return StatusValue<string[]> A good status with an array of names of
	 *  related temporary accounts, up to the limit if one is passed, or a
	 *  CheckUserPermissionStatus if the performer does not have permission
	 *  to view temporary account IPs.
	 */
	public function getActiveTempAccountNames(
		Authority $authority,
		UserIdentity $user,
		?int $limit = null
	): StatusValue {
		$status = $this->checkUserPermissionManager
			->canAccessTemporaryAccountIPAddresses( $authority );

		if ( !$status->isGood() ) {
			return $status;
		}

		if ( $this->readOnlyMode->isReadOnly() ) {
			return StatusValue::newFatal( 'readonlytext', $this->readOnlyMode->getReason() );
		}

		$this->jobQueueGroup->push(
			LogTemporaryAccountAccessJob::newSpec(
				$authority->getUser(),
				$user->getName(),
				TemporaryAccountLogger::ACTION_VIEW_RELATED_TEMPORARY_ACCOUNTS
			)
		);

		$accounts = $this->getActiveTempAccounts( $user, $limit );

		// TODO: Remove hidden names in ::getTempAccountsFromIPAddress
		if ( !$authority->isAllowed( 'hideuser' ) ) {
			foreach ( $accounts as $name => $canSee ) {
				if ( $this->userFactory->newFromName( $name )->isHidden() ) {
					$accounts[$name] = null;
				}
			}
			$accounts = array_filter( $accounts );
		}

		if ( $limit && $limit < count( $accounts ) ) {
			$accounts = array_slice( $accounts, 0, $limit );
		}

		return StatusValue::newGood( array_keys( $accounts ) );
	}

	/**
	 * Note that this does not check permissions, handle logging or handle
	 * hidden temporary accounts.
	 *
	 * Given a temporary account:
	 * 1. Find all IPs associated with the account
	 * 2. Find all temp accounts on all the IPs
	 * 3. Return the user names (there may be more than the limit)
	 *
	 * @param UserIdentity $user The temporary account to start lookup with
	 * @param int|null $limit The maximum number of accounts to fetch
	 * @return array Array with temporary account name keys, which may be
	 *  longer than the limit
	 */
	private function getActiveTempAccounts( UserIdentity $user, ?int $limit = null ): array {
		if ( !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			throw new InvalidArgumentException( 'Invalid user passed; only temporary accounts are supported' );
		}

		$ipsFromTempAccount = $this->getDistinctIPsFromTempAccount( $user );

		$uniqueAccountNames = [];
		foreach ( $ipsFromTempAccount as $ip ) {
			if ( $limit && count( $uniqueAccountNames ) >= $limit ) {
				break;
			}

			if ( IPUtils::isIPv6( $ip ) ) {
				// If IPv6, we want to look up the entire /64 range
				// To de-dupe IPv6 lookups, the IP passed through is converted into the
				// beginning of the range before returning the CIDR reprsentation
				[ $ipHex ] = IPUtils::parseRange( $ip );
				$ip = IPUtils::formatHex( $ipHex ) . '/64';
			}
			$accountsOnIp = $this->getTempAccountsFromIPAddress( $ip, $limit );
			foreach ( $accountsOnIp as $account ) {
				$uniqueAccountNames[ $account ] = true;
			}
		}

		return $uniqueAccountNames;
	}

	/**
	 * Instead of returning a precise number, return the bucket the number fits in.
	 * This has a default bucket range defined from work on T388718 but a different set
	 * of ranges can be used in the following format:
	 * [
	 *   'max' => maxCount // expected to be used like "maxCount+"
	 *   'ranges' => [
	 *     [ min1, max1 ], // expected to be used in a string like "min1-min2"
	 *     [ min2, max2 ]
	 *   ]
	 * ]
	 * Mins and maxes should be inclusive. See function for example.
	 * This function will return a bucketStart and a bucketEnd. If the count matches
	 * the min of 0 or the specified max, bucketStart and bucketEnd will be identical.
	 *
	 * @param int $count Count to be bucketed
	 * @param array|null $buckets Bucket structure, see comments and fallback example
	 * @return int[] Bucket the count belongs to
	 */
	public function getBucketedCount( int $count, ?array $buckets = null ): array {
		if ( $buckets === null ) {
			$buckets = [
				'max' => 101,
				'ranges' => [
					[ 1, 1 ],
					[ 2, 5 ],
					[ 6, 10 ],
					[ 11, 20 ],
					[ 21, 50 ],
					[ 51, 100 ],
				],
			];
		}
		if ( !$count ) {
			return [ 0, 0 ];
		}
		if ( $count >= $buckets['max'] ) {
			return [ $buckets['max'], $buckets['max'] ];
		}

		$bucketStart = 0;
		$bucketEnd = 0;
		foreach ( $buckets['ranges'] as $range ) {
			if ( $count >= $range[0] && $count <= $range[1] ) {
				$bucketStart = $range[0];
				$bucketEnd = $range[1];
				break;
			}
		}
		return [
			$bucketStart, $bucketEnd,
		];
	}

	/**
	 * For use by account <=> ip functions that need to sort through the results of
	 * multiple databases and return a final list. Queries are independent and may
	 * return duplicate identifiers when combined without additional processing.
	 * Given an arbitrary number of arrays which conform to the expected schema, return
	 * a single array of entity ids sorted by timestamp descending and sliced to limit
	 *
	 * @param int $limit
	 * @param string[] ...$entities [ ip/account => timestamp ]
	 * @return string[] [ ip/account ]
	 */
	private function mergeAndSortByLatestTimeStamp( int $limit, array ...$entities ): array {
		$sorted = [];
		foreach ( $entities as $entitySet ) {
			foreach ( $entitySet as $ipOrUsername => $timestamp ) {
				if ( !isset( $sorted[$ipOrUsername] ) || $sorted[$ipOrUsername] < $timestamp ) {
					$sorted[$ipOrUsername] = $timestamp;
				}
			}
		}

		// Results may be out of order, re-order them by timestamp descending
		arsort( $sorted );

		// Drop the timestamp as we only care about the entity value which is now sorted in descending time order
		$sorted = array_keys( $sorted );

		// Slice to respect the limit and return the final result
		return array_slice( $sorted, 0, $limit );
	}

	/**
	 * Given a temporary account, return all IPs associated with it via public actions only.
	 * This function should be called from a wrapper so that `checkPermissions()` can
	 * be run if necessary. Functions like `getAggregateActiveTempAccountCount()`
	 * don't need to because they return an aggregate number, which is less restricted.
	 *
	 * @param UserIdentity $user The temporary account to look up
	 * @param int|null $limit The maximum number of rows to fetch
	 * @return string[] An array of all matching IPs, up to the limit
	 */
	private function getDistinctIPsFromTempAccount( UserIdentity $user, ?int $limit = null ): array {
		if ( !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			throw new InvalidArgumentException( 'Invalid user passed; only temporary accounts are supported' );
		}

		$dbr = $this->connectionProvider->getReplicaDatabase();

		// If no limit is supplied, set the default to CheckUserMaximumRowCount.
		$limit = $this->getQueryLimit( $limit );

		// Get IPs from cu_changes and cu_log_event, sorted by timestamp descending.
		// They'll be combined so that in case of duplicate entries, the more recent
		// timestamp can be prioritized. Save the IP as the key for de-duping.
		$ipsByTable = [];
		foreach ( [ self::CHANGES_TABLE, self::LOG_EVENT_TABLE ] as $table ) {
			$prefix = self::RESULT_TABLE_TO_PREFIX[$table];
			$ipHexField = $prefix . 'ip_hex';
			$timestampField = $prefix . 'timestamp';
			$actorField = $prefix . 'actor';
			$indexName = $prefix . 'actor_ip_hex_time';

			$ipRows = $dbr->newSelectQueryBuilder()
				->select( [ 'ip_hex' => $ipHexField, 'timestamp' => "MAX($timestampField)" ] )
				->groupBy( $ipHexField )
				->from( $table )
				// T338276
				->useIndex( $indexName )
				->join( 'actor', null, "$actorField=actor_id" )
				->where( [
					'actor_name' => $user->getName(),
				] )
				->orderBy( [
					'timestamp ' . SelectQueryBuilder::SORT_DESC,
					$ipHexField . ' ' . SelectQueryBuilder::SORT_ASC,
				] )
				->limit( $limit )
				->caller( __METHOD__ )
				->fetchResultSet();

			$ipsByTable[$table] = [];
			foreach ( $ipRows as $ipRow ) {
				/** @var stdClass $ipRow */
				$ip = IPUtils::formatHex( $ipRow->ip_hex );
				$ipsByTable[$table][$ip] = $ipRow->timestamp;
			}
		}

		return $this->mergeAndSortByLatestTimeStamp(
			$limit,
			$ipsByTable[self::CHANGES_TABLE],
			$ipsByTable[self::LOG_EVENT_TABLE]
		);
	}

	/**
	 * Given a temporary account, return the count of IPs that the account has used.
	 *
	 * This doesn't do any permissions checks so it should be called from a handler that does.
	 *
	 * @param UserIdentity $user The temporary account to start lookup with
	 * @param int|null $limit The maximum number of rows to fetch
	 * @return int Final count, up to the limit if one is passed
	 */
	public function getIpsUsedCount( UserIdentity $user, ?int $limit = null ): int {
		$ips = $this->getDistinctIPsFromTempAccount( $user );
		return $limit ? min( $limit, count( $ips ) ) : count( $ips );
	}

	private function checkPermissions( Authority $authority ): StatusValue {
		if ( !$authority->isNamed() ) {
			// n.b. Here and for checkuser-rest-access-denied-blocked-user, the message
			// key specifies "REST", but the message is generic enough to reuse in this context.
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}
		if (
			!$this->permissionManager->userHasRight(
				$authority->getUser(),
				'checkuser-temporary-account-no-preference'
			) &&
			(
				!$this->permissionManager->userHasRight(
					$authority->getUser(),
					'checkuser-temporary-account'
				) ||
				!$this->userOptionsLookup->getOption(
					$authority->getUser(),
					'checkuser-temporary-account-enable'
				)
			)
		) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied' );
		}

		if ( $authority->getBlock() ) {
			return StatusValue::newFatal( 'checkuser-rest-access-denied-blocked-user' );
		}
		return StatusValue::newGood();
	}

	/**
	 * @param int|null $limit A limit if passed, otherwise the function will provide a fallback
	 * @return int
	 */
	public function getQueryLimit( ?int $limit = null ) {
		if ( !$limit ) {
			$limit = $this->serviceOptions->get( 'CheckUserMaximumRowCount' );
		} else {
			// The limit is the smaller of the user-provided limit parameter and the maximum row count.
			$limit = min( $limit, $this->serviceOptions->get( 'CheckUserMaximumRowCount' ) );
		}
		return $limit;
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CheckUserTemporaryAccountsByIPLookup::class,
	'MediaWiki\\CheckUser\\Services\\CheckUserTemporaryAccountsByIPLookup'
);
// @codeCoverageIgnoreEnd
