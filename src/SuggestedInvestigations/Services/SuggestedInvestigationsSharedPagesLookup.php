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
		$pageEditorMinTs = [];
		$pageEditorMaxTs = [];
		$pageIdentities = [];
		foreach ( [ $undeletedPages, $deletedPages ] as $iterable ) {
			foreach ( $iterable as $entry ) {
				/** @var PageIdentity $pageIdentity */
				$pageIdentity = $entry['page'];
				$userId = $entry['userId'];
				$numEdits = $entry['edits'];
				$minTimestamp = $entry['minTimestamp'];
				$maxTimestamp = $entry['maxTimestamp'];

				$key = $pageIdentity->getNamespace() . ':' . $pageIdentity->getDBkey();
				if ( !isset( $pageIdentities[$key] ) || $pageIdentity->getId() > 0 ) {
					// Record page identities for a given page, but prefer existing pages (with page_id)
					$pageIdentities[$key] = $pageIdentity;
				}

				if ( !isset( $pageEditors[$key][$userId] ) ) {
					$pageEditors[$key][$userId] = 0;
					$pageEditorMinTs[$key][$userId] = $minTimestamp;
					$pageEditorMaxTs[$key][$userId] = $maxTimestamp;
				} else {
					// A page-editor pair may appear in both the undeleted and deleted edits (the
					// page was partially deleted), so combine the timestamp ranges.
					// @phan-suppress-next-line PhanTypeInvalidDimOffset Phan doesn't know it's already initialized
					$pageEditorMinTs[$key][$userId] = min( $pageEditorMinTs[$key][$userId], $minTimestamp );
					// @phan-suppress-next-line PhanTypeInvalidDimOffset as above
					$pageEditorMaxTs[$key][$userId] = max( $pageEditorMaxTs[$key][$userId], $maxTimestamp );
				}
				$pageEditors[$key][$userId] += $numEdits;
			}
		}

		return array_map(
			function ( $users ) use ( $pageEditors, $pageEditorMinTs, $pageEditorMaxTs, $pageIdentities ) {
				return $this->buildSummaryForCase(
					$users,
					$pageEditors,
					$pageEditorMinTs,
					$pageEditorMaxTs,
					$pageIdentities
				);
			},
			$caseIdToUsers
		);
	}

	/**
	 * @return iterable<array> Each returned array has the following keys:
	 *   - 'page', a PageIdentity of the edited page.
	 *   - 'userId', int, id of the user.
	 *   - 'edits', int, number of edits by the user on that page.
	 *   - 'maxTimestamp', string, when the most recent edit by that user on the page occurred.
	 *   - 'minTimestamp', string, when the oldest edit by that user on the page occurred.
	 */
	private function getUndeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		// Count edits on pages user by user (and also record min/max timestamps)
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$pagesById = [];
			$editCounts = [];
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
				if ( !isset( $editCounts[$userId][$pageId] ) ) {
					$editCounts[$userId][$pageId] = 0;
				}
				$editCounts[$userId][$pageId]++;

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
			foreach ( $editCounts as $userId => $userEditCounts ) {
				foreach ( $userEditCounts as $pageId => $numEdits ) {
					yield [
						'page' => $pagesById[$userId][$pageId],
						'userId' => $userId,
						'edits' => $numEdits,
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
	 *   - 'edits', int, number of edits by the user on that page.
	 *   - 'maxTimestamp', string, when the most recent edit by that user on the page occurred.
	 *   - 'minTimestamp', string, when the oldest edit by that user on the page occurred.
	 */
	private function getDeletedPagesEditedByUsers( array $userIds ): iterable {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$cutoff = $dbr->timestamp( ConvertibleTimestamp::time() - $this->maxDataAgeSeconds );

		// Count edits on pages user by user (and also record min/max timestamps). Archived
		// revisions have unreliable page ids (often 0), so aggregate by namespace and title.
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$pagesByKey = [];
			$editCounts = [];
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
				if ( !isset( $editCounts[$userId][$key] ) ) {
					$editCounts[$userId][$key] = 0;
				}
				$editCounts[$userId][$key]++;

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
			foreach ( $editCounts as $userId => $userEditCounts ) {
				foreach ( $userEditCounts as $key => $numEdits ) {
					yield [
						'page' => $pagesByKey[$userId][$key],
						'userId' => $userId,
						'edits' => $numEdits,
						'maxTimestamp' => $maxTimestamps[$userId][$key],
						'minTimestamp' => $minTimestamps[$userId][$key],
					];
				}
			}
		}
	}

	/**
	 * @param UserIdentity[] $users The accounts in the case.
	 * @param array<string,array<int,int>> $pageEditors
	 * @param array<string,array<int,string>> $pageEditorMinTs Min edit timestamp per page-editor pair.
	 * @param array<string,array<int,string>> $pageEditorMaxTs Max edit timestamp per page-editor pair.
	 * @param array<string,PageIdentity> $pageIdentities
	 * @return SuggestedInvestigationsSharedPagesSummary
	 */
	private function buildSummaryForCase(
		array $users,
		array $pageEditors,
		array $pageEditorMinTs,
		array $pageEditorMaxTs,
		array $pageIdentities
	): SuggestedInvestigationsSharedPagesSummary {
		$caseUserIds = [];
		foreach ( $users as $user ) {
			$caseUserIds[$user->getId()] = true;
		}

		if ( count( $caseUserIds ) < 2 ) {
			return new SuggestedInvestigationsSharedPagesSummary( 0 );
		}

		$editCount = 0;
		$sharedPages = [];
		// The earliest and latest edit timestamps across all shared-page editor pairs in this case.
		$caseMinTimestamp = null;
		$caseMaxTimestamp = null;
		foreach ( $pageEditors as $pageKey => $editCounts ) {
			$editsByCaseUsers = array_intersect_key( $editCounts, $caseUserIds );
			if ( count( $editsByCaseUsers ) < 2 ) {
				continue;
			}

			$editCount += array_sum( $editsByCaseUsers );
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
			$editCount,
			$sharedPages,
			$caseMinTimestamp,
			$caseMaxTimestamp
		);
	}
}
