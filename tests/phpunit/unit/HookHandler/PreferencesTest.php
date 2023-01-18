<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\Permissions\PermissionManager;
use MediaWikiUnitTestCase;
use User;

/**
 * @author DannyS712
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\Preferences
 */
class PreferencesTest extends MediaWikiUnitTestCase {

	public function testOnGetPreferences() {
		$user = $this->createMock( User::class );
		$prefs = [];

		( new Preferences(
			$this->createMock( PermissionManager::class )
		) )->onGetPreferences( $user, $prefs );

		$this->assertNotEmpty(
			$prefs,
			'Preferences array should no longer be empty, preferences should be added'
		);
	}

	/**
	 * @dataProvider provideOnGetPreferencesTemporaryAccount
	 */
	public function testOnGetPreferencesTemporaryAccount( $options ) {
		$user = $this->createMock( User::class );
		$prefs = [];

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'UserHasRight' )
			->willReturn( $options['hasRight'] );

		( new Preferences( $permissionManager ) )->onGetPreferences( $user, $prefs );

		$this->assertSame(
			$options['expected'],
			isset( $prefs['checkuser-temporary-account-enable'] )
		);
	}

	public function provideOnGetPreferencesTemporaryAccount() {
		return [
			'User has right' => [
				[
					'expected' => true,
					'hasRight' => true,
				],
			],
			'User does not have right' => [
				[
					'expected' => false,
					'hasRight' => false,
				],
			],
		];
	}

}
