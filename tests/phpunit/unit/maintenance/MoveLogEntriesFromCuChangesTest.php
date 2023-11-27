<?php

namespace MediaWiki\CheckUser\Tests\Unit\Maintenance;

use LogEntryBase;
use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\Config\HashConfig;
use MediaWiki\MediaWikiServices;
use MediaWikiUnitTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\UpdateQueryBuilder;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesTest extends MediaWikiUnitTestCase {

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( '20231127123058' );
	}

	public function testGetUpdateKey() {
		$objectUnderTest = TestingAccessWrapper::newFromObject( new MoveLogEntriesFromCuChanges() );
		$this->assertSame(
			'MediaWiki\\CheckUser\\Maintenance\\MoveLogEntriesFromCuChanges',
			$objectUnderTest->getUpdateKey(),
			'::getUpdateKey did not return the expected value.'
		);
	}

	/** @dataProvider provideInvalidMigrationStages */
	public function testDoDBUpdatesWithWrongMigrationStage( $migrationStage ) {
		// Mock the config so that the migration stage to test with is
		// set.
		$mockConfig = new HashConfig( [
			'CheckUserEventTablesMigrationStage' => $migrationStage
		] );
		// Mock the service container to return this config to a call to ::getMainConfig
		$mockServiceContainer = $this->createMock( MediaWikiServices::class );
		$mockServiceContainer->method( 'getMainConfig' )
			->willReturn( $mockConfig );
		// Get the object under test and make the getServiceContainer return the mock MediaWikiServices.
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getServiceContainer', 'output' ] )
			->getMock();
		$objectUnderTest->method( 'getServiceContainer' )
			->willReturn( $mockServiceContainer );
		// Expect that doDBUpdates returns false.
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertFalse(
			$objectUnderTest->doDBUpdates(),
			"::doDBUpdates should return false when the migration stage doesn't allow the script to run."
		);
	}

	public static function provideInvalidMigrationStages() {
		return [
			'Reading and writing old' => [ SCHEMA_COMPAT_OLD ],
			'Read new, reading old and writing old' => [ SCHEMA_COMPAT_READ_NEW | SCHEMA_COMPAT_OLD ],
		];
	}

	public function testDoDBUpdatesWithNoRowsInCuChanges() {
		// Mock the primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbwMock ) );
		// Expect that a query for the number of rows in cu_changes is made.
		$dbwMock->method( 'selectRowCount' )
			->with(
				[ 'cu_changes' ],
				'*',
				[],
				'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::doDBUpdates',
				[],
				[]
			)
			->willReturn( 0 );
		// Mock the config to use the migration stage that allows the use of the script (i.e.
		// includes write new).
		$mockConfig = new HashConfig( [
			'CheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_WRITE_NEW | SCHEMA_COMPAT_OLD
		] );
		// Mock the service container to return this config to a call to ::getMainConfig
		$mockServiceContainer = $this->createMock( MediaWikiServices::class );
		$mockServiceContainer->method( 'getMainConfig' )
			->willReturn( $mockConfig );
		// Get the object under test and make the getServiceContainer return the mock MediaWikiServices.
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getServiceContainer', 'getDB', 'output' ] )
			->getMock();
		$objectUnderTest->method( 'getServiceContainer' )
			->willReturn( $mockServiceContainer );
		$objectUnderTest
			->method( 'getDB' )
			->with( DB_PRIMARY )
			->willReturn( $dbwMock );
		// Expect that doDBUpdates returns true.
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertTrue(
			$objectUnderTest->doDBUpdates(),
			"::doDBUpdates should return true when the no rows are in cu_changes."
		);
	}

	/** @dataProvider provideMoveLogEntriesFromCuChanges */
	public function testMoveLogEntriesFromCuChanges(
		$minCucId, $maxCucId, $batchSize, $cuChangesLogRows, $expectedCuPrivateRows, $expectedBatches
	) {
		// Mock the DB.
		$dbwMock = $this->createMock( IDatabase::class );
		// Tell the query builders to use the mock DB when executing the query.
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbwMock ) );
		$dbwMock->method( 'newUpdateQueryBuilder' )->willReturnCallback( fn() => new UpdateQueryBuilder( $dbwMock ) );
		$dbwMock->method( 'newInsertQueryBuilder' )->willReturnCallback( fn() => new InsertQueryBuilder( $dbwMock ) );
		// Expect that select queries are made that get the minimum and maximum cuc_id
		// in the cu_changes table, and mock the return value to that provided via the
		// data provider.
		$dbwMock->method( 'selectField' )
			->withConsecutive(
				[
					[ 'cu_changes' ],
					'MIN(cuc_id)',
					[],
					'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::moveLogEntriesFromCuChanges',
					[],
					[]
				],
				[
					[ 'cu_changes' ],
					'MAX(cuc_id)',
					[],
					'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::moveLogEntriesFromCuChanges',
					[],
					[]
				]
			)->willReturnOnConsecutiveCalls( $minCucId, $maxCucId );
		// Expect that:
		// * Select queries are made to get the data to move from cu_changes
		// * Insert queries are made to add this data to cu_private_event
		// * Update queries are made to update the successfully moved data
		//   in cu_changes to only be used for read old.
		$dbwSelectWithConsecutive = [];
		$dbwSelectReturnConsecutive = [];
		$dbwInsertWithConsecutive = [];
		$dbwUpdateWithConsecutive = [];
		foreach ( $expectedBatches as $index => $batch ) {
			$dbwSelectWithConsecutive[] = [
				[ 'cu_changes' ],
				[
					'cuc_id',
					'cuc_namespace',
					'cuc_title',
					'cuc_actor',
					'cuc_actiontext',
					'cuc_comment_id',
					'cuc_page_id',
					'cuc_timestamp',
					'cuc_ip',
					'cuc_ip_hex',
					'cuc_xff',
					'cuc_xff_hex',
					'cuc_agent',
					'cuc_private'
				],
				[
					"cuc_id BETWEEN {$batch['start']} AND {$batch['end']}",
					'cuc_type' => RC_LOG,
					'cuc_only_for_read_old' => 0,
				],
				'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::moveLogEntriesFromCuChanges',
				[],
				[]
			];
			$dbwSelectReturnConsecutive[] = new FakeResultWrapper( $cuChangesLogRows[$index] );
			if ( count( $cuChangesLogRows[$index] ) ) {
				$dbwUpdateWithConsecutive[] = [
					'cu_changes',
					[ 'cuc_only_for_read_old' => 1 ],
					[
						'cuc_id' => array_map( static function ( $row ) {
							if ( is_array( $row ) ) {
								$row = (object)$row;
							}
							return $row->cuc_id;
						}, $cuChangesLogRows[$index] )
					],
					'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::moveLogEntriesFromCuChanges',
					[]
				];
			}
			if ( count( $expectedCuPrivateRows[$index] ) ) {
				$dbwInsertWithConsecutive[] = [
					'cu_private_event',
					$expectedCuPrivateRows[$index],
					'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::moveLogEntriesFromCuChanges',
					[]
				];
			}
		}
		// Expect that the SelectQueryBuilder::fetchResultSet is called.
		$dbwMock->expects( $this->exactly( count( $dbwSelectWithConsecutive ) ) )
			->method( 'select' )
			->withConsecutive( ...$dbwSelectWithConsecutive )
			->willReturnOnConsecutiveCalls( ...$dbwSelectReturnConsecutive );
		// Expect that the InsertQueryBuilder::execute method is called.
		$dbwMock->expects( $this->exactly( count( $dbwInsertWithConsecutive ) ) )
			->method( 'insert' )
			->withConsecutive( ...$dbwInsertWithConsecutive );
		// Expect that the UpdateQueryBuilder::execute method is called.
		$dbwMock->expects( $this->exactly( count( $dbwUpdateWithConsecutive ) ) )
			->method( 'update' )
			->withConsecutive( ...$dbwUpdateWithConsecutive );
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getDB', 'waitForReplication', 'output' ] )
			->getMock();
		$objectUnderTest
			->method( 'getDB' )
			->with( DB_PRIMARY )
			->willReturn( $dbwMock );
		// Expect that waitForReplication is called after each delete query
		$objectUnderTest->expects( $this->exactly( count( $expectedBatches ) ) )
			->method( 'waitForReplication' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->mBatchSize = $batchSize;
		// Assertions are checked by the mocks, their expectations and lack of mocks.
		$objectUnderTest->moveLogEntriesFromCuChanges();
	}

	public static function provideMoveLogEntriesFromCuChanges() {
		return [
			'Two delete batches with one item to move' => [
				1, 2, 100,
				[
					[ self::getCuChangesRow(
						[ 'cuc_xff' => '1.2.3.4', 'cuc_xff_hex' => IPUtils::toHex( '1.2.3.4' ) ]
					) ],
					// The way that batches work means that the last query will contain no rows.
					[],
				],
				[
					[ self::getExpectedCuPrivateRow(
						[ 'cupe_xff' => '1.2.3.4', 'cupe_xff_hex' => IPUtils::toHex( '1.2.3.4' ) ]
					) ],
					// The way that batches work means that the last query will contain no rows.
					[],
				],
				[
					[ 'start' => 1, 'end' => 100 ],
					[ 'start' => 100, 'end' => 199 ],
				]
			],
			'Four delete batches with last with no rows.' => [
				1, 230, 100,
				[
					[ self::getCuChangesRow() ],
					[ self::getCuChangesRow( [
						'cuc_ip' => '1.2.3.4',
						'cuc_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
						'cuc_title' => 'Testing1234',
						'cuc_namespace' => NS_TEMPLATE
					] ) ],
					[ self::getCuChangesRow( [ 'cuc_title' => 'Testing12345' ] ) ],
					[],
				],
				[
					[ self::getExpectedCuPrivateRow() ],
					[ self::getExpectedCuPrivateRow( [
						'cupe_ip' => '1.2.3.4',
						'cupe_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
						'cupe_title' => 'Testing1234',
						'cupe_namespace' => NS_TEMPLATE
					] ) ],
					[ self::getExpectedCuPrivateRow( [ 'cupe_title' => 'Testing12345' ] ) ],
					[],
				],
				[
					[ 'start' => 1, 'end' => 100 ],
					[ 'start' => 100, 'end' => 199 ],
					[ 'start' => 199, 'end' => 298 ],
					[ 'start' => 298, 'end' => 397 ],
				]
			],
			'Two delete batches starting at a ID greater than 1' => [
				15, 34, 43,
				[
					[ self::getCuChangesRow() ],
					[]
				],
				[
					[ self::getExpectedCuPrivateRow() ],
					[]
				],
				[
					[ 'start' => 15, 'end' => 57 ],
					[ 'start' => 57, 'end' => 99 ]
				],
			]
		];
	}

	/**
	 * Combines the items provided in the first argument array
	 * with defaults for cu_changes and returns the result.
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getCuChangesRow( array $row = [] ): array {
		// If modifying this, keep it consistent with ::getExpectedCuPrivateRow
		return array_merge( [
			'cuc_id' => 1,
			'cuc_namespace' => NS_MAIN,
			'cuc_title' => 'Test',
			'cuc_actor' => 2,
			'cuc_actiontext' => 'Testing',
			'cuc_comment_id' => 2,
			'cuc_page_id' => 3,
			'cuc_timestamp' => ConvertibleTimestamp::now(),
			'cuc_ip' => '127.0.0.1',
			'cuc_ip_hex' => IPUtils::toHex( '127.0.0.1' ),
			'cuc_xff' => '',
			'cuc_xff_hex' => '',
			'cuc_agent' => 'Testing',
			'cuc_private' => '',
		], $row );
	}

	/**
	 * Combines the items provided in the first argument array
	 * with defaults for cu_private_event and returns the result.
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getExpectedCuPrivateRow( array $row = [] ): array {
		// If modifying this, keep it consistent with ::getCuChangesRow
		return array_merge( [
			'cupe_namespace' => NS_MAIN,
			'cupe_title' => 'Test',
			'cupe_actor' => 2,
			'cupe_comment_id' => 2,
			'cupe_page' => 3,
			'cupe_log_action' => 'migrated-cu_changes-log-event',
			'cupe_log_type' => 'checkuser-private-event',
			'cupe_params' => LogEntryBase::makeParamBlob( [ '4::actiontext' => 'Testing' ] ),
			'cupe_timestamp' => ConvertibleTimestamp::now(),
			'cupe_ip' => '127.0.0.1',
			'cupe_ip_hex' => IPUtils::toHex( '127.0.0.1' ),
			'cupe_xff' => '',
			'cupe_xff_hex' => '',
			'cupe_agent' => 'Testing',
			'cupe_private' => '',
		], $row );
	}
}
