<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Integration\Jobs;

use MediaWiki\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseJobTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private SuggestedInvestigationsCaseManagerService $caseManager;

	public function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->caseManager = $this->getServiceContainer()
			->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
	}

	public function testRunForEmptyCase(): void {
		$caseId = $this->caseManager->createCase(
			[ UserIdentityValue::newRegistered( 1, 'User1' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Signal', 'value', false ) ]
		);

		// Remove users from the case to simulate an empty case
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();

		$job = $this->getJob( $caseId );
		$this->assertTrue( $job->run() );

		$this->assertEquals( (string)CaseStatus::Open->value, $this->getCaseStatus( $caseId ),
			'Case should remain open when it has no users' );
	}

	public function testRunDoesNotCloseWhenNotAllUsersBlocked(): void {
		$user1 = $this->getMutableTestUser()->getUserIdentity();
		$user2 = $this->getMutableTestUser()->getUserIdentity();

		$caseId = $this->caseManager->createCase(
			[ $user1, $user2 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Signal', 'value', false ) ]
		);

		// Only block one user
		$this->blockUserIndefinitely( $user1 );

		$job = $this->getJob( $caseId );
		$this->assertTrue( $job->run() );

		$this->assertEquals( (string)CaseStatus::Open->value, $this->getCaseStatus( $caseId ),
			'Case should remain open when not all users are blocked' );
	}

	public function testRunClosesCaseWhenAllUsersBlocked(): void {
		$user1 = $this->getMutableTestUser()->getUserIdentity();
		$user2 = $this->getMutableTestUser()->getUserIdentity();

		$caseId = $this->caseManager->createCase(
			[ $user1, $user2 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Signal', 'value', false ) ]
		);

		$this->blockUserIndefinitely( $user1 );
		$this->blockUserIndefinitely( $user2 );

		$job = $this->getJob( $caseId );
		$this->assertTrue( $job->run() );

		$this->assertEquals( (string)CaseStatus::Resolved->value, $this->getCaseStatus( $caseId ),
			'Case should be resolved when all users are blocked' );
	}

	public function testNewFromGlobalState(): void {
		$job = SuggestedInvestigationsAutoCloseJob::newFromGlobalState( [ 'caseId' => 123 ] );
		$this->assertInstanceOf( SuggestedInvestigationsAutoCloseJob::class, $job );
	}

	public function testStaticFactory(): void {
		$job = SuggestedInvestigationsAutoCloseJob::newSpec( 123, true );
		$this->assertSame( SuggestedInvestigationsAutoCloseJob::TYPE, $job->getType() );
		$this->assertSame( 123, $job->getParams()['caseId'] );
		$this->assertArrayHasKey( 'jobReleaseTimestamp', $job->getParams() );
	}

	private function getJob( int $caseId ): SuggestedInvestigationsAutoCloseJob {
		$services = $this->getServiceContainer();

		return new SuggestedInvestigationsAutoCloseJob(
			[ 'caseId' => $caseId ],
			$this->caseManager,
			$services->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$services->getService( 'CheckUserCompositeIndefiniteBlockChecker' ),
			$services->getService( 'CheckUserLogger' ),
			RequestContext::getMain()
		);
	}

	private function blockUserIndefinitely( UserIdentity $user ): void {
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$block = $blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => 'infinity',
		] );
		$blockStore->insertBlock( $block );
	}

	private function getCaseStatus( int $caseId ): string|false {
		return $this->newSelectQueryBuilder()
			->select( 'sic_status' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
	}
}
