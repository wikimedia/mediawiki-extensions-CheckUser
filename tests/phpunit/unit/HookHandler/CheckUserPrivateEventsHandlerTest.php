<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use User;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler
 * @group CheckUser
 */
class CheckUserPrivateEventsHandlerTest extends MediaWikiUnitTestCase {
	public function getObjectUnderTestForNoCheckUserInsertCalls( $overrides = [] ): CheckUserPrivateEventsHandler {
		$noOpMockCheckUserInsert = $this->createNoOpMock( CheckUserInsert::class );
		return new CheckUserPrivateEventsHandler(
			$noOpMockCheckUserInsert,
			$overrides['config'] ?? $this->createMock( Config::class ),
			$overrides['userIdentityLookup'] ?? $this->createMock( UserIdentityLookup::class )
		);
	}

	public function testUserLogoutCompleteWhenLogLoginsConfigSetToFalse() {
		$handler = $this->getObjectUnderTestForNoCheckUserInsertCalls( [
			'config' => new HashConfig( [ 'CheckUserLogLogins' => false ] ),
		] );
		$html = '';
		$handler->onUserLogoutComplete( $this->createMock( User::class ), $html, 'OldName' );
	}
}
