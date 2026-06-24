<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSharedPagesLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSharedPagesLookup
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsSharedPagesSummary
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsSharedPagesLookupTest extends MediaWikiIntegrationTestCase {

	private SuggestedInvestigationsSharedPagesLookup $lookup;

	protected function setUp(): void {
		parent::setUp();

		$this->lookup = $this->getServiceContainer()
			->get( 'CheckUserSuggestedInvestigationsSharedPagesLookup' );
	}

	public function testEmptyInputReturnsEmptyArray(): void {
		$this->assertSame( [], $this->lookup->getSharedPagesForCases( [] ) );
	}

	public function testCaseWithSingleUserHasNoSharedPages(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->editPage( 'SoloOnlyPage', 'a', '', NS_MAIN, $user );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $user ] ] )[1];

		$this->assertSame( [], $summary->getSharedPages() );
		$this->assertSame( [], $summary->getRevisionIds() );
		// With no shared pages there is no edit window to report.
		$this->assertNull( $summary->getFirstEditTimestamp() );
		$this->assertNull( $summary->getLastEditTimestamp() );
	}

	public function testPageEditedByTwoUsersIsShared(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		// "SharedPage" is edited by A (twice) and B (once); "SoloPage" only by A.
		$sharedRevIds = [];
		$sharedRevIds[] = $this->editPage( 'SharedPage', 'a1', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$sharedRevIds[] = $this->editPage( 'SharedPage', 'a2', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$sharedRevIds[] = $this->editPage( 'SharedPage', 'b1', '', NS_MAIN, $userB )->getNewRevision()->getId();
		$this->editPage( 'SoloPage', 'a', '', NS_MAIN, $userA );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		// Only "SharedPage" qualifies; all 3 edits on it are returned
		$this->assertCount( 1, $summary->getSharedPages() );
		$this->assertArrayEquals( $sharedRevIds, $summary->getRevisionIds() );
	}

	public function testPageCountedOnlyForCasesWhoseUsersShareIt(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();
		$userC = $this->getMutableTestUser()->getUser();

		// "GlobalPage" is edited by A and C, but not B.
		$revA = $this->editPage( 'GlobalPage', 'a', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$revC = $this->editPage( 'GlobalPage', 'c', '', NS_MAIN, $userC )->getNewRevision()->getId();

		$summaries = $this->lookup->getSharedPagesForCases( [
			1 => [ $userA, $userB ],
			2 => [ $userA, $userC ],
		] );

		// Case 1 (A, B): only A edited the page, so it is not shared within the case.
		$this->assertSame( [], $summaries[1]->getRevisionIds() );
		// Case 2 (A, C): both edited the page, one edit each.
		$this->assertCount( 1, $summaries[2]->getSharedPages() );
		$this->assertArrayEquals( [ $revA, $revC ], $summaries[2]->getRevisionIds() );
	}

	public function testEditsOnDeletedPagesAreCounted(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		// The original rev_id is preserved as ar_rev_id when a page is deleted, so capturing the
		// IDs before deletion lets us assert they are still reported afterward.
		$revIds = [];
		$revIds[] = $this->editPage( 'DeletedSharedPage', 'a', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$revIds[] = $this->editPage( 'DeletedSharedPage', 'b', '', NS_MAIN, $userB )->getNewRevision()->getId();
		$this->deletePage( 'DeletedSharedPage' );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		// The edits now live in the archive table but must still be detected.
		$this->assertCount( 1, $summary->getSharedPages() );
		$this->assertArrayEquals( $revIds, $summary->getRevisionIds() );
	}

	public function testLiveAndDeletedEditsOnSameTitleAreMerged(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		// A and B edit the title, the page is deleted (both edits archived),
		// then A recreates the page (a live edit on the same title).
		$revIds = [];
		$revIds[] = $this->editPage( 'MergeTitlePage', 'a1', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$revIds[] = $this->editPage( 'MergeTitlePage', 'b1', '', NS_MAIN, $userB )->getNewRevision()->getId();
		$this->deletePage( 'MergeTitlePage' );
		$revIds[] = $this->editPage( 'MergeTitlePage', 'a2', '', NS_MAIN, $userA )->getNewRevision()->getId();

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		// Live and archived edits to the same title count as one shared page,
		// and all three edits (A twice, B once) are returned.
		$this->assertCount( 1, $summary->getSharedPages() );
		$this->assertArrayEquals( $revIds, $summary->getRevisionIds() );
	}

	public function testEditsOlderThanMaxAgeAreExcluded(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		// Edits made long before the CUDMaxAge window must be ignored...
		ConvertibleTimestamp::setFakeTime( '20000101000000' );
		$this->editPage( 'OldSharedPage', 'a', '', NS_MAIN, $userA );
		$this->editPage( 'OldSharedPage', 'b', '', NS_MAIN, $userB );

		// ...while a recent shared page is still counted.
		ConvertibleTimestamp::setFakeTime( false );
		$recentRevIds = [];
		$recentRevIds[] = $this->editPage( 'RecentSharedPage', 'a', '', NS_MAIN, $userA )->getNewRevision()->getId();
		$recentRevIds[] = $this->editPage( 'RecentSharedPage', 'b', '', NS_MAIN, $userB )->getNewRevision()->getId();

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		$this->assertCount( 1, $summary->getSharedPages() );
		$this->assertArrayEquals( $recentRevIds, $summary->getRevisionIds() );
	}

	public function testFirstAndLastEditTimestampsSpanAllSharedPages(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		$revIds = [];
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$revIds[] = $this->editPage( 'TimestampPageOne', 'a', '', NS_MAIN, $userA )->getNewRevision()->getId();
		ConvertibleTimestamp::setFakeTime( '20260101000100' );
		$revIds[] = $this->editPage( 'TimestampPageOne', 'b', '', NS_MAIN, $userB )->getNewRevision()->getId();
		ConvertibleTimestamp::setFakeTime( '20260101000200' );
		$revIds[] = $this->editPage( 'TimestampPageTwo', 'a', '', NS_MAIN, $userA )->getNewRevision()->getId();
		ConvertibleTimestamp::setFakeTime( '20260101000300' );
		$revIds[] = $this->editPage( 'TimestampPageTwo', 'b', '', NS_MAIN, $userB )->getNewRevision()->getId();

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		$this->assertCount( 2, $summary->getSharedPages() );
		$this->assertArrayEquals( $revIds, $summary->getRevisionIds() );
		$this->assertSame( '20260101000000', $summary->getFirstEditTimestamp() );
		$this->assertSame( '20260101000300', $summary->getLastEditTimestamp() );
	}

	public function testCommonEditorsAreUsersWhoEditedSharedPages(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		$this->editPage( 'CommonEditorsPage', 'a', '', NS_MAIN, $userA );
		$this->editPage( 'CommonEditorsPage', 'b', '', NS_MAIN, $userB );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		$this->assertArrayEquals(
			[ $userA, $userB ],
			$summary->getCommonEditors()
		);
	}

	public function testCommonEditorsExcludeUsersWithoutSharedEdits(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();
		$userC = $this->getMutableTestUser()->getUser();

		// A and B share a page; C only edits a page on their own.
		$this->editPage( 'SharedByABPage', 'a', '', NS_MAIN, $userA );
		$this->editPage( 'SharedByABPage', 'b', '', NS_MAIN, $userB );
		$this->editPage( 'SoloByCPage', 'c', '', NS_MAIN, $userC );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB, $userC ] ] )[1];

		$this->assertArrayEquals(
			[ $userA, $userB ],
			$summary->getCommonEditors()
		);
	}

	public function testCommonEditorsAreDeduplicatedAcrossPages(): void {
		$userA = $this->getMutableTestUser()->getUser();
		$userB = $this->getMutableTestUser()->getUser();

		// A and B share two different pages.
		$this->editPage( 'SharedPageOne', 'a', '', NS_MAIN, $userA );
		$this->editPage( 'SharedPageOne', 'b', '', NS_MAIN, $userB );
		$this->editPage( 'SharedPageTwo', 'a', '', NS_MAIN, $userA );
		$this->editPage( 'SharedPageTwo', 'b', '', NS_MAIN, $userB );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $userA, $userB ] ] )[1];

		$this->assertCount( 2, $summary->getSharedPages() );
		$this->assertArrayEquals(
			[ $userA, $userB ],
			$summary->getCommonEditors()
		);
	}

	public function testNoSharedPagesMeansNoCommonEditors(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->editPage( 'NoSharedEditorsPage', 'a', '', NS_MAIN, $user );

		$summary = $this->lookup->getSharedPagesForCases( [ 1 => [ $user ] ] )[1];

		$this->assertSame( [], $summary->getCommonEditors() );
	}
}
