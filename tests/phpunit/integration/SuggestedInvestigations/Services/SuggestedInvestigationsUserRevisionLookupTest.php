<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsUserRevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsUserRevisionLookup
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsUserRevisionLookupTest extends MediaWikiIntegrationTestCase {

	private SuggestedInvestigationsUserRevisionLookup $lookup;

	protected function setUp(): void {
		parent::setUp();

		$this->lookup = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsUserRevisionLookup' );
	}

	public function testReturnsFalseWhenUserHasNoRevisions(): void {
		$user = new UserIdentityValue( 0, 'NonExistentTestUser' );

		$this->assertFalse( $this->lookup->isFirstEditByUser( $user, 42 ) );
	}

	public function testReturnsTrueWhenRevisionIsUsersFirstEdit(): void {
		$user = $this->getTestUser()->getUser();
		$status = $this->editPage( 'RevLookupTestPage', 'content', '', NS_MAIN, $user );
		$revId = $status->getNewRevision()->getId();

		$this->assertTrue( $this->lookup->isFirstEditByUser( $user, $revId ) );
	}

	public function testReturnsFalseWhenRevisionIsNotUsersFirstEdit(): void {
		$user = $this->getTestUser()->getUser();
		$this->editPage( 'RevLookupFirstPage', 'content', '', NS_MAIN, $user );
		$status = $this->editPage( 'RevLookupSecondPage', 'content', '', NS_MAIN, $user );
		$secondRevId = $status->getNewRevision()->getId();

		$this->assertFalse( $this->lookup->isFirstEditByUser( $user, $secondRevId ) );
	}

	public function testReturnsFalseWhenUsersOnlyPriorRevisionIsArchived(): void {
		$user = $this->getTestUser()->getUser();
		$firstEdit = $this->editPage( 'RevLookupArchivedPage', 'content', '', NS_MAIN, $user );
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( $firstEdit->getNewRevision()->getPage() );
		$this->deletePage( $page );
		$secondEdit = $this->editPage( 'RevLookupAfterDeletePage', 'content', '', NS_MAIN, $user );
		$secondRevId = $secondEdit->getNewRevision()->getId();

		$this->assertFalse( $this->lookup->isFirstEditByUser( $user, $secondRevId ) );
	}

	private function getRevertedRevisionsTestUsers(): array {
		$userWithReverts = $this->getMutableTestUser()->getUser();
		$userWithDeletedReverts = $this->getMutableTestUser()->getUser();
		$userWithoutReverts = $this->getTestSysop()->getUser();

		// Reverted revision
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $userWithReverts );
		$revertedEditId = $this
			->editPage( 'Foo', 'Foo Bar', '', NS_MAIN, $userWithReverts )
			->getNewRevision()
			->getId();
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $userWithoutReverts );

		// Mock that the edit has been marked as reverted, which usually happens via job
		$rcId = null;
		$this->getServiceContainer()->getChangeTagsStore()
			->updateTags( [ 'mw-reverted' ], [], $rcId, $revertedEditId );

		// Deleted/Reverted revision
		$this->editPage( 'Foo2', 'Foo', '', NS_MAIN, $userWithDeletedReverts );
		$revertedDeletedEditId = $this
			->editPage( 'Foo2', 'Foo Bar', '', NS_MAIN, $userWithDeletedReverts )
			->getNewRevision()
			->getId();
		$this->editPage( 'Foo2', 'Foo', '', NS_MAIN, $userWithoutReverts );

		// Mock that the edit has been marked as reverted, which usually happens via job
		$rcId = null;
		$this->getServiceContainer()->getChangeTagsStore()
			->updateTags( [ 'mw-reverted' ], [], $rcId, $revertedDeletedEditId );

		// Delete the page, creating the reverted/deleted edit
		$deletePage = $this->getServiceContainer()
			->getDeletePageFactory()
			->newDeletePage(
				Title::makeTitle( NS_MAIN, 'Foo2' )->toPageIdentity(),
				$userWithoutReverts
			)
			->forceImmediate( true );
		$deletePage->deleteUnsafe( 'Force delete' );

		return [ $userWithReverts, $userWithDeletedReverts ];
	}

	public function testGetRevertedRevisionCountsByUsersForCases(): void {
		[ $userWithReverts, $userWithDeletedReverts ] = $this->getRevertedRevisionsTestUsers();
		$userWithRevertsId = $userWithReverts->getId();
		$userWithDeletedRevertsId = $userWithDeletedReverts->getId();

		// Assert that the user with deleted reverts has the expected total revision counts and split counts
		$revisionCountsSummaryBasic = $this->lookup->getRevertedRevisionCountsByUsersForCases( [
			1 => [ $userWithDeletedReverts ],
		] );
		$this->assertCount( 1, $revisionCountsSummaryBasic );
		$this->assertSame( 1, $revisionCountsSummaryBasic[ 1 ]->getRevertedRevisionsCount() );
		$this->assertSame( 2, $revisionCountsSummaryBasic[ 1 ]->getTotalRevisionsCount() );
		$revisionCountsBasic = $this->lookup->getAllRevisionCountsByUsers(
			[ $userWithDeletedRevertsId ]
		);
		$this->assertCount( 1, $revisionCountsBasic );
		$this->assertSame( 0, $revisionCountsBasic[ $userWithDeletedRevertsId ][ 'reverted' ] );
		$this->assertSame( 0, $revisionCountsBasic[ $userWithDeletedRevertsId ][ 'total' ] );
		$deletedRevisionCountsBasic = $this->lookup->getAllRevisionCountsByUsers(
			[ $userWithDeletedRevertsId ],
			true
		);
		$this->assertCount( 1, $deletedRevisionCountsBasic );
		$this->assertSame( 1, $deletedRevisionCountsBasic[ $userWithDeletedRevertsId ][ 'reverted' ] );
		$this->assertSame( 2, $deletedRevisionCountsBasic[ $userWithDeletedRevertsId ][ 'total' ] );

		// Assert that the user with no deleted reverts has the expected visible revisions count
		$revisionsCountVisible = $this->lookup->getAllRevisionCountsByUsers(
			[ $userWithRevertsId ]
		);
		$this->assertCount( 1, $revisionsCountVisible );
		$this->assertSame( 1, $revisionsCountVisible[ $userWithRevertsId ][ 'reverted' ] );
		$this->assertSame( 2, $revisionsCountVisible[ $userWithRevertsId ][ 'total' ] );

		// Delete the page the user with no deleted revisions edited, archiving those revisions
		$this->deletePage( 'Foo' );

		// Assert that the revisions counts for the user remain the same, as they've been cached
		$revisionsCountVisiblePostDelete = $this->lookup->getAllRevisionCountsByUsers(
			[ $userWithRevertsId ]
		);
		$this->assertCount( 1, $revisionsCountVisiblePostDelete );
		$this->assertSame( 1, $revisionsCountVisiblePostDelete[ $userWithRevertsId ][ 'reverted' ] );
		$this->assertSame( 2, $revisionsCountVisiblePostDelete[ $userWithRevertsId ][ 'total' ] );

		// // Assert that the deleted revisions count for this user is updated, as that function hasn't been called yet
		$deletedRevisionsCountPostDelete = $this->lookup->getAllRevisionCountsByUsers(
			[ $userWithRevertsId ],
			true
		);
		$this->assertCount( 1, $deletedRevisionsCountPostDelete );
		$this->assertSame( 1, $deletedRevisionsCountPostDelete[ $userWithRevertsId ][ 'reverted' ] );
		$this->assertSame( 2, $deletedRevisionsCountPostDelete[ $userWithRevertsId ][ 'total' ] );
	}

	public function testGetDeletedRevisionCountsByUsersForCases(): void {
		[ $userWithReverts, $userWithDeletedReverts ] = $this->getRevertedRevisionsTestUsers();
		$deletedRevisionCountsSummary = $this->lookup->getDeletedRevisionCountsByUsersForCases( [
			1 => [ $userWithDeletedReverts ],
		] );
		$this->assertCount( 1, $deletedRevisionCountsSummary );
		$this->assertSame( 2, $deletedRevisionCountsSummary[ 1 ]->getTotalDeletedRevisionsCount() );

		$noDeletedRevisionCountsSummary = $this->lookup->getDeletedRevisionCountsByUsersForCases( [
			1 => [ $userWithReverts ],
		] );
		$this->assertCount( 1, $noDeletedRevisionCountsSummary );
		$this->assertSame( 0, $noDeletedRevisionCountsSummary[ 1 ]->getTotalDeletedRevisionsCount() );
	}
}
