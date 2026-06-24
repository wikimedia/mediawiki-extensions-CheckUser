<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
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
	 * This many edits by a single user will be considered for counting. This applies separately to `revision`
	 * and `archive` tables, so in total up to twice that many edits can be read.
	 * The limit is actually set to this value times the number of users in batch, so depending on the edit times,
	 * users might have uneven number of edits processed.
	 */
	private const EDITS_PER_USER = 500;

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
		private readonly RevisionStore $revisionStore,
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
		$userIdentities = [];
		foreach ( $caseIdToUsers as $users ) {
			foreach ( $users as $user ) {
				$userIdentities[$user->getId()] = $user;
			}
		}
		$allUserIds = array_keys( $userIdentities );

		// Then, list all pages edited recently by the users in cases
		$undeletedPages = $this->getUndeletedPagesEditedByUsers( $allUserIds );
		$deletedPages = $this->getDeletedPagesEditedByUsers( $allUserIds );

		// Group by namespace id and title, so that contributions that have been since deleted will be clustered
		// with undeleted edits to pages with the same title.
		$pageEditorRevIds = [];
		$pageEditorMinTs = [];
		$pageEditorMaxTs = [];
		$pageIdentities = [];
		foreach ( [ $undeletedPages, $deletedPages ] as $iterable ) {
			foreach ( $iterable as $entry ) {
				/** @var PageIdentity $pageIdentity */
				$pageIdentity = $entry['page'];
				$userId = $entry['userId'];
				$revIds = $entry['revIds'];
				$minTimestamp = $entry['minTimestamp'];
				$maxTimestamp = $entry['maxTimestamp'];

				$key = $pageIdentity->getNamespace() . ':' . $pageIdentity->getDBkey();
				if ( !isset( $pageIdentities[$key] ) || $pageIdentity->getId() > 0 ) {
					// Record page identities for a given page, but prefer existing pages (with page_id)
					$pageIdentities[$key] = $pageIdentity;
				}

				if ( !isset( $pageEditorRevIds[$key][$userId] ) ) {
					$pageEditorRevIds[$key][$userId] = $revIds;
					$pageEditorMinTs[$key][$userId] = $minTimestamp;
					$pageEditorMaxTs[$key][$userId] = $maxTimestamp;
				} else {
					// A page-editor pair may appear in both the undeleted and deleted edits (the
					// page was partially deleted), so combine the revision IDs and timestamp ranges.
					$pageEditorRevIds[$key][$userId] = array_merge( $pageEditorRevIds[$key][$userId], $revIds );
					// @phan-suppress-next-line PhanTypeInvalidDimOffset Phan doesn't know it's already initialized
					$pageEditorMinTs[$key][$userId] = min( $pageEditorMinTs[$key][$userId], $minTimestamp );
					// @phan-suppress-next-line PhanTypeInvalidDimOffset as above
					$pageEditorMaxTs[$key][$userId] = max( $pageEditorMaxTs[$key][$userId], $maxTimestamp );
				}
			}
		}

		return array_map(
			function ( $users )
			use ( $pageEditorRevIds, $pageEditorMinTs, $pageEditorMaxTs, $pageIdentities, $userIdentities ) {
				return $this->buildSummaryForCase(
					$users,
					$pageEditorRevIds,
					$pageEditorMinTs,
					$pageEditorMaxTs,
					$pageIdentities,
					$userIdentities
				);
			},
			$caseIdToUsers
		);
	}

	/**
	 * @return iterable<array> Each returned array has the following keys:
	 *   - 'page', a PageIdentity of the edited page.
	 *   - 'userId', int, id of the user.
	 *   - 'revIds', int[], the `rev_id`s of the edits by the user on that page.
	 *   - 'maxTimestamp', string, when the most recent edit by that user on the page occurred.
	 *   - 'minTimestamp', string, when the oldest edit by that user on the page occurred.
	 */
	private function getUndeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		// Collect edits on pages user by user (and also record min/max timestamps)
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$pagesById = [];
			$revIds = [];
			$maxTimestamps = [];
			$minTimestamps = [];

			$result = $this->revisionStore->newSelectQueryBuilder( $dbr )
				->joinPage()
				->where( [
					'actor_user' => $userIdChunk,
					$dbr->expr( 'rev_timestamp', '>=', $cutoff ),
				] )
				->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( self::EDITS_PER_USER * count( $userIdChunk ) )
				->caller( __METHOD__ )
				->fetchResultSet();

			// Aggregate the data from DB
			foreach ( $result as $row ) {
				$userId = (int)$row->rev_user;
				$pageId = (int)$row->page_id;
				$pageNamespace = (int)$row->page_namespace;
				$pageTitle = $row->page_title;
				$page = PageIdentityValue::localIdentity( $pageId, $pageNamespace, $pageTitle );

				// Record edit to the page
				$revIds[$userId][$pageId][] = (int)$row->rev_id;

				// Record for use when generating return values
				$pagesById[$userId][$pageId] = $page;

				// Given that the result is ordered by rev_timestamp, we don't need to check for current timestamp
				// being less/greater than already recorded ones - it will be less or equal.
				if ( !isset( $maxTimestamps[$userId][$pageId] ) ) {
					$maxTimestamps[$userId][$pageId] = $row->rev_timestamp;
				}
				$minTimestamps[$userId][$pageId] = $row->rev_timestamp;
			}

			// Yield the per-user data
			foreach ( $revIds as $userId => $userRevIds ) {
				foreach ( $userRevIds as $pageId => $pageRevIds ) {
					yield [
						'page' => $pagesById[$userId][$pageId],
						'userId' => $userId,
						'revIds' => $pageRevIds,
						'maxTimestamp' => $maxTimestamps[$userId][$pageId],
						'minTimestamp' => $minTimestamps[$userId][$pageId],
					];
				}
			}
		}
	}

	/**
	 * @return iterable<array> Each returned array has the following keys:
	 *   - 'page', a PageIdentity of the edited page. Pages known only from archived (deleted)
	 *     revisions have a page ID of 0.
	 *   - 'userId', int, id of the user.
	 *   - 'revIds', int[], the `ar_rev_id`s of the edits by the user on that page.
	 *   - 'maxTimestamp', string, when the most recent edit by that user on the page occurred.
	 *   - 'minTimestamp', string, when the oldest edit by that user on the page occurred.
	 */
	private function getDeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		// Collect edits on pages user by user (and also record min/max timestamps). Archived
		// revisions have unreliable page ids (often 0), so aggregate by namespace and title.
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$pagesByKey = [];
			$revIds = [];
			$maxTimestamps = [];
			$minTimestamps = [];

			$result = $this->revisionStore->newArchiveSelectQueryBuilder( $dbr )
				->where( [
					'actor_user' => $userIdChunk,
					$dbr->expr( 'ar_timestamp', '>=', $cutoff ),
				] )
				->orderBy( 'ar_timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( self::EDITS_PER_USER * count( $userIdChunk ) )
				->caller( __METHOD__ )
				->fetchResultSet();

			// Aggregate the data from DB
			foreach ( $result as $row ) {
				$userId = (int)$row->ar_user;
				$pageNamespace = (int)$row->ar_namespace;
				$pageTitle = $row->ar_title;

				$key = $pageNamespace . ':' . $pageTitle;
				$revIds[$userId][$key][] = (int)$row->ar_rev_id;

				// Record for use when generating return values
				$pagesByKey[$userId][$key] = PageIdentityValue::localIdentity( 0, $pageNamespace, $pageTitle );

				// Given that the result is ordered by ar_timestamp, we don't need to check for current
				// timestamp being less/greater than already recorded ones - it will be less or equal.
				if ( !isset( $maxTimestamps[$userId][$key] ) ) {
					$maxTimestamps[$userId][$key] = $row->ar_timestamp;
				}
				$minTimestamps[$userId][$key] = $row->ar_timestamp;
			}

			// Yield the per-user data
			foreach ( $revIds as $userId => $userRevIds ) {
				foreach ( $userRevIds as $key => $pageRevIds ) {
					yield [
						'page' => $pagesByKey[$userId][$key],
						'userId' => $userId,
						'revIds' => $pageRevIds,
						'maxTimestamp' => $maxTimestamps[$userId][$key],
						'minTimestamp' => $minTimestamps[$userId][$key],
					];
				}
			}
		}
	}

	/**
	 * @param UserIdentity[] $users The accounts in the case.
	 * @param array<string,array<int,int[]>> $pageEditorRevIds Revision IDs per page-editor pair.
	 * @param array<string,array<int,string>> $pageEditorMinTs Min edit timestamp per page-editor pair.
	 * @param array<string,array<int,string>> $pageEditorMaxTs Max edit timestamp per page-editor pair.
	 * @param array<string,PageIdentity> $pageIdentities
	 * @param array<int,UserIdentity> $userIdentities
	 * @return SuggestedInvestigationsSharedPagesSummary
	 */
	private function buildSummaryForCase(
		array $users,
		array $pageEditorRevIds,
		array $pageEditorMinTs,
		array $pageEditorMaxTs,
		array $pageIdentities,
		array $userIdentities,
	): SuggestedInvestigationsSharedPagesSummary {
		$caseUserIds = [];
		foreach ( $users as $user ) {
			$caseUserIds[$user->getId()] = true;
		}

		if ( count( $caseUserIds ) < 2 ) {
			return new SuggestedInvestigationsSharedPagesSummary();
		}

		$revisionIds = [];
		$sharedPages = [];
		$commonEditors = [];
		// The earliest and latest edit timestamps across all shared-page editor pairs in this case.
		$caseMinTimestamp = null;
		$caseMaxTimestamp = null;
		foreach ( $pageEditorRevIds as $pageKey => $revIdsByUser ) {
			$revIdsByCaseUsers = array_intersect_key( $revIdsByUser, $caseUserIds );
			if ( count( $revIdsByCaseUsers ) < 2 ) {
				continue;
			}

			foreach ( $revIdsByCaseUsers as $userId => $userRevIds ) {
				$revisionIds = array_merge( $revisionIds, $userRevIds );
				$commonEditors[$userId] = $userIdentities[$userId];
			}
			$sharedPages[] = $pageIdentities[$pageKey];

			// Reduce the min/max timestamp to min and max for this page; then aggregate it with case-level data
			$pageMinTimestamp = min( array_intersect_key( $pageEditorMinTs[$pageKey], $caseUserIds ) );
			$pageMaxTimestamp = max( array_intersect_key( $pageEditorMaxTs[$pageKey], $caseUserIds ) );

			if ( !$caseMinTimestamp || $caseMinTimestamp > $pageMinTimestamp ) {
				$caseMinTimestamp = $pageMinTimestamp;
			}
			if ( !$caseMaxTimestamp || $caseMaxTimestamp < $pageMaxTimestamp ) {
				$caseMaxTimestamp = $pageMaxTimestamp;
			}
		}

		return new SuggestedInvestigationsSharedPagesSummary(
			$revisionIds,
			$sharedPages,
			$caseMinTimestamp,
			$caseMaxTimestamp,
			array_values( $commonEditors ),
		);
	}
}
