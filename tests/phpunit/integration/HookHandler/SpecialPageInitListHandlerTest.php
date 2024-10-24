<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiIntegrationTestCase {
	private TempUserConfig $tempUserConfig;

	private SpecialPageInitListHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->handler = new SpecialPageInitListHandler( $this->tempUserConfig );
	}

	public function testShouldDoNothingWhenTempUsersAreNotKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( false );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertSame( [], $list );
	}

	public function testShouldRegisterSpecialIPContributionsIfTempUsersAreKnown(): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( true );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		$this->assertArrayHasKey( 'IPContributions', $list );
	}
}
