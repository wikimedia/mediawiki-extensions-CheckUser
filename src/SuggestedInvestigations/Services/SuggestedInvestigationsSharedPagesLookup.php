<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Computes per-case metrics about the pages shared between the accounts of a Suggested
 * Investigations case.
 *
 * A page is "shared" within a case if at least two distinct accounts in the case edited it within
 * the configured time window ({@link CUDMaxAge}). The metrics are computed from the local wiki's
 * core `revision` and `archive` tables, which hold the canonical edit history including deleted
 * pages.
 */
class SuggestedInvestigationsSharedPagesLookup {

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'CUDMaxAge',
	];

	private readonly int $maxDataAgeSeconds;

	public function __construct(
		ServiceOptions $options,
		private readonly IConnectionProvider $connectionProvider,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->maxDataAgeSeconds = $options->get( 'CUDMaxAge' );
	}

	/**
	 * Computes the shared-pages summary for each provided case.
	 *
	 * @param array<int,list<UserIdentity>> $caseIdToUsers Maps each case ID to the list of accounts in that case.
	 * @return SuggestedInvestigationsSharedPagesSummary[] Maps each input case ID to the summary of shared pages.
	 */
	public function getSharedPagesForCases( array $caseIdToUsers ): array {
		// First, flatten the array to have list of unique user ids
		$allUserIds = [];
		foreach ( $caseIdToUsers as $users ) {
			foreach ( $users as $user ) {
				$allUserIds[$user->getId()] = true;
			}
		}
		$allUserIds = array_keys( $allUserIds );

		// Then, list all pages edited recently by the users in cases
		$undeletedPages = $this->getUndeletedPagesEditedByUsers( $allUserIds );
		$deletedPages = $this->getDeletedPagesEditedByUsers( $allUserIds );

		// Group by namespace id and title, so that contributions that have been since deleted will be clustered
		// with undeleted edits to pages with the same title.
		$pageEditors = [];
		$pageIdentities = [];
		foreach ( [ $undeletedPages, $deletedPages ] as $iterable ) {
			foreach ( $iterable as $entry ) {
				/** @var PageIdentity $pageIdentity */
				$pageIdentity = $entry[0];
				$userId = $entry[1];
				$numEdits = $entry[2];

				$key = $pageIdentity->getNamespace() . ':' . $pageIdentity->getDBkey();
				if ( !isset( $pageIdentities[$key] ) || $pageIdentity->getId() > 0 ) {
					// Record page identities for a given page, but prefer existing pages (with page_id)
					$pageIdentities[$key] = $pageIdentity;
				}

				if ( !isset( $pageEditors[$key][$userId] ) ) {
					$pageEditors[$key][$userId] = 0;
				}
				$pageEditors[$key][$userId] += $numEdits;
			}
		}

		return array_map( function ( $users ) use ( $pageEditors, $pageIdentities ) {
			return $this->buildSummaryForCase( $users, $pageEditors, $pageIdentities );
		}, $caseIdToUsers );
	}

	/**
	 * @return iterable<array{0:PageIdentity,1:int,2:int}> page, user id, number of edits to that page
	 */
	private function getUndeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$result = $dbr->newSelectQueryBuilder()
				->select( [
					'page_namespace',
					'page_title',
					'page_id',
					'actor_user',
					'edits' => 'COUNT(*)',
				] )
				->from( 'revision' )
				->join( 'actor', null, 'actor_id = rev_actor' )
				->join( 'page', null, 'page_id = rev_page' )
				->where( [
					'actor_user' => $userIdChunk,
					$dbr->expr( 'rev_timestamp', '>=', $cutoff ),
				] )
				->groupBy( [ 'page_namespace', 'page_title', 'page_id', 'actor_user' ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$pageId = (int)$row->page_id;
				$pageNamespace = (int)$row->page_namespace;
				$pageTitle = $row->page_title;
				$userId = (int)$row->actor_user;
				$numEdits = (int)$row->edits;
				yield [
					PageIdentityValue::localIdentity( $pageId, $pageNamespace, $pageTitle ),
					$userId,
					$numEdits,
				];
			}
		}
	}

	/**
	 * @return iterable<array{0:PageIdentity,1:int,2:int}> page, user id, number of edits to that page
	 */
	private function getDeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$result = $dbr->newSelectQueryBuilder()
				->select( [
					'ar_namespace',
					'ar_title',
					'actor_user',
					'edits' => 'COUNT(*)',
				] )
				->from( 'archive' )
				->join( 'actor', null, 'actor_id = ar_actor' )
				->where( [
					'actor_user' => $userIdChunk,
					$dbr->expr( 'ar_timestamp', '>=', $cutoff ),
				] )
				->groupBy( [ 'ar_namespace', 'ar_title', 'actor_user' ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$pageNamespace = (int)$row->ar_namespace;
				$pageTitle = $row->ar_title;
				$userId = (int)$row->actor_user;
				$numEdits = (int)$row->edits;
				yield [
					PageIdentityValue::localIdentity( 0, $pageNamespace, $pageTitle ),
					$userId,
					$numEdits,
				];
			}
		}
	}

	/**
	 * @param UserIdentity[] $users The accounts in the case.
	 * @param array<string,array<int,int>> $pageEditors
	 * @param array<string,PageIdentity> $pageIdentities
	 * @return SuggestedInvestigationsSharedPagesSummary
	 */
	private function buildSummaryForCase(
		array $users,
		array $pageEditors,
		array $pageIdentities
	): SuggestedInvestigationsSharedPagesSummary {
		$caseUserIds = [];
		foreach ( $users as $user ) {
			$caseUserIds[$user->getId()] = true;
		}

		if ( count( $caseUserIds ) < 2 ) {
			return new SuggestedInvestigationsSharedPagesSummary( 0, [] );
		}

		$editCount = 0;
		$sharedPages = [];
		foreach ( $pageEditors as $key => $editCounts ) {
			$editsByCaseUsers = array_intersect_key( $editCounts, $caseUserIds );
			if ( count( $editsByCaseUsers ) < 2 ) {
				continue;
			}

			$editCount += array_sum( $editsByCaseUsers );
			$sharedPages[] = $pageIdentities[$key];
		}

		return new SuggestedInvestigationsSharedPagesSummary( $editCount, $sharedPages );
	}
}
