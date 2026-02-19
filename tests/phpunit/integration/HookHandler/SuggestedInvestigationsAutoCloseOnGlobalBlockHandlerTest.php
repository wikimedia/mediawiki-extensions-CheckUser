<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob;
// phpcs:ignore Generic.Files.LineLength.TooLong
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseOnGlobalBlockHandlerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private JobQueueGroup $jobQueueGroup;
	private GlobalBlockManager $globalBlockManager;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$this->enableSuggestedInvestigations();
		$this->jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$this->globalBlockManager = GlobalBlockingServices::wrap( $this->getServiceContainer() )
			->getGlobalBlockManager();
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->setService( 'CheckUserCrossWikiAutoCloseJobDispatcher', $this->crossWikiJobDispatcher );
	}

	public function testJobPushedForOpenCase(): void {
		$testUser = $this->getMutableTestUser()->getUserIdentity();
		$this->createCaseForUser( $testUser );

		$this->crossWikiJobDispatcher
			->expects( $this->once() )
			->method( 'dispatch' )
			->with( $testUser->getName() );

		// this will trigger SuggestedInvestigationsAutoCloseOnGlobalBlockHandler::onGlobalBlockingGlobalBlockAudit
		$this->globalBlockManager->block(
			$testUser->getName(),
			'test global block',
			'infinity',
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame(
			1,
			$this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseJob::TYPE )->getSize(),
			'A job should be pushed for an open case'
		);
	}

	private function createCaseForUser( UserIdentity $user ): int {
		return $this->getServiceContainer()
			->getService( 'CheckUserSuggestedInvestigationsCaseManager' )
			->createCase(
				[ $user ],
				[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'TestSignal', 'value', false ) ]
			);
	}
}
