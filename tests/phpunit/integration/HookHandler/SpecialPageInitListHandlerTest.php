<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialPageInitListHandler
 */
class SpecialPageInitListHandlerTest extends MediaWikiIntegrationTestCase {
	private Config $config;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;
	private SpecialPageInitListHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock( Config::class );
		$this->tempUserConfig = $this->createMock( TempUserConfig::class );
		$this->extensionRegistry = $this->createMock( ExtensionRegistry::class );

		$this->handler = new SpecialPageInitListHandler(
			$this->config,
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
	public function testRegisterSpecialGlobalContributions(
		$extensionsAreLoaded,
		$tempAccountsAreKnown,
		$centralWiki,
		$expectLoaded
	): void {
		$this->tempUserConfig->method( 'isKnown' )
			->willReturn( $tempAccountsAreKnown );
		$this->extensionRegistry->method( 'isLoaded' )
			->willReturn( $extensionsAreLoaded );

		$this->config->method( 'get' )
			->willReturn( $centralWiki );

		$list = [];

		$this->handler->onSpecialPage_initList( $list );

		if ( $expectLoaded ) {
			$this->assertArrayHasKey( 'GlobalContributions', $list );
		} else {
			$this->assertArrayNotHasKey( 'GlobalContributions', $list );
		}
	}

	public function provideRegisterSpecialGlobalContributions(): array {
		return [
			'Page is added when dependencies are loaded and temp accounts are known' => [
				true, true, false, true,
			],
			'Page is added when dependencies are loaded and central wiki is defined' => [
				true, false, 'somewiki', true,
			],
			'Page is not added when temp accounts are unknown and central wiki is not defined' => [
				true, false, false, false,
			],
			'Page is not added when dependencies are not loaded' => [
				false, true, 'somewiki', false,
			],
		];
	}
}
