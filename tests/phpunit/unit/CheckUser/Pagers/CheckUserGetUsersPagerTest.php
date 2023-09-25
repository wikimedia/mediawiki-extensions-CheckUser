<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use HashConfig;
use LogicException;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\User\UserIdentityValue;
use RequestContext;
use Wikimedia\IPUtils;

/**
 * Test class for CheckUserGetUsersPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 */
class CheckUserGetUsersPagerTest extends CheckUserPagerCommonUnitTest {

	protected function getPagerClass(): string {
		return CheckUserGetUsersPager::class;
	}

	public function testGetQueryInfoThrowsExceptionWithNullTable() {
		$object = $this->getMockBuilder( CheckUserGetUsersPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( LogicException::class );
		$object->getQueryInfo( null );
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $table, $tableSpecificQueryInfo, $expectedQueryInfo ) {
		// Mock config on main request context for ::getIpConds which is static
		// and gets the config from the main request.
		RequestContext::getMain()->setConfig(
			new HashConfig( [ 'CheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			] ] )
		);
		$this->commonTestGetQueryInfo(
			UserIdentityValue::newAnonymous( '127.0.0.1' ), false,
			$table, $tableSpecificQueryInfo, $expectedQueryInfo
		);
	}

	public static function provideGetQueryInfo() {
		return [
			'cu_changes table' => [
				'cu_changes', [
					'tables' => [ 'cu_changes' ],
					'conds' => [ 'cuc_only_for_read_old' => 0 ],
					'fields' => [], 'options' => [], 'join_conds' => [],
				],
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [ 'cuc_ip_hex' => IPUtils::toHex( '127.0.0.1' ), 'cuc_only_for_read_old' => 0 ],
					'fields' => [],
					'options' => [ 'USE INDEX' => [ 'cu_changes' => 'cuc_ip_hex_time' ] ],
					'join_conds' => [],
				]
			],
			'cu_log_event table' => [
				'cu_log_event', [
					'tables' => [ 'cu_log_event' ], 'conds' => [],
					'fields' => [], 'options' => [], 'join_conds' => [],
				],
				[
					'tables' => [ 'cu_log_event' ],
					'conds' => [ 'cule_ip_hex' => IPUtils::toHex( '127.0.0.1' ) ],
					'fields' => [],
					'options' => [ 'USE INDEX' => [ 'cu_log_event' => 'cule_ip_hex_time' ] ],
					'join_conds' => [],
				]
			],
			'cu_private_event table' => [
				'cu_private_event', [
					'tables' => [ 'cu_private_event' ], 'conds' => [],
					'fields' => [], 'options' => [], 'join_conds' => [],
				],
				[
					'tables' => [ 'cu_private_event' ],
					'conds' => [ 'cupe_ip_hex' => IPUtils::toHex( '127.0.0.1' ) ],
					'fields' => [],
					'options' => [ 'USE INDEX' => [ 'cu_private_event' => 'cupe_ip_hex_time' ] ],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuChanges */
	public function testGetQueryInfoForCuChanges( $eventTableMigrationStage, $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuChanges',
			[ 'eventTableReadNew' => boolval( $eventTableMigrationStage & SCHEMA_COMPAT_READ_NEW ) ],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuChanges() {
		return [
			'Returns expected keys to arrays and includes cu_changes in tables while reading new' => [
				SCHEMA_COMPAT_READ_NEW, [
					// Fields should be an array
					'fields' => [],
					// Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					// When reading new, do not include rows from cu_changes
					// that were marked as only being for read old.
					'conds' => [ 'cuc_only_for_read_old' => 0 ],
					// Should be all of these as arrays
					'options' => [],
					'join_conds' => [],
				]
			],
			'Returns expected keys to arrays and includes cu_changes in tables while reading old' => [
				SCHEMA_COMPAT_READ_OLD, [
					// Fields should be an array
					'fields' => [],
					// Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					// Should be all of these as arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuLogEvent */
	public function testGetQueryInfoForCuLogEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuLogEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuLogEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				// Fields should be an array
				'fields' => [],
				// Tables array should have at least cu_log_event
				'tables' => [ 'cu_log_event' ],
				// All other values should be arrays
				'conds' => [],
				'options' => [],
				'join_conds' => [],
			]
		];
	}

	/** @dataProvider provideGetQueryInfoForCuPrivateEvent */
	public function testGetQueryInfoForCuPrivateEvent( $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuPrivateEvent',
			[],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuPrivateEvent() {
		return [
			'Returns expected keys to arrays and includes cu_private_event in tables' => [
				// Fields should be an array
				'fields' => [],
				// Tables array should have at least cu_private_event
				'tables' => [ 'cu_private_event' ],
				// All other values should be arrays
				'conds' => [],
				'options' => [],
				'join_conds' => [],
			]
		];
	}
}
