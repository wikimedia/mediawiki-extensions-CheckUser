<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;

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
			$this->createMock( PermissionManager::class ),
			$this->createMock( TemporaryAccountLoggerFactory::class )
		) )->onGetPreferences( $user, $prefs );

		$this->assertNotEmpty(
			$prefs,
			'Preferences array should no longer be empty, preferences should be added'
		);
	}

}
