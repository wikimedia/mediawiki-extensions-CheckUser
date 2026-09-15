<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\Hook\HookRunner;
use MediaWiki\Extension\CheckUser\HookHandler\RLRegisterModulesHandler;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\RLRegisterModulesHandler
 */
class RLRegisterModulesHandlerTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideTestIPInfoHooksModuleRegistration */
	public function testIPInfoHooksModuleRegistration( array $extensionRegistryReturnMap, bool $isLoaded ) {
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnMap( $extensionRegistryReturnMap );
		$handler = new RLRegisterModulesHandler(
			$mockExtensionRegistry,
			$this->createMock( HookRunner::class ),
			new HashConfig( [
				'CheckUserSuggestedInvestigationsEnabled' => false,
				'CheckUserSuggestedInvestigationsQueueViews' => [],
			] )
		);

		// Run hook and save modules loaded to an array to check against in the assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertEquals( array_key_exists( 'ext.checkUser.ipInfo.hooks', $rlModules ), $isLoaded );
		$this->assertArrayHasKey( 'ext.checkUser.tempAccountOnboarding', $rlModules );

		if ( $isLoaded ) {
			$this->assertContains(
				'ipinfo-preference-use-agreement',
				$rlModules['ext.checkUser.tempAccountOnboarding']['messages'],
				'tempAccountOnboarding module has ipinfo agreement message',
			);
		}
	}

	public static function provideTestIPInfoHooksModuleRegistration() {
		return [
			'IPInfo not loaded, module not loaded' => [
				'extensionRegistryReturnMap' => [ [ 'IPInfo', '*', false ] ],
				'isLoaded' => false,
			],
			'IPInfo loaded, module loaded' => [
				'extensionRegistryReturnMap' => [ [ 'IPInfo', '*', true ] ],
				'isLoaded' => true,
			],
		];
	}

	public function testSuggestedInvestigationsQueueViewMessageRegistration(): void {
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$handler = new RLRegisterModulesHandler(
			$mockExtensionRegistry,
			$this->createMock( HookRunner::class ),
			new HashConfig( [
				'CheckUserSuggestedInvestigationsEnabled' => true,
				'CheckUserSuggestedInvestigationsQueueViews' => [
					'foo' => [
						'msgKeys' => [
							'defaultName' => 'foo-1',
							'editedName' => 'foo-2',
							'filterDialogTitle' => 'foo-3',
						],
					],
					'bar' => [
						'msgKeys' => [
							'defaultName' => 'bar-1',
							'editedName' => 'bar-2',
							'filterDialogTitle' => 'bar-3',
						],
					],
				],
			] )
		);

		// Run hook and save modules loaded to an array to check against in the assertion
		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		// Assert all messages defined in CheckUserSuggestedInvestigationsQueueViews are loaded
		$this->assertTrue( isset( $rlModules[ 'ext.checkUser.suggestedInvestigations' ][ 'messages' ] ) );
		$this->assertArrayContains(
			[ 'foo-1', 'foo-2', 'foo-3', 'bar-1', 'bar-2', 'bar-3' ],
			$rlModules[ 'ext.checkUser.suggestedInvestigations' ][ 'messages' ]
		);
	}
}
