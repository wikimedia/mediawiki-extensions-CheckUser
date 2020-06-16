<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWikiIntegrationTestCase;
use ReflectionClass;
use SpecialCheckUser;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers SpecialCheckUser
 */
class SpecialCheckUserTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var int
	 */
	private $lowerThanLimitIPv4;

	/**
	 * @var int
	 */
	private $lowerThanLimitIPv6;

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'page',
				'revision',
				'ip_changes',
				'text',
				'archive',
				'recentchanges',
				'logging',
				'page_props',
				'cu_changes',
			]
		);
	}

	protected function setUp() : void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgCheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			]
		] );

		$CIDRLimit = \RequestContext::getMain()->getConfig()->get( 'CheckUserCIDRLimit' );
		$this->lowerThanLimitIPv4 = $CIDRLimit['IPv4'] - 1;
		$this->lowerThanLimitIPv6 = $CIDRLimit['IPv6'] - 1;
	}

	/**
	 * @covers SpecialCheckUser::getIpConds
	 * @dataProvider provideGetIpConds
	 */
	public function testGetIpConds( $target, $expected ) {
		$dbr = wfGetDB( DB_REPLICA );

		$this->assertEquals(
			$expected,
			SpecialCheckUser::getIpConds( $dbr, $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::getIpConds
	 * @return array
	 */
	public function provideGetIpConds() {
		return [
			[
				'212.35.31.121',
				[ 'cuc_ip_hex' => 'D4231F79' ],
			],
			[
				'212.35.31.121/32',
				[ 0 => 'cuc_ip_hex BETWEEN \'D4231F79\' AND \'D4231F79\'' ],
			],
			[
				'::e:f:2001',
				[ 'cuc_ip_hex' => 'v6-00000000000000000000000E000F2001' ],
			],
			[
				'::e:f:2001/96',
				[ 0 => 'cuc_ip_hex BETWEEN \'v6-00000000000000000000000E00000000\'' .
					' AND \'v6-00000000000000000000000EFFFFFFFF\'' ],
			],
			[ "0.17.184.5/{$this->lowerThanLimitIPv4}", false ],
			[ "2000::/{$this->lowerThanLimitIPv6}", false ],
		];
	}

	/**
	 * @covers SpecialCheckUser::isValidRange
	 * @dataProvider provideIsValidRange
	 */
	public function testIsValidRange( $target, $expected ) {
		$this->assertEquals(
			$expected,
			SpecialCheckUser::isValidRange( $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::isValid
	 * @return array
	 */
	public function provideIsValidRange() {
		return [
			[ '212.35.31.121', true ],
			[ '212.35.31.121/32', true ],
			[ '::e:f:2001', true ],
			[ '::e:f:2001/96', true ],
			[ "0.17.184.5/{$this->lowerThanLimitIPv4}", false ],
			[ "2000::/{$this->lowerThanLimitIPv6}", false ]
		];
	}

	/**
	 * @covers SpecialCheckUser::checkReason
	 * @dataProvider provideCheckReason
	 */
	public function testCheckReason( $config, $reason, $expected ) {
		$this->setMwGlobals( 'wgCheckUserForceSummary', $config );
		$class = new ReflectionClass( SpecialCheckUser::class );
		$method = $class->getMethod( 'checkReason' );
		$method->setAccessible( true );
		$instance = $class->newInstanceWithoutConstructor();
		$property = $class->getProperty( 'reason' );
		$property->setAccessible( true );
		$property->setValue( $instance, $reason );
		$this->assertEquals(
			$expected,
			$method->invoke( $instance )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::checkReason
	 * @return array
	 */
	public function provideCheckReason() {
		return [
			[ false, '', true ],
			[ false, 'Test Reason', true ],
			[ true, '', false ],
			[ true, 'Test Reason', true ]
		];
	}
}
