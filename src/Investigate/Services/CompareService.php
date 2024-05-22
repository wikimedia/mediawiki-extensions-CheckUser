<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use LogicException;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\AndExpressionGroup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

class CompareService extends ChangeService {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserInvestigateMaximumRowCount',
		'CheckUserEventTablesMigrationStage',
	];

	/** @var int */
	private $limit;

	/**
	 * @param ServiceOptions $options
	 * @param IConnectionProvider $dbProvider
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 */
	public function __construct(
		ServiceOptions $options,
		IConnectionProvider $dbProvider,
		UserIdentityLookup $userIdentityLookup,
		CheckUserLookupUtils $checkUserLookupUtils
	) {
		parent::__construct( $options, $dbProvider, $userIdentityLookup, $checkUserLookupUtils );

		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->limit = $options->get( 'CheckUserInvestigateMaximumRowCount' );
	}

	/**
	 * Get the total number of actions made from an IP.
	 *
	 * @param string $ipHex
	 * @return int
	 */
	public function getTotalActionsFromIP( string $ipHex ): int {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( 'cuc_id' )
			->from( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( [
				'cuc_ip_hex' => $ipHex,
			] )
			->limit( $this->limit )
			->caller( __METHOD__ );

		return $queryBuilder->fetchRowCount();
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
		$dbr = $this->dbProvider->getReplicaDatabase();

		if ( $targets === [] ) {
			throw new LogicException( 'Cannot get query info when $targets is empty.' );
		}
		$limit = (int)( $this->limit / count( $targets ) );

		$unionQueryBuilder = $dbr->newUnionQueryBuilder()->caller( __METHOD__ );
		foreach ( $targets as $target ) {
			$conds = $this->buildExprForSingleTarget( $target, $excludeTargets, $start );
			if ( $conds !== null ) {
				$queryBuilder = $dbr->newSelectQueryBuilder()
					->select( [
						'id' => 'cuc_id',
						'user' => 'cuc_user_actor.actor_user',
						'user_text' => 'cuc_user_actor.actor_name',
						'ip' => 'cuc_ip',
						'ip_hex' => 'cuc_ip_hex',
						'agent' => 'cuc_agent',
						'timestamp' => 'cuc_timestamp',
					] )
					->from( 'cu_changes' )
					->join( 'actor', 'cuc_user_actor', 'cuc_user_actor.actor_id=cuc_actor' )
					->where( $conds )
					->caller( __METHOD__ );
				if ( $dbr->unionSupportsOrderAndLimit() ) {
					// TODO: T360712: Add cuc_id to the ORDER BY clause to ensure unique ordering.
					$queryBuilder->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC )
						->limit( $limit );
				}
				$unionQueryBuilder->add( $queryBuilder );
			}
		}

		$derivedTable = $unionQueryBuilder->getSQL();

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'user' => 'a.user',
				'user_text' => 'a.user_text',
				'ip' => 'a.ip',
				'ip_hex' => 'a.ip_hex',
				'agent' => 'a.agent',
				'first_action' => 'MIN(a.timestamp)',
				'last_action' => 'MAX(a.timestamp)',
				'total_actions' => 'count(*)',
			],
			'options' => [
				'GROUP BY' => [
					'user',
					'user_text',
					'ip',
					'ip_hex',
					'agent',
				],
			],
		];
	}

	/**
	 * Get the WHERE conditions for a single target in an IExpression object.
	 *
	 * For the main investigation, this is used in a subquery that contributes to a derived
	 * table, used by getQueryInfo.
	 *
	 * For a limit check, this is used to build a query that is used to check whether the number of results for
	 * the target exceed the limit-per-target in getQueryInfo.
	 *
	 * @param string $target
	 * @param string[] $excludeTargets
	 * @param string $start
	 * @return IExpression|null Return null for invalid target
	 */
	private function buildExprForSingleTarget(
		string $target,
		array $excludeTargets,
		string $start
	): ?IExpression {
		$targetExpr = $this->buildTargetExpr( $target );
		if ( $targetExpr === null ) {
			return null;
		}

		$andExpressionGroup = new AndExpressionGroup();
		$andExpressionGroup = $andExpressionGroup->andExpr( $targetExpr );

		// Add the WHERE conditions to exclude the targets in the $excludeTargets array, if they can be generated.
		$excludeTargetsExpr = $this->buildExcludeTargetsExpr( $excludeTargets );
		if ( $excludeTargetsExpr !== null ) {
			$andExpressionGroup = $andExpressionGroup->andExpr( $excludeTargetsExpr );
		}
		// Add the start timestamp WHERE conditions to the query, if they can be generated.
		$startExpr = $this->buildStartExpr( $start );
		if ( $startExpr !== null ) {
			$andExpressionGroup = $andExpressionGroup->andExpr( $startExpr );
		}

		return $andExpressionGroup;
	}

	/**
	 * We set a maximum number of rows in cu_changes to be grouped in the Compare table query,
	 * for performance reasons (see ::getQueryInfo). We share these uniformly between the targets,
	 * so the maximum number of rows per target is the limit divided by the number of targets.
	 *
	 * @param array $targets
	 * @return int
	 */
	private function getLimitPerTarget( array $targets ) {
		return (int)( $this->limit / count( $targets ) );
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

		$dbr = $this->dbProvider->getReplicaDatabase();

		// If the database does not support order and limit on a UNION
		// then none of the targets can be over the limit.
		if ( !$dbr->unionSupportsOrderAndLimit() ) {
			return [];
		}

		$targetsOverLimit = [];
		$offset = $this->getLimitPerTarget( $targets );

		foreach ( $targets as $target ) {
			$conds = $this->buildExprForSingleTarget( $target, $excludeTargets, $start );
			if ( $conds !== null ) {
				$limitCheck = $dbr->newSelectQueryBuilder()
					->select( 'cuc_id' )
					->from( 'cu_changes' )
					->join( 'actor', 'cuc_user_actor', 'cuc_user_actor.actor_id=cuc_actor' )
					->where( $conds )
					->offset( $offset )
					->limit( 1 )
					->caller( __METHOD__ );
				if ( $limitCheck->fetchRowCount() > 0 ) {
					$targetsOverLimit[] = $target;
				}
			}
		}

		return $targetsOverLimit;
	}
}
