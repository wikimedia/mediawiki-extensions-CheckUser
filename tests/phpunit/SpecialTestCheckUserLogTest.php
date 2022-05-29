<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\Specials\SpecialCheckUserLog;

/**
 * Test class for SpecialCheckUserLog class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUserLog
 */
class SpecialTestCheckUserLogTest extends CheckUserIntegrationTestCaseTest {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_log',
			]
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUserLog::verifyInitiator
	 */
	public function testVerifyInitiator() {
		// Existing user
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$this->assertSame(
			$testUser->getId(),
			SpecialCheckUserLog::verifyInitiator( $testUser->getName() ),
			'For an existing user it\'s ID should be returned.'
		);

		// Non-existent user with a valid username
		$testUser = $this->getNonExistentTestUser();
		$this->assertFalse(
			SpecialCheckUserLog::verifyInitiator( $testUser->getName() ),
			'Non-existent users should not be a valid initiator.'
		);

		// Only registered users can be performers/initiators,
		// so test an IP address as the username.
		$this->assertFalse(
			SpecialCheckUserLog::verifyInitiator( '1.2.3.4' ),
			'Only registered users can be valid performers.'
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUserLog::verifyTarget
	 */
	public function testVerifyTargetUser() {
		// Existing user
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$this->assertSame(
			$testUser->getId(),
			SpecialCheckUserLog::verifyTarget( $testUser->getName() ),
			'For an existing user it\'s ID should be returned.'
		);

		// Non-existent user with a valid username
		$testUser = $this->getNonExistentTestUser();
		$this->assertFalse(
			SpecialCheckUserLog::verifyTarget( $testUser->getName() ),
			'Non-existent users should not be a valid target.'
		);

		// Invalid username
		$this->assertFalse(
			SpecialCheckUserLog::verifyTarget( '/' ),
			'Invalid usernames should not be a valid target.'
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUserLog::verifyTarget
	 * @dataProvider provideVerifyTargetIP
	 */
	public function testVerifyTargetIP( $target, $expected ) {
		$this->assertArrayEquals(
			$expected,
			SpecialCheckUserLog::verifyTarget( $target ),
			true,
			false,
			'Valid IP addresses should be seen as valid targets and parsed as a IP or IP range.'
		);
	}

	public function provideVerifyTargetIP() {
		return [
			'Single IP' => [ '124.0.0.0', [ '7C000000' ] ],
			'/24 IP range' => [ '124.0.0.0/24', [ '7C000000', '7C0000FF' ] ],
			'/16 IP range' => [ '124.0.0.0/16', [ '7C000000', '7C00FFFF' ] ],
			'Single IP notated as a /32 range' => [ '1.2.3.4/32', [ '01020304' ] ],
			'Single IPv6' => [ '::e:f:2001', [ 'v6-00000000000000000000000E000F2001' ] ],
			'/96 IPv6 range' => [ '::e:f:2001/96', [
					'v6-00000000000000000000000E00000000',
					'v6-00000000000000000000000EFFFFFFFF'
				]
			],
		];
	}
}
