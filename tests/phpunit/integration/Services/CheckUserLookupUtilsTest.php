<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserLookupUtils
 * @group Database
 */
class CheckUserLookupUtilsTest extends MediaWikiIntegrationTestCase {
	/** @dataProvider provideGetIpConds */
	public function testGetIpConds( $target, $table, $xfor, $expectedSql ) {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		/** @var CheckUserLookupUtils $checkUserLookupUtils */
		$checkUserLookupUtils = $this->getServiceContainer()->get( 'CheckUserLookupUtils' );
		$actualExpr = $checkUserLookupUtils->getIPTargetExpr( $target, $xfor, $table );
		if ( $expectedSql === null ) {
			$this->assertNull(
				$actualExpr,
				'Conditions were generated when they should not have been, as the target was invalid.'
			);
		} else {
			$this->assertEquals(
				$expectedSql, $actualExpr->toSql( $this->getDb() ),
				'The SQL representation of the conditions for an IP or IP range target is not as expected.'
			);
		}
	}

	public static function provideGetIpConds() {
		return [
			'Single IPv4 address for cu_changes and non-XFF' => [
				// The target IP address or range
				'212.35.31.121',
				// The table which these conditions will be used on
				CheckUserQueryInterface::CHANGES_TABLE,
				// The value of the xfor parameter (true indicates the IP is an XFF IP).
				false,
				// The expected SQL representation of the IExpression that is returned.
				'cuc_ip_hex = \'D4231F79\'',
			],
			'Single IPv4 address for cu_private_event and XFF' => [
				'212.35.31.121', CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, true, 'cupe_xff_hex = \'D4231F79\'',
			],
			'Single IPv4 address for cu_log_event and non-XFF' => [
				'212.35.31.121', CheckUserQueryInterface::LOG_EVENT_TABLE, false, 'cule_ip_hex = \'D4231F79\'',
			],
			'Single IPv4 address notated as a /32' => [
				'212.35.31.121/32', CheckUserQueryInterface::CHANGES_TABLE, false,
				'(cuc_ip_hex >= \'D4231F79\' AND cuc_ip_hex <= \'D4231F79\')',
			],
			'Single IPv6 address' => [
				'::e:f:2001', CheckUserQueryInterface::CHANGES_TABLE, false,
				'cuc_ip_hex = \'v6-00000000000000000000000E000F2001\'',
			],
			'IPv6 /96 range for cu_changes and non-XFF' => [
				'::e:f:2001/96', CheckUserQueryInterface::CHANGES_TABLE, false,
				'(cuc_ip_hex >= \'v6-00000000000000000000000E00000000\' AND ' .
					'cuc_ip_hex <= \'v6-00000000000000000000000EFFFFFFFF\')',
			],
			'IPv6 /96 range for cu_private_event and XFF' => [
				'::e:f:2001/96', CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, true,
				'(cupe_xff_hex >= \'v6-00000000000000000000000E00000000\' AND ' .
					'cupe_xff_hex <= \'v6-00000000000000000000000EFFFFFFFF\')',
			],
			'Invalid IP address' => [ 'abcedf', CheckUserQueryInterface::CHANGES_TABLE, false, null ],
			'IPv4 address with CIDR lower than the limit' => [
				'0.17.184.5/15', CheckUserQueryInterface::CHANGES_TABLE, false, null
			],
			'IPv6 address with CIDR lower than the limit' => [
				'2000::/18', CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, true, null
			],
		];
	}

	/** @dataProvider provideIsValidRange */
	public function testIsValidRange( $target, $expected ) {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		/** @var CheckUserLookupUtils $checkUserLookupUtils */
		$checkUserLookupUtils = $this->getServiceContainer()->get( 'CheckUserLookupUtils' );
		$this->assertSame(
			$expected, $checkUserLookupUtils->isValidIPOrRange( $target ),
			'The return value of ::isValidIPOrRange is not as expected.'
		);
	}

	public static function provideIsValidRange() {
		return [
			'Single IPv4 address' => [ '212.35.31.121', true ],
			'Single IPv4 address notated as a /32' => [ '212.35.31.121/32', true ],
			'Single IPv6 address' => [ '::e:f:2001', true ],
			'IPv6 /96 range' => [ '::e:f:2001/96', true ],
			'Invalid IP address' => [ 'abcedf', false ],
			'IPv4 address with CIDR lower than the limit' => [ '0.17.184.5/15', false ],
			'IPv6 address with CIDR lower than the limit' => [ '2000::/18', false ],
		];
	}

	/** @dataProvider provideGetIndexName */
	public function testGetIndexName( $table, $xfor, $expectedIndexValue ) {
		/** @var CheckUserLookupUtils $checkUserLookupUtils */
		$checkUserLookupUtils = $this->getServiceContainer()->get( 'CheckUserLookupUtils' );
		$this->assertSame(
			$expectedIndexValue, $checkUserLookupUtils->getIndexName( $xfor, $table ),
			'Index name is not as expected.'
		);
	}

	public static function provideGetIndexName() {
		return [
			'cu_changes with null xfor' => [ CheckUserQueryInterface::CHANGES_TABLE, null, 'cuc_actor_ip_time' ],
			'cu_private_event with null xfor' => [
				CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, null, 'cupe_actor_ip_time',
			],
			'cu_log_event with false xfor' => [ CheckUserQueryInterface::LOG_EVENT_TABLE, false, 'cule_ip_hex_time' ],
			'cu_private_event with true xfor' => [
				CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, true, 'cupe_xff_hex_time',
			],
		];
	}

	/** @dataProvider provideGetIpHexColumn */
	public function testGetIpHexColumn( $xfor, $table, $expectedReturnValue ) {
		$objectUnderTest = TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
		);
		$this->assertSame(
			$expectedReturnValue, $objectUnderTest->getIpHexColumn( $xfor, $table ),
			'Call to ::getIpHexColumn did not return the correct value.'
		);
	}

	public static function provideGetIpHexColumn() {
		return [
			'Table as cu_changes with xfor as false' => [ false, CheckUserQueryInterface::CHANGES_TABLE, 'cuc_ip_hex' ],
			'Table as cu_changes with xfor as true' => [ true, CheckUserQueryInterface::CHANGES_TABLE, 'cuc_xff_hex' ],
			'Table as cu_log_event with xfor as true' => [
				true, CheckUserQueryInterface::LOG_EVENT_TABLE, 'cule_xff_hex',
			],
			'Table as cu_private_event with xfor as false' => [
				false, CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE, 'cupe_ip_hex',
			],
		];
	}
}
