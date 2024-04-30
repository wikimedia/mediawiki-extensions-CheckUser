<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\Preferences
 */
class PreferencesTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideOnGetPreferencesTemporaryAccount
	 */
	public function testOnGetPreferencesTemporaryAccount( $options ) {
		$user = $this->createMock( User::class );
		$prefs = [];

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $user, $right ) use ( $options ) {
				if ( $right === 'checkuser-temporary-account' ) {
					return $options['hasRight'];
				}
				if ( $right === 'checkuser-temporary-account-no-preference' ) {
					return $options['hasNoPreferenceRight'];
				}
				return true;
			} );

		$loggerFactory = $this->createMock( TemporaryAccountLoggerFactory::class );

		( new Preferences(
			$permissionManager,
			$loggerFactory
		) )->onGetPreferences( $user, $prefs );

		$this->assertSame(
			$options['expected'],
			isset( $prefs['checkuser-temporary-account-enable'] )
		);
		$this->assertSame(
			$options['expected'],
			isset( $prefs['checkuser-temporary-account-enable-description'] )
		);
	}

	public static function provideOnGetPreferencesTemporaryAccount() {
		return [
			'User has right' => [
				[
					'expected' => true,
					'hasRight' => true,
					'hasNoPreferenceRight' => false,
				],
			],
			'User has no-preference right' => [
				[
					'expected' => false,
					'hasRight' => false,
					'hasNoPreferenceRight' => true,
				],
			],
			'User does not have right' => [
				[
					'expected' => false,
					'hasRight' => false,
					'hasNoPreferenceRight' => false,
				],
			],
		];
	}

}
