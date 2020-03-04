<?php

namespace MediaWiki\CheckUser;

use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\Subquery;

class CompareService {
	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var int */
	private $limit;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param int $limit Maximum number of rows to access (T245499)
	 */
	public function __construct( ILoadBalancer $loadBalancer, $limit = 100000 ) {
		$this->loadBalancer = $loadBalancer;
		$this->limit = $limit;
	}

	/**
	 * Get edits made from an ip
	 *
	 * @param string $ip
	 * @param string|null $excludeUser
	 * @return array
	 */
	public function getTotalEditsFromIp(
		string $ip,
		string $excludeUser = null
	): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$conds = [
			'cuc_ip' => $ip,
			'cuc_type' => [ RC_EDIT, RC_NEW ],
		];

		if ( $excludeUser ) {
			$conds[] = 'cuc_user_text != ' . $db->addQuotes( $excludeUser );
		}

		$data = $db->selectRow(
			'cu_changes',
			[
				'total_edits' => 'COUNT(*)',
				'total_users' => 'COUNT(distinct cuc_user_text)'
			],
			$conds,
			__METHOD__
		);

		return $data ? (array)$data : [];
	}

	/**
	 * Get the compare query info
	 *
	 * @param string[] $targets
	 * @return array
	 */
	public function getQueryInfo( array $targets ): array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );

		if ( $targets === [] ) {
			throw new \LogicException( 'Cannot get query info when $targets is empty.' );
		}

		$limit = (int)( $this->limit / count( $targets ) );

		$sqlText = [];
		foreach ( $targets as $target ) {
			$info = $this->getQueryInfoForSingleTarget( $target, $limit );
			if ( $info !== null ) {
				if ( !$db->unionSupportsOrderAndLimit() ) {
					unset( $info['options']['ORDER BY'], $info['options']['LIMIT'] );
				}
				$sqlText[] = $db->selectSQLText(
					$info['tables'],
					$info['fields'],
					$info['conds'],
					__METHOD__,
					$info['options']
				);
			}
		}

		$derivedTable = $db->unionQueries( $sqlText, $db::UNION_DISTINCT );

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'a.cuc_user',
				'a.cuc_user_text',
				'a.cuc_ip',
				'a.cuc_ip_hex',
				'a.cuc_agent',
				'first_edit' => 'MIN(a.cuc_timestamp)',
				'last_edit' => 'MAX(a.cuc_timestamp)',
				'total_edits' => 'count(*)',
			],
			'options' => [
				'GROUP BY' => [
					'cuc_user_text',
					'cuc_ip',
					'cuc_agent',
				],
			],
		];
	}

	/**
	 * Get the query info for a single target.
	 *
	 * For the main investigation, this becomes a subquery that contributes to a derived
	 * table, used by getQueryInfo.
	 *
	 * For a limit check, this query is used to check whether the number of results for
	 * the target exceed the limit-per-target in getQueryInfo.
	 *
	 * @param string $target
	 * @param int $limitPerTarget
	 * @param bool $limitCheck
	 * @return array|null Return null for invalid target
	 */
	public function getQueryInfoForSingleTarget(
		$target,
		int $limitPerTarget,
		$limitCheck = false
	) : ?array {
		if ( $limitCheck ) {
			$orderBy = null;
			$offset = $limitPerTarget;
			$limit = 1;
		} else {
			$orderBy = 'cuc_timestamp DESC';
			$offset = null;
			$limit = $limitPerTarget;
		}

		$conds = $this->buildUserConds( $target );
		if ( $conds === [] ) {
			return null;
		}

		// TODO: Add timestamp conditions (T246261)
		$conds['cuc_type'] = [ RC_EDIT, RC_NEW ];

		return [
			'tables' => 'cu_changes',
			'fields' => [
				'cuc_id',
				'cuc_user',
				'cuc_user_text',
				'cuc_ip',
				'cuc_ip_hex',
				'cuc_agent',
				'cuc_timestamp',
			],
			'conds' => $conds,
			'options' => [
				'ORDER BY' => $orderBy,
				'LIMIT' => $limit,
				'OFFSET' => $offset,
			],
		];
	}

	/**
	 * Builds a query predicate depending on what type of
	 * target is passed in
	 *
	 * @param string $target
	 * @return string[]
	 */
	private function buildUserConds( $target ) : array {
		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$conds = [];

		if ( IPUtils::isIpAddress( $target ) ) {
			if ( IPUtils::isValid( $target ) ) {
				$conds['cuc_ip_hex'] = IPUtils::toHex( $target );
			} elseif ( IPUtils::isValidRange( $target ) ) {
				$range = IPUtils::parseRange( $target );
				$conds[] = $db->makeList( [
					'cuc_ip_hex >= ' . $db->addQuotes( $range[0] ),
					'cuc_ip_hex <=' . $db->addQuotes( $range[1] )
				], IDatabase::LIST_AND );
			}
		} else {
			// TODO: This may filter out invalid values, changing the number of
			// targets. The per-target limit should change too (T246393).
			$userId = $this->getUserId( $target );
			if ( $userId ) {
				$conds['cuc_user'] = $userId;
			}
		}

		return $conds;
	}

	/**
	 * Get user ID from a user name; for mocking in tests.
	 *
	 * @param string $username
	 * @return int|null Id, or null if the username is invalid or non-existent
	 */
	protected function getUserId( $username ) : ?int {
		return User::idFromName( $username );
	}

	/**
	 * Check if we have incomplete data for any of the targets.
	 *
	 * @param string[] $targets
	 * @return string[]
	 */
	public function getTargetsOverLimit( array $targets ) : array {
		if ( $targets === [] ) {
			return $targets;
		}

		$db = $this->loadBalancer->getConnectionRef( DB_REPLICA );

		// If the database does not support order and limit on a UNION
		// then none of the targets can be over the limit.
		if ( !$db->unionSupportsOrderAndLimit() ) {
			return [];
		}

		$targetsOverLimit = [];
		$offset = (int)( $this->limit / count( $targets ) );

		foreach ( $targets as $target ) {
			$info = $this->getQueryInfoForSingleTarget( $target, $offset, true );
			if ( $info !== null ) {
				$limitCheck = $db->select(
					$info['tables'],
					$info['fields'],
					$info['conds'],
					__METHOD__,
					$info['options']
				);
				if ( $limitCheck->numRows() > 0 ) {
					$targetsOverLimit[] = $target;
				}
			}
		}

		return $targetsOverLimit;
	}
}
