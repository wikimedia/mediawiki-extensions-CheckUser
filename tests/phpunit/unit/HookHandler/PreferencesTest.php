<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWikiUnitTestCase;
use User;

/**
 * @author DannyS712
 * @group CheckUser
 * @coversDefaultClass \MediaWiki\CheckUser\HookHandler\Preferences
 */
class PreferencesTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferences() {
		$user = $this->createMock( User::class );
		$prefs = [];

		( new Preferences() )->onGetPreferences( $user, $prefs );

		$this->assertNotEmpty(
			$prefs,
			'Preferences array should no longer be empty, preferences should be added'
		);
	}

}
