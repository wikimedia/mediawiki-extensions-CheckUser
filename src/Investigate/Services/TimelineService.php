<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use LogicException;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;

class TimelineService extends ChangeService {
	/** @var IDatabase */
	private IDatabase $dbr;

	/**
	 * @param IDatabase $dbr
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		IDatabase $dbr,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( $dbr, $dbr, $userIdentityLookup );

		$this->dbr = $dbr;
	}

	/**
	 * Get timeline query info
	 *
	 * @param string[] $targets The targets of the check
	 * @param string[] $excludeTargets The targets to exclude from the check
	 * @param string $start The start offset
	 * @return array
	 */
	public function getQueryInfo( array $targets, array $excludeTargets, string $start ): array {
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
				$ipTargets, $excludeTargets, $start, 'cuc_ip_hex_time'
			);
		}
		if ( count( $userTargets ) ) {
			$userTargetsQuery = $this->getSelectQueryBuilder(
				$userTargets, $excludeTargets, $start, 'cuc_user_ip_time'
			);
		}
		if ( $ipTargetsQuery === null && $userTargetsQuery === null ) {
			throw new LogicException( 'Cannot get query info when $targets is empty or contains all invalid targets.' );
		}

		$sqlText = [];
		if ( $ipTargetsQuery !== null ) {
			$sqlText[] = $ipTargetsQuery->getSQL();
		}
		if ( $userTargetsQuery !== null ) {
			$sqlText[] = $userTargetsQuery->getSQL();
		}
		$derivedTable = $this->dbr->unionQueries( $sqlText, IDatabase::UNION_DISTINCT );

		return [
			'tables' => [ 'a' => new Subquery( $derivedTable ) ],
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
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
	 * @return ?SelectQueryBuilder
	 */
	private function getSelectQueryBuilder(
		array $targets, array $excludeTargets, string $start, string $index
	): ?SelectQueryBuilder {
		$targetConds = $this->buildTargetCondsMultiple( $targets );
		// Don't run the query if no targets are valid.
		if ( $targetConds === null ) {
			return null;
		}
		$queryBuilder = $this->dbr->newSelectQueryBuilder()
			->select( [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent', 'cuc_id',
			] )
			->from( 'cu_changes' )
			->useIndex( $index )
			->where( array_merge(
				$targetConds,
				$this->buildExcludeTargetsConds( $excludeTargets ),
				$this->buildStartConds( $start )
			) )
			->caller( __METHOD__ );
		if ( $this->dbr->unionSupportsOrderAndLimit() ) {
			$queryBuilder->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( 501 );
		}
		return $queryBuilder;
	}
}
