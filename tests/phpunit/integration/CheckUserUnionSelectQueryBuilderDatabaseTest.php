<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserUnionSelectQueryBuilder
 *
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder
 */
class CheckUserUnionSelectQueryBuilderDatabaseTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_changes',
			'cu_private_event',
			'cu_log_event',
			'user',
			'logging',
			'actor',
			'recentchanges'
		];
	}

	/**
	 * @param bool $needsCommentJoin
	 * @param bool $needsActorJoin
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject( bool $needsCommentJoin = true, bool $needsActorJoin = true ) {
		$object = TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->get( 'CheckUserUnionSelectQueryBuilderFactory' )
				->newCheckUserSelectQueryBuilder( $this->getDb() )
		);
		if ( $needsCommentJoin ) {
			$object->needsCommentJoin();
		}
		if ( $needsActorJoin ) {
			$object->needsActorJoin();
		}
		return $object;
	}

	protected function insertTestData( $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps ) {
		$expectedRow = [];
		foreach ( $cuChangesTimestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			// Insertion into cu_changes
			$this->commonTestsUpdateCheckUserData( self::getDefaultRecentChangeAttribs(), [], $expectedRow );
		}
		foreach ( $cuLogEventTimestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			// Insertion into cu_log_event
			$logId = $this->newLogEntry();
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_logid' => $logId ] ),
				[],
				$expectedRow
			);
		}
		foreach ( $cuPrivateEventTimestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			// Insertion into cu_private_event
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
		}
	}

	protected function commonTestQuery(
		$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
	) {
		$this->setMwGlobals( [
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_NEW
		] );
		$this->insertTestData( $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps );
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have not been added yet.'
		);
		$object
			->fields( 'timestamp' )
			->subQueryFields( 'timestamp' )
			->subQueryLimit( $limit )
			->limit( $limit )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->subQueryOrderBy( $object->generateTableSpecificArgumentList(
				'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp'
			), SelectQueryBuilder::SORT_DESC );
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have not been added yet.'
		);
		$expectedTimestamps = array_merge( $cuChangesTimestamps, $cuPrivateEventTimestamps, $cuLogEventTimestamps );
		rsort( $expectedTimestamps );
		return array_slice( $expectedTimestamps, 0, $limit );
	}

	public static function provideTestQuery() {
		$currentTime = time();
		return [
			'4 entries in each table with default LIMIT' => [
				[ $currentTime - 2, $currentTime - 100, $currentTime - 52, $currentTime - 10 ],
				[ $currentTime - 3, $currentTime - 101, $currentTime - 50, $currentTime - 11 ],
				[ $currentTime - 4, $currentTime - 102, $currentTime - 51, $currentTime - 12 ],
				true, true, 5000
			],
			'4 entries in each table with default LIMIT without actor and comment' => [
				[ $currentTime - 2, $currentTime - 100, $currentTime - 52, $currentTime - 10 ],
				[ $currentTime - 3, $currentTime - 101, $currentTime - 50, $currentTime - 11 ],
				[ $currentTime - 4, $currentTime - 102, $currentTime - 51, $currentTime - 12 ],
				false, false, 5000
			],
			'Two entries in each table with LIMIT of 3' => [
				[ $currentTime - 2, $currentTime - 100, ],
				[ $currentTime - 3, $currentTime - 101, ],
				[ $currentTime - 4, $currentTime - 102, ],
				true, true, 3
			],
		];
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testFetchResultSet(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$object = $this->setUpObject( $needsCommentQuery, $needsActorQuery );
		$expectedTimestamps = $this->commonTestQuery(
			$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
		);
		$result = $object->fetchResultSet();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$data = [];
		foreach ( $result as $row ) {
			$data[] = ConvertibleTimestamp::convert( TS_UNIX, $row->timestamp );
		}
		$this->assertArrayEquals(
			$expectedTimestamps,
			$data,
			true,
			true,
			'The fetched result was not returned correctly.'
		);
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testFetchField(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$object = $this->setUpObject( $needsCommentQuery, $needsActorQuery );
		$expectedTimestamps = $this->commonTestQuery(
			$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
		);
		$result = $object->fetchField();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$this->assertSame(
			$expectedTimestamps[0],
			intval( ConvertibleTimestamp::convert( TS_UNIX, $result ) ),
			'The fetched result was not returned correctly.'
		);
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testFetchFieldValues(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$object = $this->setUpObject( $needsCommentQuery, $needsActorQuery );
		$expectedTimestamps = $this->commonTestQuery(
			$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
		);
		$result = $object->fetchFieldValues();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$result = array_map( static function ( $timestamp ) {
			return intval( ConvertibleTimestamp::convert( TS_UNIX, $timestamp ) );
		}, $result );
		$this->assertArrayEquals(
			$expectedTimestamps,
			$result,
			true,
			true,
			'The fetched result was not returned correctly.'
		);
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testFetchRow(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$object = $this->setUpObject( $needsCommentQuery, $needsActorQuery );
		$expectedTimestamps = $this->commonTestQuery(
			$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
		);
		$result = $object->fetchRow();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$this->assertSame(
			$expectedTimestamps[0],
			intval( ConvertibleTimestamp::convert( TS_UNIX, $result->timestamp ) ),
			'The fetched timestamp was not correct.'
		);
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testFetchRowCount(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$object = $this->setUpObject( $needsCommentQuery, $needsActorQuery );
		$expectedTimestamps = $this->commonTestQuery(
			$object, $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $limit
		);
		$result = $object->fetchRowCount();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$this->assertCount(
			$result,
			$expectedTimestamps,
			'The fetched row count was incorrect.'
		);
	}

	/**
	 * @dataProvider provideTestQuery
	 */
	public function testSelectingAllFields(
		$cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps, $needsActorQuery, $needsCommentQuery,
		$limit
	) {
		$this->setMwGlobals( [
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_NEW
		] );
		$object = $this->setUpObject();
		$this->insertTestData( $cuChangesTimestamps, $cuLogEventTimestamps, $cuPrivateEventTimestamps );
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have not been added yet.'
		);
		$object
			->fields( null )
			->subQueryFields( null )
			->subQueryLimit( $limit )
			->limit( $limit )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->needsActorJoin()
			->needsCommentJoin()
			->subQueryOrderBy( $object->generateTableSpecificArgumentList(
				'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp'
			), SelectQueryBuilder::SORT_DESC );
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have not been added yet.'
		);
		$expectedTimestamps = array_merge( $cuChangesTimestamps, $cuPrivateEventTimestamps, $cuLogEventTimestamps );
		rsort( $expectedTimestamps );
		$expectedTimestamps = array_slice( $expectedTimestamps, 0, $limit );
		$result = $object->fetchResultSet();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed once the query is done.'
		);
		$this->assertCount(
			$result->count(),
			$expectedTimestamps,
			'Rows are missing from the result of the query.'
		);
		foreach ( $result as $i => $row ) {
			// Check that results were returned in the correct order.
			$rowTimestampAsUnix = intval( ConvertibleTimestamp::convert( TS_UNIX, $row->timestamp ) );
			$this->assertSame(
				$expectedTimestamps[$i],
				$rowTimestampAsUnix,
				'The fetched timestamp was not correct.'
			);
			// Check that the row came from the correct table
			//  based on values that are only for rows from that table
			if ( $row->type == RC_LOG ) {
				if ( $row->log_id === null ) {
					// This timestamp should exist in the cuPrivateEventTimestamps array
					$this->assertContains(
						$rowTimestampAsUnix, $cuPrivateEventTimestamps,
						'Row should have come from the cu_private_event table'
					);
				} else {
					$this->assertContains(
						$rowTimestampAsUnix, $cuLogEventTimestamps,
						'Row should have come from the cu_log_event table'
					);
				}
			} else {
				$this->assertContains(
					$rowTimestampAsUnix, $cuChangesTimestamps,
					'Row should have come from the cu_changes table'
				);
			}
		}
	}
}
