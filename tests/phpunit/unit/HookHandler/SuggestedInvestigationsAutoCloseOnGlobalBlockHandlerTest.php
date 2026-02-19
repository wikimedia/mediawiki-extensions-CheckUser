<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler;
// phpcs:ignore Generic.Files.LineLength.TooLong
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseOnGlobalBlockHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher&MockObject $crossWikiJobDispatcher;
	private SuggestedInvestigationsAutoCloseOnGlobalBlockHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalBlock::class ) ) {
			$this->markTestSkipped( 'GlobalBlocking extension is not available' );
		}

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->crossWikiJobDispatcher = $this->createMock(
			SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher::class
		);
		$this->handler = new SuggestedInvestigationsAutoCloseOnGlobalBlockHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger(),
			$this->crossWikiJobDispatcher
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [
			'isExtensionEnabled' => false, 'targetUserId' => 1, 'isIndefinite' => true,
		];
		yield 'block target is null' => [
			'isExtensionEnabled' => true, 'targetUserId' => null, 'isIndefinite' => true,
		];
		yield 'block is not indefinite' => [
			'isExtensionEnabled' => true, 'targetUserId' => 1, 'isIndefinite' => false,
		];
		yield 'block target is an IP address' => [
			'isExtensionEnabled' => true, 'targetUserId' => 0, 'isIndefinite' => true,
		];
	}

	/**
	 * @dataProvider provideEarlyReturnCases
	 */
	public function testEarlyReturn( bool $isExtensionEnabled, int|null $targetUserId, bool $isIndefinite ): void {
		$this->caseLookup
			->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( $isExtensionEnabled );
		$this->caseLookup
			->expects( $this->never() )
			->method( 'getOpenCaseIdsForUser' );

		$this->jobQueueGroup
			->expects( $this->never() )
			->method( 'lazyPush' );

		$this->crossWikiJobDispatcher
			->expects( $this->never() )
			->method( 'dispatch' );

		$this->handler->onGlobalBlockingGlobalBlockAudit(
			$this->getGlobalBlockMock( $targetUserId, $isIndefinite )
		);
	}

	private function getGlobalBlockMock( int|null $targetUserId, bool $isIndefinite ): GlobalBlock {
		$block = $this->createMock( GlobalBlock::class );
		$block->expects( $this->atMost( 1 ) )
			->method( 'getTargetUserIdentity' )
			->willReturn( $targetUserId !== null ? new UserIdentityValue( $targetUserId, 'TestUser' ) : null );
		$block->expects( $this->atMost( 1 ) )
			->method( 'isIndefinite' )
			->willReturn( $isIndefinite );

		return $block;
	}

}
