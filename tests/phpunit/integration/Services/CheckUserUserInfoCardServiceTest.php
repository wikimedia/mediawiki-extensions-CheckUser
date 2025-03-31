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
	public function testExecute() {
		$checkUserUserInfoCardService = new CheckUserUserInfoCardService(
			MediaWikiServices::getInstance()->getService( 'GrowthExperimentsUserImpactLookup' )
		);
		$page = $this->getNonexistingTestPage();
		$user = static::getTestUser()->getUser();
		$this->assertStatusGood(
			$this->editPage( $page, 'test', '', NS_MAIN, $user )
		);

		$userImpact = $checkUserUserInfoCardService->getUserInfo( $user );
		$this->assertSame( 1, $userImpact[ 'totalEditCount' ] );
	}

	public function testExecuteInvalidUser() {
		$checkUserUserInfoCardService = new CheckUserUserInfoCardService(
			MediaWikiServices::getInstance()->getService( 'GrowthExperimentsUserImpactLookup' )
		);

		// User impacts can only be retrieved for registered users
		$anonUser = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '1.2.3.4' );
		$userImpact = $checkUserUserInfoCardService->getUserInfo( $anonUser );
		$this->assertSame( [], $userImpact );
	}
}
