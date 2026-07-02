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

	public function testGetRevertedRevisionCountsByUsersForCases(): void {
		$user = $this->getMutableTestUser( 'User1' )->getUser();
		$sysop = $this->getTestSysop()->getUser();
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $user );
		$revertedEditId = $this->editPage( 'Foo', 'Foo Bar', '', NS_MAIN, $user )->getNewRevision()->getId();
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $sysop );

		// Mock that the edit has been marked as reverted, which usually happens via job
		$rcId = null;
		$this->getServiceContainer()->getChangeTagsStore()
			->updateTags( [ 'mw-reverted' ], [], $rcId, $revertedEditId );

		$revisionCountsSummary = $this->lookup->getRevertedRevisionCountsByUsersForCases( [
			1 => [ $user ],
		] );
		$this->assertCount( 1, $revisionCountsSummary );
		$this->assertSame( 1, $revisionCountsSummary[ 1 ]->getRevertedRevisionsCount() );
		$this->assertSame( 2, $revisionCountsSummary[ 1 ]->getTotalRevisionsCount() );

		// Assert that the summary stays the same after a page delete moves the revisions to the archive table
		$deletePage = $this->getServiceContainer()
			->getDeletePageFactory()
			->newDeletePage(
				Title::makeTitle( NS_MAIN, 'Foo' )->toPageIdentity(),
				$sysop
			)
			->forceImmediate( true );
		$deletePage->deleteUnsafe( 'Force delete' );

		$revisionCountsSummary = $this->lookup->getRevertedRevisionCountsByUsersForCases( [
			1 => [ $user ],
		] );
		$this->assertCount( 1, $revisionCountsSummary );
		$this->assertSame( 1, $revisionCountsSummary[ 1 ]->getRevertedRevisionsCount() );
		$this->assertSame( 2, $revisionCountsSummary[ 1 ]->getTotalRevisionsCount() );
	}
}
