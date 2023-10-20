<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\CheckUser\LogPager;
use MediaWiki\CheckUser\Tests\CheckUserIntegrationTestCaseTest;

/**
 * Test class for LogPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\LogPager
 */
class LogPagerTest extends CheckUserIntegrationTestCaseTest {

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
	 * @covers \MediaWiki\CheckUser\LogPager::getTargetSearchConds
	 */
	public function testGetTargetSearchCondsUser() {
		// Existing user
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$this->assertArrayEquals(
			$this->getExpectedGetTargetSearchConds( 'user', $testUser->getId() ),
			LogPager::getTargetSearchConds( $testUser->getName() ),
			false,
			true,
			'For an existing user the valid search cond should be returned.'
		);

		// Non-existent user with a valid username
		$testUser = $this->getNonExistentTestUser();
		$this->assertNull(
			LogPager::getTargetSearchConds( $testUser->getName() ),
			'Non-existent users should not be a valid target.'
		);

		// Invalid username
		$this->assertNull(
			LogPager::getTargetSearchConds( '/' ),
			'Invalid usernames should not be a valid target.'
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\LogPager::getTargetSearchConds
	 * @dataProvider provideGetTargetSearchCondsIP
	 */
	public function testGetTargetSearchCondsIP( $target, $type, $start, $end ) {
		$this->assertArrayEquals(
			$this->getExpectedGetTargetSearchConds( $type, null, $start, $end ),
			LogPager::getTargetSearchConds( $target ),
			false,
			true,
			'Valid IP addresses should have associated search conditions.'
		);
	}

	public function provideGetTargetSearchCondsIP() {
		return [
			'Single IP' => [ '124.0.0.0', 'ip', '7C000000', '7C000000' ],
			'/24 IP range' => [ '124.0.0.0/24', 'range', '7C000000', '7C0000FF' ],
			'/16 IP range' => [ '124.0.0.0/16', 'range', '7C000000', '7C00FFFF' ],
			'Single IP notated as a /32 range' => [ '1.2.3.4/32', 'ip', '01020304', '01020304' ],
			'Single IPv6' => [ '::e:f:2001', 'ip',
				'v6-00000000000000000000000E000F2001',
				'v6-00000000000000000000000E000F2001'
			],
			'/96 IPv6 range' => [ '::e:f:2001/96', 'range',
				'v6-00000000000000000000000E00000000',
				'v6-00000000000000000000000EFFFFFFFF'
			],
		];
	}

	private function getExpectedGetTargetSearchConds( $type, $id, $start = 0, $end = 0 ) {
		$dbr = wfGetDB( DB_REPLICA );
		switch ( $type ) {
			case 'ip':
				return [
					'cul_target_hex = ' . $dbr->addQuotes( $start ) . ' OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $start ) . ')'
				];
			case 'range':
				return [
					'(cul_target_hex >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_target_hex <= ' . $dbr->addQuotes( $end ) . ') OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $end ) . ')'
				];
			case 'user':
				if ( $id === null ) {
					return null;
				}
				return [
					'cul_type' => [ 'userips', 'useredits', 'investigate' ],
					'cul_target_id' => $id,
				];
			default:
				$this->fail( 'getExpectedGetTargetSearchConds() got an unexpected type.' );
		}
	}
}
