<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiIntegrationTestCase {
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private SpecialPageInitListHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );

		$this->handler = new SpecialPageInitListHandler(
			$this->tempUserConfig,
			$this->extensionRegistry
		);
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

	/**
	 * @dataProvider provideRegisterSpecialGlobalContributions
	 */
	public function testRegisterSpecialGlobalContributions( $globalPreferencesIsLoaded ): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( true );
		$this->extensionRegistry->method( 'isLoaded' )
			->willReturn( $globalPreferencesIsLoaded );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		if ( $globalPreferencesIsLoaded ) {
			$this->assertArrayHasKey( 'GlobalContributions', $list );
		} else {
			$this->assertArrayNotHasKey( 'GlobalContributions', $list );
		}
	}

	public function provideRegisterSpecialGlobalContributions(): array {
		return [
			'Page is added when dependencies are loaded' => [ true ],
			'Page is not added when dependencies are not loaded' => [ false ],
		];
	}
}
