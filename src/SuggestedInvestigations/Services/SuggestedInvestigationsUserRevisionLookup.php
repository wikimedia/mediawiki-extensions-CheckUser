<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRevisionRevertsSummary;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Provides revision-related lookups for Suggested Investigations.
 */
class SuggestedInvestigationsUserRevisionLookup {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CUDMaxAge',
	];

	private readonly int $maxDataAgeSeconds;

	public function __construct(
		ServiceOptions $options,
		private readonly IConnectionProvider $dbProvider,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->maxDataAgeSeconds = $options->get( 'CUDMaxAge' );
	}

	/**
	 * Get comparison of reverted revision counts to total revision counts on a case-level summed from all users.
	 *
	 * @param array<int,list<UserIdentity>> $caseIdToUsers Maps each case ID to the list of accounts in that case.
	 * @return SuggestedInvestigationsRevisionRevertsSummary[] Maps each input case ID to the reverted vs total
	 * revision counts.
	 */
	public function getRevertedRevisionCountsByUsersForCases( array $caseIdToUsers ): array {
		// First, flatten the array to have list of unique user ids
		$userIdentities = [];
		foreach ( $caseIdToUsers as $users ) {
			foreach ( $users as $user ) {
				$userIdentities[$user->getId()] = $user;
			}
		}
		$allUserIds = array_keys( $userIdentities );

		// Get all revision counts for all users across revision and archive tables
		$visibleRevisionByUserCounts = $this->getAllRevisionCountsByUsers( $allUserIds );
		$deletedRevisionByUserCounts = $this->getAllRevisionCountsByUsers( $allUserIds, true );

		// Sum all user revision counts by case
		$revisionCountsByCaseId = [];
		foreach ( $caseIdToUsers as $caseId => $users ) {
			$revisionCountsByCaseId[ $caseId ] = [
				'reverted' => 0,
				'total' => 0,
			];
			foreach ( $users as $user ) {
				$userId = $user->getId();
				$revisionCountsByCaseId[ $caseId ]['reverted'] += $visibleRevisionByUserCounts[ $userId ]['reverted'];
				$revisionCountsByCaseId[ $caseId ]['reverted'] += $deletedRevisionByUserCounts[ $userId ]['reverted'];
				$revisionCountsByCaseId[ $caseId ]['total'] += $visibleRevisionByUserCounts[ $userId ]['total'];
				$revisionCountsByCaseId[ $caseId ]['total'] += $deletedRevisionByUserCounts[ $userId ]['total'];
			}
		}

		// Return information in expected summary format
		$allCases = [];
		foreach ( $caseIdToUsers as $caseId => $users ) {
			$allCases[ $caseId ] = new SuggestedInvestigationsRevisionRevertsSummary(
				$revisionCountsByCaseId[$caseId]['reverted'],
				$revisionCountsByCaseId[$caseId]['total'],
			);
		}
		return $allCases;
	}

	/**
	 * Given an array of users, return the count of all revisions or archived revisions for those users
	 * separated into a reverted count and a total count.
	 * @param array $userIds
	 * @param bool $getDeleted Whether or use the revision or archive table
	 * @return array [ userId => [ 'reverted' => x, 'total' => y ] ]
	 */
	private function getAllRevisionCountsByUsers( array $userIds, bool $getDeleted = false ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		$revisionsByUser = [];
		foreach ( $userIds as $userId ) {
			$revisionsByUser[ $userId ] = [
				'reverted' => 0,
				'total' => 0,
			];
		}
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$revisionTableName = $getDeleted ? 'archive' : 'revision';
			$revisionQueryBuilder = $dbr->newSelectQueryBuilder()->from( $revisionTableName );

			$revertedRevisionTagJoinCond = $getDeleted ?
				'change_tag.ct_rev_id=ar_rev_id' :
				'change_tag.ct_rev_id=rev_id';
			$revertedRevisionQueryBuilder = $dbr
				->newSelectQueryBuilder()
				->from( $revisionTableName )
				->join(
					'change_tag',
					'change_tag',
					$revertedRevisionTagJoinCond
				)
				->join(
					'change_tag_def',
					'change_tag_def',
					'change_tag.ct_tag_id = change_tag_def.ctd_id'
				)
				->where( [ 'change_tag_def.ctd_name' => 'mw-reverted' ] );

			foreach ( [ $revisionQueryBuilder, $revertedRevisionQueryBuilder ] as $selectQueryBuilder ) {
				if ( $getDeleted ) {
					$selectQueryBuilder
						->join( 'actor', 'archive_actor', 'actor_id=ar_actor' )
						->select( [ 'archive_actor.actor_user' ] )
						->where( $dbr->expr( 'ar_timestamp', '>=', $cutoff ) );
				} else {
					$selectQueryBuilder
						->join( 'actor', 'actor_rev_user', 'actor_rev_user.actor_id = rev_actor' )
						->select( [ 'actor_rev_user.actor_user' ] )
						->where( $dbr->expr( 'rev_timestamp', '>=', $cutoff ) );
				}
				$selectQueryBuilder
					->select( [ 'count' => 'COUNT(*)' ] )
					->where( [
						'actor_user' => $userIdChunk,
					] )
					->groupBy( 'actor_user' )
					->caller( __METHOD__ );
			}

			foreach ( $revisionQueryBuilder->fetchResultSet() as $row ) {
				$revisionsByUser[ $row->actor_user ]['total'] = (int)$row->count;
			}
			foreach ( $revertedRevisionQueryBuilder->fetchResultSet() as $row ) {
				$revisionsByUser[ $row->actor_user ]['reverted'] = (int)$row->count;
			}
		}

		return $revisionsByUser;
	}

	/**
	 * Checks if the given revision is the first revision authored by the user,
	 * considering both live revisions and revisions archived due to page deletion.
	 */
	public function isFirstEditByUser( UserIdentity $userIdentity, int $revId ): bool {
		$db = $this->dbProvider->getPrimaryDatabase();
		$actorName = $userIdentity->getName();

		$revCount = $db->newSelectQueryBuilder()
			->from( 'revision' )
			->join( 'actor', null, 'actor_id = rev_actor' )
			->where( [
				'actor_name' => $actorName,
				$db->expr( 'rev_id', '<=', $revId ),
			] )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( $revCount >= 2 ) {
			return false;
		}

		$archiveCount = $db->newSelectQueryBuilder()
			->from( 'archive' )
			->join( 'actor', null, 'actor_id = ar_actor' )
			->where( [
				'actor_name' => $actorName,
				$db->expr( 'ar_rev_id', '<=', $revId ),
			] )
			->limit( 2 - $revCount )
			->caller( __METHOD__ )
			->fetchRowCount();

		return ( $revCount + $archiveCount ) === 1;
	}
}
