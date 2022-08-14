<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use IDatabase;
use LogicException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\Subquery;

class CompareService extends ChangeService {
	/** @var ServiceOptions */
	private $options;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserInvestigateMaximumRowCount',
	];

	/** @var int */
	private $limit;

	/**
	 * @param ServiceOptions $options
	 * @param ILoadBalancer $loadBalancer
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		ServiceOptions $options,
		ILoadBalancer $loadBalancer,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct(
			$loadBalancer->getConnection( DB_REPLICA ),
			$loadBalancer->getConnection( DB_REPLICA ),
			$userIdentityLookup
		);

		$this->loadBalancer = $loadBalancer;
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->limit = $options->get( 'CheckUserInvestigateMaximumRowCount' );
	}

	/**
	 * Get edits made from an ip
	 *
	 * @param string $ipHex
	 * @param string|null $excludeUser
	 * @return int
	 */
	public function getTotalEditsFromIp(
		string $ipHex,
		string $excludeUser = null
	): int {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$conds = [
			'cuc_ip_hex' => $ipHex,
			'cuc_type' => [ RC_EDIT, RC_NEW ],
		];

		if ( $excludeUser ) {
			$conds[] = 'cuc_user_text != ' . $this->dbQuoter->addQuotes( $excludeUser );
		}

		return $db->selectRowCount( 'cu_changes', '*', $conds, __METHOD__ );
	}

	/**
	 * Get the compare query info
	 *
	 * @param string[] $targets
	 * @param string[] $excludeTargets
	 * @param string $start
	 * @return array
	 */
	public function getQueryInfo( array $targets, array $excludeTargets, string $start ): array {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		if ( $targets === [] ) {
			throw new LogicException( 'Cannot get query info when $targets is empty.' );
		}
		$limit = (int)( $this->limit / count( $targets ) );

		$sqlText = [];
		foreach ( $targets as $target ) {
			$info = $this->getQueryInfoForSingleTarget( $target, $excludeTargets, $start, $limit );
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

		$derivedTable = $db->unionQueries( $sqlText, IDatabase::UNION_DISTINCT );

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'cuc_user' => 'a.cuc_user',
				'cuc_user_text' => 'a.cuc_user_text',
				'cuc_ip' => 'a.cuc_ip',
				'cuc_ip_hex' => 'a.cuc_ip_hex',
				'cuc_agent' => 'a.cuc_agent',
				'first_edit' => 'MIN(a.cuc_timestamp)',
				'last_edit' => 'MAX(a.cuc_timestamp)',
				'total_edits' => 'count(*)',
			],
			'options' => [
				'GROUP BY' => [
					'cuc_user',
					'cuc_user_text',
					'cuc_ip',
					'cuc_ip_hex',
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
	 * @param string[] $excludeTargets
	 * @param string $start
	 * @param int $limitPerTarget
	 * @param bool $limitCheck
	 * @return array|null Return null for invalid target
	 */
	public function getQueryInfoForSingleTarget(
		string $target,
		array $excludeTargets,
		string $start,
		int $limitPerTarget,
		$limitCheck = false
	): ?array {
		if ( $limitCheck ) {
			$orderBy = null;
			$offset = $limitPerTarget;
			$limit = 1;
		} else {
			$orderBy = 'cuc_timestamp DESC';
			$offset = null;
			$limit = $limitPerTarget;
		}

		$conds = $this->buildTargetConds( $target );
		if ( $conds === [] ) {
			return null;
		}

		$conds = array_merge(
			$conds,
			$this->buildExcludeTargetsConds( $excludeTargets ),
			$this->buildStartConds( $start )
		);

		$conds['cuc_type'] = [ RC_EDIT, RC_NEW, RC_LOG ];

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
	 * Check if we have incomplete data for any of the targets.
	 *
	 * @param string[] $targets
	 * @param string[] $excludeTargets
	 * @param string $start
	 * @return string[]
	 */
	public function getTargetsOverLimit(
		array $targets,
		array $excludeTargets,
		string $start
	): array {
		if ( $targets === [] ) {
			return $targets;
		}

		$db = $this->loadBalancer->getConnection( DB_REPLICA );

		// If the database does not support order and limit on a UNION
		// then none of the targets can be over the limit.
		if ( !$db->unionSupportsOrderAndLimit() ) {
			return [];
		}

		$targetsOverLimit = [];
		$offset = (int)( $this->limit / count( $targets ) );

		foreach ( $targets as $target ) {
			$info = $this->getQueryInfoForSingleTarget( $target, $excludeTargets, $start, $offset, true );
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
