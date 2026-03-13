<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\SuggestedInvestigationsNoticeRenderer;
use MediaWiki\Linker\LinkRenderer;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\SuggestedInvestigationsNoticeRenderer
 */
class SuggestedInvestigationsNoticeRendererTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService $caseLookupService;
	private SuggestedInvestigationsNoticeRenderer $renderer;

	private MockObject $checkUserGetUsersPager;

	protected function setUp(): void {
		parent::setUp();

		$this->caseLookupService = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->renderer = new SuggestedInvestigationsNoticeRenderer( $this->caseLookupService );
		$this->checkUserGetUsersPager = $this->createMock( CheckUserGetUsersPager::class );
	}

	public function testReturnsEmptyStringWhenPagerDoesNotImplementCheckUsernameResultInterface(): void {
		$pager = $this->createMock( AbstractCheckUserPager::class );
		$this->caseLookupService->expects( $this->never() )
			->method( 'areSuggestedInvestigationsEnabled' );

		$this->assertSame(
			'',
			$this->renderer->getNotice(
				$pager,
				$this->createMock( IContextSource::class ),
				$this->createMock( LinkRenderer::class )
			)
		);
	}

	public function testReturnsEmptyStringWhenSuggestedInvestigationsDisabled(): void {
		$this->caseLookupService->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( false );
		$this->caseLookupService->expects( $this->never() )
			->method( 'getUserIdsWithCases' );

		$this->assertSame(
			'',
			$this->renderer->getNotice(
				$this->checkUserGetUsersPager,
				$this->createMock( IContextSource::class ),
				$this->createMock( LinkRenderer::class )
			)
		);
	}

	public function testReturnsEmptyStringWhenResultUsernameMapIsEmpty(): void {
		$this->checkUserGetUsersPager->expects( $this->once() )
			->method( 'getResultUsernameMap' )
			->willReturn( [] );
		$this->caseLookupService->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( true );
		$this->caseLookupService->expects( $this->never() )
			->method( 'getUserIdsWithCases' );

		$this->assertSame(
			'',
			$this->renderer->getNotice(
				$this->checkUserGetUsersPager,
				$this->createMock( IContextSource::class ),
				$this->createMock( LinkRenderer::class )
			)
		);
	}

	public function testReturnsEmptyStringWhenNoUsersHaveOpenCases(): void {
		$this->checkUserGetUsersPager->expects( $this->once() )
			->method( 'getResultUsernameMap' )
			->willReturn( [ 1 => 'User1' ] );
		$this->caseLookupService->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( true );
		$this->caseLookupService->expects( $this->once() )
			->method( 'getUserIdsWithCases' )
			->willReturn( [] );

		$this->assertSame(
			'',
			$this->renderer->getNotice(
				$this->checkUserGetUsersPager,
				$this->createMock( IContextSource::class ),
				$this->createMock( LinkRenderer::class )
			)
		);
	}

}
