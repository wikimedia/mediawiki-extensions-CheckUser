<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\Services\CheckUserUserInfoCardService;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUserInfoCardService
 */
class CheckUserUserInfoCardServiceTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// CheckUserUserInfoCardService has dependencies provided by the GrowthExperiments extension.
		$this->markTestSkippedIfExtensionNotLoaded( 'GrowthExperiments' );
	}

	private function getObjectUnderTest(): CheckUserUserInfoCardService {
		$services = MediaWikiServices::getInstance();
		return new CheckUserUserInfoCardService(
			$services->getService( 'GrowthExperimentsUserImpactLookup' ),
			$services->getExtensionRegistry()
		);
	}

	public function testExecute() {
		$page = $this->getNonexistingTestPage();
		$user = static::getTestUser()->getUser();
		$this->assertStatusGood(
			$this->editPage( $page, 'test', '', NS_MAIN, $user )
		);
		// Run deferred updates, to ensure that globalEditCount gets populated in CentralAuth.
		$this->runDeferredUpdates();
		$userInfo = $this->getObjectUnderTest()->getUserInfo( $user );
		$this->assertSame( 1, $userInfo[ 'totalEditCount' ] );
		if ( MediaWikiServices::getInstance()->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			// TODO: Fix this test so that we assert that the globalEditCount is 1.
			$this->assertArrayHasKey( 'globalEditCount', $userInfo );
		}
		$this->assertSame( 0, $userInfo[ 'thanksGiven' ] );
		$this->assertSame( 0, $userInfo[ 'thanksReceived' ] );
	}

	public function testExecuteInvalidUser() {
		// User impacts can only be retrieved for registered users
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$userImpact = $this->getObjectUnderTest()->getUserInfo( $anonUser );
		$this->assertSame( [], $userImpact );
	}
}
