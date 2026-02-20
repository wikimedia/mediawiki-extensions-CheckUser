<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalBlockHandler;
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
	private SuggestedInvestigationsAutoCloseOnGlobalBlockHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalBlock::class ) ) {
			$this->markTestSkipped( 'GlobalBlocking extension is not available' );
		}

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->handler = new SuggestedInvestigationsAutoCloseOnGlobalBlockHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger()
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [ false, 1, true ];
		yield 'block target is null' => [ true, null, true ];
		yield 'block is not indefinite' => [ true, 1, false ];
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
