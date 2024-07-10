<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler
 * @group Database
 * @group CheckUser
 */
class CheckUserPrivateEventsHandlerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;

	private function getObjectUnderTest(): CheckUserPrivateEventsHandler {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup()
		);
	}

	/**
	 * Re-define the CheckUserInsert service to expect no calls to any of it's methods.
	 * This is done to assert that no inserts to the database occur instead of having
	 * to assert a row count of zero.
	 *
	 * @return void
	 */
	private function expectNoCheckUserInsertCalls() {
		$this->setService( 'CheckUserInsert', function () {
			return $this->createNoOpMock( CheckUserInsert::class );
		} );
	}

	public function testUserLogoutComplete() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$testUser = $this->getTestUser()->getUserIdentity();
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			$testUser->getName()
		);
		$this->assertRowCount(
			1, 'cu_private_event', 'cupe_id',
			'Should have logged the event to cu_private_event'
		);
	}

	public function testUserLogoutCompleteInvalidUser() {
		$this->expectNoCheckUserInsertCalls();
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		$html = '';
		$this->getObjectUnderTest()->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			'Nonexisting test user1234567'
		);
	}
}
