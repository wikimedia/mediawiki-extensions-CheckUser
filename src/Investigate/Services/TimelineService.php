<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use LogicException;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

class TimelineService extends ChangeService {

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
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		parent::__construct( $options, $dbProvider, $userIdentityLookup, $checkUserLookupUtils );
	}

	/**
	 * Get timeline query info
	 *
	 * @param string[] $targets The targets of the check
	 * @param string[] $excludeTargets The targets to exclude from the check
	 * @param string $start The start offset
	 * @param int $limit The limit for the check
	 * @return array
	 */
	public function getQueryInfo( array $targets, array $excludeTargets, string $start, int $limit ): array {
		// Split the targets into users and IP addresses, so that two queries can be made (one for the users and one
		// for the IPs) and then unioned together.
		$ipTargets = array_filter( $targets, [ IPUtils::class, 'isIPAddress' ] );
		$userTargets = array_diff( $targets, $ipTargets );

		// Generate the queries to be combined in a UNION. If there are no valid targets for the query, then the
		// query will not be run.
		$ipTargetsQuery = null;
		$userTargetsQuery = null;
		if ( count( $ipTargets ) ) {
			$ipTargetsQuery = $this->getSelectQueryBuilder(
				$ipTargets, $excludeTargets, $start, 'cuc_ip_hex_time', $limit
			);
		}
		if ( count( $userTargets ) ) {
			$userTargetsQuery = $this->getSelectQueryBuilder(
				$userTargets, $excludeTargets, $start, 'cuc_actor_ip_time', $limit
			);
		}
		if ( $ipTargetsQuery === null && $userTargetsQuery === null ) {
			throw new LogicException( 'Cannot get query info when $targets is empty or contains all invalid targets.' );
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$unionQueryBuilder = $dbr->newUnionQueryBuilder()->caller( __METHOD__ );
		if ( $ipTargetsQuery !== null ) {
			$unionQueryBuilder->add( $ipTargetsQuery );
		}
		if ( $userTargetsQuery !== null ) {
			$unionQueryBuilder->add( $userTargetsQuery );
		}

		$derivedTable = $unionQueryBuilder->getSQL();

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'namespace', 'title', 'actiontext', 'timestamp', 'minor', 'page_id',
				'type', 'this_oldid', 'last_oldid', 'ip', 'xff', 'agent', 'id',
				'user', 'user_text', 'comment_text', 'comment_data',
			],
		];
	}

	/**
	 * Returns a SelectQueryBuilder instance that can be used to select results from the cu_changes table for the
	 * given $targets.
	 *
	 * @param string[] $targets See ::getQueryInfo
	 * @param string[] $excludeTargets See ::getQueryInfo
	 * @param string $start See ::getQueryInfo
	 * @param string $index The index to use as the FORCE INDEX index for the query
	 * @param int $limit The limit that applies the overall query
	 * @return ?SelectQueryBuilder
	 */
	private function getSelectQueryBuilder(
		array $targets, array $excludeTargets, string $start, string $index, int $limit
	): ?SelectQueryBuilder {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$targetsExpr = $this->buildTargetExprMultiple( $targets );
		// Don't run the query if no targets are valid.
		if ( $targetsExpr === null ) {
			return null;
		}
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'namespace' => 'cuc_namespace', 'title' => 'cuc_title', 'actiontext' => 'cuc_actiontext',
				'timestamp' => 'cuc_timestamp', 'minor' => 'cuc_minor', 'page_id' => 'cuc_page_id',
				'type' => 'cuc_type', 'this_oldid' => 'cuc_this_oldid', 'last_oldid' => 'cuc_last_oldid',
				'ip' => 'cuc_ip', 'xff' => 'cuc_xff', 'agent' => 'cuc_agent', 'id' => 'cuc_id',
				'user' => 'cuc_user_actor.actor_user', 'user_text' => 'cuc_user_actor.actor_name',
				'comment_text', 'comment_data',
			] )
			->from( 'cu_changes' )
			->useIndex( $index )
			->join( 'actor', 'cuc_user_actor', 'cuc_user_actor.actor_id=cuc_actor' )
			->join( 'comment', 'comment_cuc_comment', 'comment_cuc_comment.comment_id=cuc_comment_id' )
			->where( $targetsExpr )
			->caller( __METHOD__ );
		// Add the WHERE conditions to exclude the targets in the $excludeTargets array, if they can be generated.
		$excludeTargetsExpr = $this->buildExcludeTargetsExpr( $excludeTargets );
		if ( $excludeTargetsExpr !== null ) {
			$queryBuilder->where( $excludeTargetsExpr );
		}
		// Add the start timestamp WHERE conditions to the query, if they can be generated.
		$startExpr = $this->buildStartExpr( $start );
		if ( $startExpr !== null ) {
			$queryBuilder->where( $startExpr );
		}
		if ( $dbr->unionSupportsOrderAndLimit() ) {
			// TODO: T360712: Add cuc_id to the ORDER BY clause to ensure unique ordering.
			$queryBuilder->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( $limit + 1 );
		}
		return $queryBuilder;
	}
}
