<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\AbuseFilterHandler;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\AbuseFilterHandler
 * @group CheckUser
 */
class AbuseFilterHandlerTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
	}

	public function testMakesUserUnnamedIpAsAlwaysProtected() {
		// Tests that the hook is called by AbuseFilter and adds the variables to the return
		// value of the protected variables service.
		$this->overrideConfigValue( 'AbuseFilterProtectedVariables', [] );
		$this->assertContains(
			'user_unnamed_ip',
			AbuseFilterServices::getProtectedVariablesLookup()->getAllProtectedVariables()
		);
	}

	public function testOnAbuseFilterCustomProtectedVariables() {
		// Tests the hook handler works without testing that the AbuseFilter part works, to make
		// it easier to diagnose issues if ::testMakesUserUnnamedIpAsAlwaysProtected fails.
		$variables = [];
		( new AbuseFilterHandler() )->onAbuseFilterCustomProtectedVariables( $variables );
		$this->assertArrayEquals( [ 'user_unnamed_ip' ], $variables );
	}
}
