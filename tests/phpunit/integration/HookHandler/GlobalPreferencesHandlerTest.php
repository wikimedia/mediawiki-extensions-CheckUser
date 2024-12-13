<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\GlobalPreferencesHandler;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\GlobalPreferencesHandler
 */
class GlobalPreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
	}

	/** @dataProvider providePreferences */
	public function testOnGlobalPreferencesSetGlobalPreferences(
		array $oldPreferences,
		array $newPreferences,
		string $expectedLogMethod
	) {
		$user = $this->createMock( User::class );

		$logger = $this->createMock( TemporaryAccountLogger::class );
		foreach ( [ 'logGlobalAccessEnabled', 'logGlobalAccessDisabled' ] as $logMethod ) {
			if ( $expectedLogMethod === $logMethod ) {
				$logger->expects( $this->once() )
					->method( $logMethod );
			} else {
				$logger->expects( $this->never() )
					->method( $logMethod );
			}
		}

		$loggerFactory = $this->createMock( TemporaryAccountLoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$this->setUserLang( 'qqx' );
		( new GlobalPreferencesHandler( $loggerFactory ) )
			->onGlobalPreferencesSetGlobalPreferences( $user, $oldPreferences, $newPreferences );
	}

	public static function providePreferences() {
		return [
			'IP reveal not in preferences' => [ [], [], '' ],
			'IP reveal made global preference but not enabled' => [
				[],
				[ 'checkuser-temporary-account-enable' => '0' ],
				''
			],
			'IP reveal made global preference and enabled' => [
				[],
				[ 'checkuser-temporary-account-enable' => '1' ],
				'logGlobalAccessEnabled'
			],
			'IP reveal starts disabled then removed from global preferences' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[],
				''
			],
			'IP reveal starts enabled then removed from global preferences' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[],
				'logGlobalAccessDisabled'
			],
			'IP reveal global preference starts enabled then disabled' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[ 'checkuser-temporary-account-enable' => '0' ],
				'logGlobalAccessDisabled'
			],
			'IP reveal global preference starts disabled then enabled' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[ 'checkuser-temporary-account-enable' => '1' ],
				'logGlobalAccessEnabled'
			],
			'IP reveal global preference starts enabled and not changed' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[ 'checkuser-temporary-account-enable' => '1' ],
				''
			],
			'IP reveal global preference starts disabled and not changed' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[ 'checkuser-temporary-account-enable' => '0' ],
				''
			],
		];
	}
}
