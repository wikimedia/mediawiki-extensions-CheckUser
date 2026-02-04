<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Unit\Jobs;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseJobTest extends MediaWikiUnitTestCase {

	private const CASE_ID = 123;

	public function testNewSpecWithDelayedJobsEnabled(): void {
		$spec = SuggestedInvestigationsAutoCloseJob::newSpec( self::CASE_ID, true );

		$this->assertSame( SuggestedInvestigationsAutoCloseJob::TYPE, $spec->getType() );
		$this->assertSame( self::CASE_ID, $spec->getParams()['caseId'] );
		$this->assertArrayHasKey( 'jobReleaseTimestamp', $spec->getParams() );
	}

	public function testNewSpecWithDelayedJobsDisabled(): void {
		$spec = SuggestedInvestigationsAutoCloseJob::newSpec( self::CASE_ID, false );

		$this->assertSame( SuggestedInvestigationsAutoCloseJob::TYPE, $spec->getType() );
		$this->assertSame( self::CASE_ID, $spec->getParams()['caseId'] );
		$this->assertArrayNotHasKey( 'jobReleaseTimestamp', $spec->getParams() );
	}

	public function testClosedCaseEarlyReturn(): void {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Resolved );

		$caseLookUpMock->expects( $this->never() )
			->method( 'getUserIdsInCase' );

		$job = $this->createJobWithMocks(
			$this->getCaseManagerMockIsNeverCalled(),
			$caseLookUpMock
		);

		$job->run();
	}

	public function testCaseWithNoUsers(): void {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Open );

		$caseLookUpMock->expects( $this->once() )
			->method( 'getUserIdsInCase' )
			->with( self::CASE_ID )
			->willReturn( [] );

		$job = $this->createJobWithMocks(
			$this->getCaseManagerMockIsNeverCalled(),
			$caseLookUpMock
		);

		$job->run();
	}

	public function testCaseWithUsersNotAllIndefinitelySitewideBlocked(): void {
		$caseLookUpMock = $this->getCaseLookUpMockFound();

		$blockStoreMock = $this->createMock( DatabaseBlockStore::class );
		$blockStoreMock->expects( $this->once() )
			->method( 'newListFromConds' )
			->willReturn( [] );

		$job = $this->createJobWithMocks(
			$this->getCaseManagerMockIsNeverCalled(),
			$caseLookUpMock,
			$blockStoreMock
		);

		$job->run();
	}

	public function testCaseAutoClosedOk(): void {
		$caseLookUpMock = $this->getCaseLookUpMockFound();

		$block1 = $this->createBlockMockWithUserId( 1 );
		$block2 = $this->createBlockMockWithUserId( 2 );

		$blockStoreMock = $this->createMock( DatabaseBlockStore::class );
		$blockStoreMock->expects( $this->once() )
			->method( 'newListFromConds' )
			->willReturn( [ $block1, $block2 ] );

		$caseManagerMock = $this->createMock( SuggestedInvestigationsCaseManagerService::class );
		$caseManagerMock->expects( $this->once() )
			->method( 'setCaseStatus' )
			->with( self::CASE_ID, CaseStatus::Resolved, $this->isType( 'string' ) );

		$job = $this->createJobWithMocks(
			$caseManagerMock,
			$caseLookUpMock,
			$blockStoreMock
		);

		$this->assertTrue( $job->run() );
	}

	private function getCaseManagerMockIsNeverCalled(): SuggestedInvestigationsCaseManagerService {
		$caseManagerMock = $this->createMock( SuggestedInvestigationsCaseManagerService::class );
		$caseManagerMock->expects( $this->never() )
			->method( 'setCaseStatus' );
		return $caseManagerMock;
	}

	private function getCaseLookUpMockFound(): SuggestedInvestigationsCaseLookupService&MockObject {
		$caseLookUpMock = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$caseLookUpMock->expects( $this->once() )
			->method( 'getCaseStatus' )
			->with( self::CASE_ID )
			->willReturn( CaseStatus::Open );

		$caseLookUpMock->expects( $this->once() )
			->method( 'getUserIdsInCase' )
			->with( self::CASE_ID )
			->willReturn( [ 1, 2 ] );
		return $caseLookUpMock;
	}

	private function createJobWithMocks(
		SuggestedInvestigationsCaseManagerService $caseManager,
		SuggestedInvestigationsCaseLookupService $caseLookup,
		?DatabaseBlockStore $blockStore = null
	): SuggestedInvestigationsAutoCloseJob {
		return new SuggestedInvestigationsAutoCloseJob(
			[ 'caseId' => self::CASE_ID ],
			$caseManager,
			$caseLookup,
			$blockStore ?? $this->createMock( DatabaseBlockStore::class ),
			$this->createMock( LoggerInterface::class ),
			new FakeQqxMessageLocalizer()
		);
	}

	private function createBlockMockWithUserId( int $userId ): DatabaseBlock&MockObject {
		$userIdentity = $this->createMock( UserIdentity::class );
		$userIdentity->expects( $this->once() )->method( 'getId' )->willReturn( $userId );

		$block = $this->createMock( DatabaseBlock::class );
		$block->expects( $this->once() )->method( 'getTargetUserIdentity' )->willReturn( $userIdentity );
		$block->expects( $this->once() )->method( 'isSitewide' )->willReturn( true );
		$block->expects( $this->once() )->method( 'isIndefinite' )->willReturn( true );

		return $block;
	}
}
