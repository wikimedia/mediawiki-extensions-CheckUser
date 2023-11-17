<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use HashConfig;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Tests\Unit\Libs\Rdbms\AddQuoterMock;
use MediaWiki\User\UserIdentityValue;
use RequestContext;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\Platform\SQLPlatform;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends CheckUserPagerCommonUnitTest {

	/** @inheritDoc */
	protected function getPagerClass(): string {
		return CheckUserGetIPsPager::class;
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $table, $tableSpecificQueryInfo, $expectedQueryInfo ) {
		$this->commonTestGetQueryInfo(
			UserIdentityValue::newRegistered( 1, 'Testing' ), null,
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
					'conds' => [ 'actor_user' => 1, 'cuc_only_for_read_old' => 0 ],
					'fields' => [],
					'options' => [
						'USE INDEX' => [ 'cu_changes' => 'cuc_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
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
					'conds' => [ 'actor_user' => 1 ],
					'fields' => [],
					'options' => [
						'USE INDEX' => [ 'cu_log_event' => 'cule_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
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
					'conds' => [ 'actor_user' => 1 ],
					'fields' => [],
					'options' => [
						'USE INDEX' => [ 'cu_private_event' => 'cupe_actor_ip_time' ],
						'GROUP BY' => [ 'ip', 'ip_hex' ]
					],
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
				SCHEMA_COMPAT_READ_NEW,
				[
					# Fields should be an array
					'fields' => [
						'ip' => 'cuc_ip',
						'ip_hex' => 'cuc_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cuc_timestamp)',
						'last' => 'MAX(cuc_timestamp)',
					],
					# Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					# When reading new, do not include rows from cu_changes
					# that were marked as only being for read old.
					'conds' => [ 'cuc_only_for_read_old' => 0 ],
					# Should be all of these as arrays
					'options' => [],
					'join_conds' => [],
				]
			],
			'Returns expected keys to arrays and includes cu_changes in tables while reading old' => [
				SCHEMA_COMPAT_READ_OLD,
				[
					# Fields should be an array
					'fields' => [
						'ip' => 'cuc_ip',
						'ip_hex' => 'cuc_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cuc_timestamp)',
						'last' => 'MAX(cuc_timestamp)',
					],
					# Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					# Should be all of these as arrays
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
				[
					# Fields should be an array
					'fields' => [
						'ip' => 'cule_ip',
						'ip_hex' => 'cule_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cule_timestamp)',
						'last' => 'MAX(cule_timestamp)',
					],
					# Tables array should have at least cu_log_event
					'tables' => [ 'cu_log_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
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
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				[
					# Fields should be an array
					'fields' => [
						'ip' => 'cupe_ip',
						'ip_hex' => 'cupe_ip_hex',
						'count' => 'COUNT(*)',
						'first' => 'MIN(cupe_timestamp)',
						'last' => 'MAX(cupe_timestamp)',
					],
					# Tables array should have at least cu_private_event
					'tables' => [ 'cu_private_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	public function testGetIndexField() {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->assertSame(
			'last',
			$object->getIndexField(),
			'::getIndexField did not return the expected value.'
		);
	}

	/** @dataProvider provideGetCountForIPActions */
	public function testGetCountForIPActions(
		$cuChangesCount, $cuLogEventCount, $cuPrivateEventCount,
		$expectedReturnValue, $eventTableMigrationStage
	) {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getCountForIPActionsPerTable' ] )
			->getMock();
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_READ_OLD ) {
			$tables = [ 'cu_changes' ];
			$withConsecutive = [ [ '127.0.0.1', 'cu_changes' ] ];
		} else {
			$tables = [ 'cu_changes', 'cu_log_event', 'cu_private_event' ];
			$withConsecutive = [
				[ '127.0.0.1', 'cu_changes' ],
				[ '127.0.0.1', 'cu_log_event' ],
				[ '127.0.0.1', 'cu_private_event' ],
			];
		}
		$object->expects( $this->exactly( count( $tables ) ) )
			->method( 'getCountForIPActionsPerTable' )
			->withConsecutive( ...$withConsecutive )
			->willReturnOnConsecutiveCalls( $cuChangesCount, $cuLogEventCount, $cuPrivateEventCount );
		$object = TestingAccessWrapper::newFromObject( $object );
		$object->eventTableReadNew = boolval( $eventTableMigrationStage & SCHEMA_COMPAT_READ_NEW );
		$this->assertSame(
			$expectedReturnValue,
			$object->getCountForIPActions( '127.0.0.1' ),
			'Return value of ::getCountForIPActions was not as expected.'
		);
	}

	public static function provideGetCountForIPActions() {
		return [
			'All ::getCountForIPActionsPerTable counts as null when reading new' => [
				null, null, null, false, SCHEMA_COMPAT_READ_NEW
			],
			'cu_changes ::getCountForIPActionsPerTable count as null when reading old' => [
				null, [ 'total' => 2, 'by_this_target' => 1 ], [ 'total' => 2, 'by_this_target' => 1 ], false,
				SCHEMA_COMPAT_READ_OLD
			],
			'All ::getCountForIPActionsPerTable counts as array when reading new' => [
				[ 'total' => 1, 'by_this_target' => 0 ], [ 'total' => 2, 'by_this_target' => 1 ],
				[ 'total' => 3, 'by_this_target' => 2 ], 6, SCHEMA_COMPAT_READ_NEW
			],
			'All but one ::getCountForIPActionsPerTable counts as array when reading new' => [
				[ 'total' => 1, 'by_this_target' => 0 ], null, [ 'total' => 3, 'by_this_target' => 1 ], 4,
				SCHEMA_COMPAT_READ_NEW
			],
			'cu_changes ::getCountForIPActionsPerTable count as array when reading old' => [
				[ 'total' => 4, 'by_this_target' => 2 ], [ 'total' => 5, 'by_this_target' => 1 ], false,
				4, SCHEMA_COMPAT_READ_OLD
			],
			'All ::getCountForIPActionsPerTable counts as array when reading new, total not higher' => [
				[ 'total' => 1, 'by_this_target' => 1 ], [ 'total' => 2, 'by_this_target' => 2 ],
				[ 'total' => 3, 'by_this_target' => 3 ], false, SCHEMA_COMPAT_READ_NEW
			],
			'cu_changes ::getCountForIPActionsPerTable count as array when reading old, total not higher' => [
				[ 'total' => 4, 'by_this_target' => 4 ], [ 'total' => 5, 'by_this_target' => 5 ], false,
				false, SCHEMA_COMPAT_READ_OLD
			],
		];
	}

	/** @dataProvider provideInvalidIPsAndRanges */
	public function testGetCountForIPActionsPerTableReturnsFalseOnInvalidIP( $invalidIPOrInvalidRange, $table ) {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		# Mock config on main request context for un-mocked call to ::isValidRange via ::getIpConds
		# These cannot be mocked as they are static.
		RequestContext::getMain()->setConfig(
			new HashConfig( [ 'CheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			] ] )
		);
		// Mock replica DB as AddQuoterMock, as only quoting is needed.
		$object->mDb = new AddQuoterMock();
		$object = TestingAccessWrapper::newFromObject( $object );
		$this->assertNull(
			$object->getCountForIPActionsPerTable( $invalidIPOrInvalidRange, $table ),
			'::getCountForIPActionsPerTable should return false on invalid range or invalid IP.'
		);
	}

	public static function provideInvalidIPsAndRanges() {
		return [
			'Invalid IPv4' => [ '123454.5.4.3', 'cu_changes' ],
			'Invalid IPv6' => [ '123123:123123123123:12', 'cu_changes' ],
			'Invalid IPv6 for cu_private_event' => [ '123123:123123123123:12', 'cu_private_event' ],
			'Invalid IP' => [ 'test', 'cu_changes' ],
			'Invalid IP for cu_log_event table' => [ 'test', 'cu_log_event' ],
			'Invalid IPv4 range' => [ '127.0.0.1/45', 'cu_changes' ],
		];
	}

	/** @dataProvider provideGetCountForIPActionsPerTable */
	public function testGetCountForIPActionsPerTable(
		$eventTableMigrationStage, $ipOrRange, $table, $startOffset, $estimatedCounts, $actualCounts, $expectedResult
	) {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		# Mock config on main request context for un-mocked call to ::isValidRange via ::getIpConds
		# These cannot be mocked as they are static.
		RequestContext::getMain()->setConfig(
			new HashConfig( [ 'CheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			] ] )
		);
		// Mock the DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )
			->willReturnCallback( static function () use ( $dbrMock ) {
				return new SelectQueryBuilder( $dbrMock );
			} );
		// Pass through ::buildComparison to a SQLPlatform instance
		$dbrMock->method( 'buildComparison' )
			->willReturnCallback( static function ( string $op, array $conds ) {
				return ( new SQLPlatform( new AddQuoterMock() ) )->buildComparison( $op, $conds );
			} );
		// Pass through ::addQuotes to a AddQuoterMock
		$dbrMock->method( 'addQuotes' )
			->willReturnCallback( static function ( $s ) {
				return ( new AddQuoterMock() )->addQuotes( $s );
			} );
		$tablePrefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];
		// We are not testing ::getIpConds, but as it is a static method
		// it cannot be mocked. As such, just get the result to avoid
		// testing that method here too.
		$conds = CheckUserGetIPsPager::getIpConds( $dbrMock, $ipOrRange, false, $table );
		if ( $startOffset ) {
			$conds[] = "{$tablePrefix}timestamp >= '$startOffset'";
		}
		if (
			( $eventTableMigrationStage & SCHEMA_COMPAT_READ_NEW ) &&
			$table === CheckUserQueryInterface::CHANGES_TABLE
		) {
			$conds['cuc_only_for_read_old'] = 0;
		}
		// Mock estimateRowCount
		$dbrMock->method( 'estimateRowCount' )
			->withConsecutive(
				# Mock call for actions on IP
				[
					[ $table ],
					'*',
					$conds,
					'MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager::getCountForIPActionsPerTable',
					[],
					[],
				],
				# Mock call for actions on IP performed by the target user (ID 1)
				[
					[ $table, "{$table}_actor" => 'actor' ],
					'*',
					array_merge(
						$conds,
						[ 'actor_user' => 1 ]
					),
					'MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager::getCountForIPActionsPerTable',
					[],
					# Expected join conditions
					[ "{$table}_actor" => [
						'JOIN', "{$table}_actor.actor_id = {$tablePrefix}actor"
					] ],
				]
			)
			->willReturnOnConsecutiveCalls( ...$estimatedCounts );
		if ( $actualCounts ) {
			$dbrWithConsecutiveSelectRowCount = [];
			$dbrReturnConsecutiveSelectRowCount = [];
			if ( $actualCounts[0] ) {
				# Mock call for actions on IP
				$dbrWithConsecutiveSelectRowCount[] = [
					[ $table ],
					'*',
					$conds,
					'MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager::getCountForIPActionsPerTable',
					[],
					[],
				];
				$dbrReturnConsecutiveSelectRowCount[] = $actualCounts[0];
			}
			if ( $actualCounts[1] ) {
				# Mock call for actions on IP performed by the target user (ID 1)
				$dbrWithConsecutiveSelectRowCount[] = [
					[ $table, "{$table}_actor" => 'actor' ],
					'*',
					array_merge(
						$conds,
						[ 'actor_user' => 1 ]
					),
					'MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager::getCountForIPActionsPerTable',
					[],
					# Expected join conditions
					[ "{$table}_actor" => [
						'JOIN', "{$table}_actor.actor_id = {$tablePrefix}actor"
					] ],
				];
				$dbrReturnConsecutiveSelectRowCount[] = $actualCounts[1];
			}
			// Mock fetchRowCount
			$dbrMock->method( 'selectRowCount' )
				->withConsecutive( ...$dbrWithConsecutiveSelectRowCount )
				->willReturnOnConsecutiveCalls( ...$dbrReturnConsecutiveSelectRowCount );
		}
		$object->mDb = $dbrMock;
		$object = TestingAccessWrapper::newFromObject( $object );
		$object->eventTableReadNew = $eventTableMigrationStage & SCHEMA_COMPAT_READ_NEW;
		$object->startOffset = $startOffset;
		$object->target = UserIdentityValue::newRegistered( 1, 'Test' );
		$this->assertSame(
			$expectedResult,
			$object->getCountForIPActionsPerTable( $ipOrRange, $table ),
			'::getCountForIPActionsPerTable should return false on invalid range or invalid IP.'
		);
	}

	public static function provideGetCountForIPActionsPerTable() {
		return [
			'IPv4, under 1000 estimated actions, and equal counts' => [
				SCHEMA_COMPAT_READ_NEW, '127.0.0.1', 'cu_changes', '', [ 500, 500 ], [ 456, 456 ],
				[ 'total' => 456, 'by_this_target' => 456 ]
			],
			'IPv4, under 1000 estimated actions, and unequal counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1', 'cu_changes', '', [ 500, 500 ], [ 456, 345 ],
				[ 'total' => 456, 'by_this_target' => 345 ]
			],
			'IPv4, over 1000 estimated actions, and equal estimated counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1', 'cu_changes', '', [ 1500, 1500 ], [],
				[ 'total' => 1500, 'by_this_target' => 1500 ]
			],
			'IPv4, over 1000 estimated actions, and unequal estimated counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1', 'cu_changes', '', [ 1500, 1200 ], [],
				[ 'total' => 1500, 'by_this_target' => 1200 ]
			],
			'IPv4, over 1000 estimated actions, cu_private_event table, and unequal estimated counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1', 'cu_private_event', '', [ 1500, 1200 ], [],
				[ 'total' => 1500, 'by_this_target' => 1200 ]
			],
			'IPv4 range, under 1000 estimated actions, and unequal counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1/24', 'cu_changes', '', [ 500, 500 ], [ 456, 345 ],
				[ 'total' => 456, 'by_this_target' => 345 ]
			],
			'IPv4 range, under 1000 estimated actions, cu_log_event table, and unequal counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1/20', 'cu_log_event', '', [ 500, 500 ], [ 456, 345 ],
				[ 'total' => 456, 'by_this_target' => 345 ]
			],
			'IPv6, under 1000 estimated actions, unequal counts, and reading new' => [
				SCHEMA_COMPAT_READ_NEW, '::', 'cu_changes', '', [ 500, 500 ], [ 456, 345 ],
				[ 'total' => 456, 'by_this_target' => 345 ]
			],
			'IPv4 range, under 1000 estimated actions, start offset, and unequal counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1/20', 'cu_log_event', ConvertibleTimestamp::now(), [ 500, 500 ],
				[ 456, 345 ], [ 'total' => 456, 'by_this_target' => 345 ]
			],
			'IPv4, over 1000 estimated actions, cu_private_event table, start offset, and unequal counts' => [
				SCHEMA_COMPAT_READ_OLD, '127.0.0.1', 'cu_private_event', ConvertibleTimestamp::now(), [ 1500, 1200 ],
				[], [ 'total' => 1500, 'by_this_target' => 1200 ]
			],
		];
	}

	/** @dataProvider provideGroupResultsByIndexField */
	public function testGroupResultsByIndexField( $results, $expectedReturnResults ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertArrayEquals(
			$expectedReturnResults,
			$objectUnderTest->groupResultsByIndexField( $results ),
			true,
			true,
			'Return result of ::groupResultsByIndexField was not as expected.'
		);
	}

	public static function provideGroupResultsByIndexField() {
		$currentTimestamp = ConvertibleTimestamp::now();
		return [
			'One IP' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					]
				],
				[
					$currentTimestamp => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					] ]
				]
			],
			'One IPs that is repeated' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 34,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 46,
						'first' => '20220903094043',
						'last' => $currentTimestamp,
					] ]
				]
			],
			'Multiple IPs with repeated IPs' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => '20231004094043',
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '20231004094042',
					],
					(object)[
						'ip' => '127.0.0.2',
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => null,
					],
					(object)[
						'ip' => 'fd12:3456:789a:1::',
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 123,
						'first' => '20221004094043',
						'last' => '20231004094043'
					],
					(object)[
						'ip' => 'fd12:3456:789a:1::',
						'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
						'count' => 12,
						'first' => '20211004094043',
						'last' => '20231004104043'
					],
					(object)[
						'ip' => '125.3.4.5',
						'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
						'count' => 11,
						'first' => '20211004094043',
						'last' => '20231004104043'
					]
				],
				[
					'20231004094043' => [ (object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 25,
						'first' => '20210903094043',
						'last' => '20231004094043',
					] ],
					'' => [ (object)[
						'ip' => '127.0.0.2',
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 13,
						'first' => '20210903094043',
						'last' => '',
					] ],
					'20231004104043' => [
						(object)[
							'ip' => 'fd12:3456:789a:1::',
							'ip_hex' => IPUtils::toHex( 'fd12:3456:789a:1::' ),
							'count' => 135,
							'first' => '20211004094043',
							'last' => '20231004104043'
						],
						(object)[
							'ip' => '125.3.4.5',
							'ip_hex' => IPUtils::toHex( '125.3.4.5' ),
							'count' => 11,
							'first' => '20211004094043',
							'last' => '20231004104043'
						],
					]
				]
			],
			'Same IP but different hex' => [
				[
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 12,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
						'count' => 1,
						'first' => null,
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						// Deliberately 127.0.0.2 to cause a different hex and properly test grouping.
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 34,
						'first' => '20220904094043',
						'last' => $currentTimestamp,
					],
					(object)[
						'ip' => '127.0.0.1',
						// Deliberately 127.0.0.2 to cause a different hex and properly test grouping.
						'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
						'count' => 0,
						'first' => '',
						'last' => $currentTimestamp,
					],
				],
				[
					$currentTimestamp => [
						(object)[
							'ip' => '127.0.0.1',
							'ip_hex' => IPUtils::toHex( '127.0.0.1' ),
							'count' => 13,
							'first' => '20220904094043',
							'last' => $currentTimestamp,
						],
						(object)[
							'ip' => '127.0.0.1',
							'ip_hex' => IPUtils::toHex( '127.0.0.2' ),
							'count' => 34,
							'first' => '20220904094043',
							'last' => $currentTimestamp,
						],
					]
				]
			]
		];
	}
}
