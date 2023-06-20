<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use IDatabase;
use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CommentStore\CommentStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\DatabasePostgres;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilder
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder
 */
class CheckUserUnionSelectQueryBuilderTest extends MediaWikiUnitTestCase {

	// Make a copy to reduce the number of characters used to specify a table
	public const PRIVATE_LOG_EVENT_TABLE = CheckUserUnionSelectQueryBuilder::PRIVATE_LOG_EVENT_TABLE;
	public const LOG_EVENT_TABLE = CheckUserUnionSelectQueryBuilder::LOG_EVENT_TABLE;
	public const CHANGES_TABLE = CheckUserUnionSelectQueryBuilder::CHANGES_TABLE;
	public const UNION_TABLES = CheckUserUnionSelectQueryBuilder::UNION_TABLES;

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			( new CheckUserUnionSelectQueryBuilderFactory( $this->createMock( CommentStore::class ) ) )
				->newCheckUserSelectQueryBuilder( $this->createMock( IDatabase::class ) )
		);
	}

	/**
	 * @covers ::fillTableSpecificArgument
	 * @dataProvider provideFillTableSpecificArgument
	 */
	public function testFillTableSpecificArgument( $argument, $expectedReturnValue ) {
		$this->assertArrayEquals(
			$expectedReturnValue, $this->setUpObject()->fillTableSpecificArgument( $argument ),
			false, true, 'The table specific argument was not filled correctly.'
		);
	}

	public static function provideFillTableSpecificArgument() {
		return [
			'Argument value as null' => [ null, [
				self::CHANGES_TABLE => null, self::LOG_EVENT_TABLE => null, self::PRIVATE_LOG_EVENT_TABLE => null
			] ],
			'Argument value as a string' => [ 'test', [
				self::CHANGES_TABLE => 'test', self::LOG_EVENT_TABLE => 'test', self::PRIVATE_LOG_EVENT_TABLE => 'test',
			] ],
			'Argument value as an array' => [ [ 'test' ], [
				self::CHANGES_TABLE => [ 'test' ], self::LOG_EVENT_TABLE => [ 'test' ],
				self::PRIVATE_LOG_EVENT_TABLE => [ 'test' ],
			] ],
			'Argument value specifying value for one table' => [
				[ self::CHANGES_TABLE => 'test' ], [ self::CHANGES_TABLE => 'test' ]
			],
			'Argument value specifying value for all three tables' => [
				[
					self::CHANGES_TABLE => [ 'test' ], self::LOG_EVENT_TABLE => 'test',
					self::PRIVATE_LOG_EVENT_TABLE => 123,
				],
				[
					self::CHANGES_TABLE => [ 'test' ], self::LOG_EVENT_TABLE => 'test',
					self::PRIVATE_LOG_EVENT_TABLE => 123,
				]
			]
		];
	}

	/**
	 * @covers ::getEmptyTableSpecificArgumentList
	 */
	public function testGetEmptyTableSpecificArgumentList() {
		$this->assertArrayEquals(
			[ self::CHANGES_TABLE => [], self::LOG_EVENT_TABLE => [], self::PRIVATE_LOG_EVENT_TABLE => [] ],
			$this->setUpObject()->getEmptyTableSpecificArgumentList(),
			false, true, 'The empty table specific argument list was not generated correctly'
		);
	}

	/**
	 * @covers ::generateTableSpecificArgumentList
	 * @dataProvider provideGenerateTableSpecificArgumentList
	 */
	public function testGenerateTableSpecificArgumentList( $cuChangesValue, $cuLogEventValue, $cuPrivateEventValue ) {
		$this->assertArrayEquals(
			[
				self::CHANGES_TABLE => $cuChangesValue,
				self::LOG_EVENT_TABLE => $cuLogEventValue,
				self::PRIVATE_LOG_EVENT_TABLE => $cuPrivateEventValue
			],
			$this->setUpObject()->generateTableSpecificArgumentList(
				$cuChangesValue, $cuLogEventValue, $cuPrivateEventValue
			),
			false, true, 'The table specific argument list was not generated correctly'
		);
	}

	public static function provideGenerateTableSpecificArgumentList() {
		return [
			'String values' => [ 'test', 'testing', 'test2' ],
			'Array values' => [ [ 'testing' ], [ 1, 2 ], [ 'testing', 'test' ] ]
		];
	}

	/**
	 * @covers ::resetSubQueryForFutureChanges
	 */
	public function testResetSubQueryForFutureChanges() {
		$object = $this->setUpObject();
		$object->tables = [ 'test' => 'cu_changes', CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS => 'removed' ];
		$object->resetSubQueryForFutureChanges();
		$this->assertArrayNotHasKey(
			CheckUserUnionSelectQueryBuilder::UNION_SELECT_ALIAS,
			$object->tables,
			'The UNION should have been removed to allow future changes.'
		);
		$this->assertArrayEquals(
			[ 'test' => 'cu_changes' ], $object->tables, false, false,
			'The reset method should have only removed the UNION query.'
		);
	}

	/**
	 * @covers ::markUnusedFieldsAsNull
	 * @dataProvider provideMarkUnusedFieldsAsNull
	 */
	public function testMarkUnusedFieldsAsNull( $inputFields, $expectedFields ) {
		$this->assertArrayEquals(
			$expectedFields, $this->setUpObject()->markUnusedFieldsAsNull( $inputFields ), true, true,
			'Fields were not properly NULLed when unused for a particular table.'
		);
	}

	public static function provideMarkUnusedFieldsAsNull() {
		return [
			'One item with string key' => [ [ 'test' => 'testing' ], [ 'test' => 'Null' ] ],
			'One item with numeric key' => [ [ 'test' ], [ 'test' => 'Null' ] ],
			'Multiple items with numeric and string keys' => [
				[ 'test', 'testing' => 'test1', 'testing1234', 'last' => 'COUNT(*)' ],
				[ 'test' => 'Null', 'testing' => 'Null', 'testing1234' => 'Null', 'last' => 'Null' ]
			]
		];
	}

	/**
	 * @covers ::markUnusedFieldsAsNull
	 * @dataProvider provideMarkUnusedFieldsAsNullForPostgres
	 */
	public function testMarkUnusedFieldsAsNullForPostgres( $inputFields, $postgresType, $expectedFields ) {
		$object = $this->setUpObject();
		$mockDb = $this->createMock( DatabasePostgres::class );
		$mockDb
			->method( 'getType' )
			->willReturn( 'postgres' );
		$object->db = $mockDb;
		$this->assertArrayEquals(
			$expectedFields, $object->markUnusedFieldsAsNull( $inputFields, $postgresType ),
			true, true, 'Fields were not properly NULLed when unused for a particular table.'
		);
	}

	public static function provideMarkUnusedFieldsAsNullForPostgres() {
		return [
			'One item with string key and no type' => [ [ 'test' => 'testing' ], null, [ 'test' => 'Null' ] ],
			'One item with numeric key and no type' => [ [ 'test' ], null, [ 'test' => 'Null' ] ],
			'One item with string key and int type' => [
				[ 'test' => 'testing' ], 'int', [ 'test' => 'CAST(Null AS int)' ]
			],
			'One item with numeric key and text type' => [ [ 'test' ], 'text', [ 'test' => 'CAST(Null AS text)' ] ],
			'Multiple items with numeric and string keys' => [
				[ 'test', 'testing' => 'test1', 'testing1234', 'last' => 'COUNT(*)' ],
				null,
				[ 'test' => 'Null', 'testing' => 'Null', 'testing1234' => 'Null', 'last' => 'Null' ]
			],
			'Multiple items with numeric and string keys with int type' => [
				[ 'test', 'testing' => 'test1', 'testing1234', 'last' => 'COUNT(*)' ],
				'int',
				[
					'test' => 'CAST(Null AS int)', 'testing' => 'CAST(Null AS int)',
					'testing1234' => 'CAST(Null AS int)', 'last' => 'CAST(Null AS int)'
				]
			]
		];
	}

	/**
	 * @covers ::getSelectFieldsForTable
	 * @dataProvider provideTableSpecificSubQueryFields
	 */
	public function testGetSelectFieldsForTable( $fields, $expectedReturnValues ) {
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedReturnValues[$table], $this->setUpObject()->getSelectFieldsForTable( $table, $fields ),
				true, true,
				'getSelectFieldsForTable() did not return the correct fields for the ' . $table . ' table.'
			);
		}
	}

	public static function provideTableSpecificSubQueryFields() {
		return [
			'Mixture of alias and non-alias fields' => [
				[
					self::CHANGES_TABLE => [ 'COUNT(*)', 'timestamp', 'first' => 'MIN(cuc_timestamp)' ],
					self::LOG_EVENT_TABLE => [ 'COUNT(*)', 'timestamp', 'first' => 'MIN(cule_timestamp)' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'COUNT(*)', 'timestamp', 'first' => 'MIN(cupe_timestamp)' ],
				],
				[
					self::CHANGES_TABLE => [
						'COUNT(*)', 'timestamp' => 'cuc_timestamp', 'first' => 'MIN(cuc_timestamp)'
					],
					self::LOG_EVENT_TABLE => [
						'COUNT(*)', 'timestamp' => 'cule_timestamp', 'first' => 'MIN(cule_timestamp)'
					],
					self::PRIVATE_LOG_EVENT_TABLE => [
						'COUNT(*)', 'timestamp' => 'cupe_timestamp', 'first' => 'MIN(cupe_timestamp)'
					],
				]
			]
		];
	}

	/**
	 * @covers ::getSelectFieldsForTable
	 */
	public function testGetSelectFieldsForTableWhenFieldsAreNull() {
		$expectedReturnValues = [
			self::CHANGES_TABLE => $this->setUpObject()->getAllSelectFields( self::CHANGES_TABLE ),
			self::LOG_EVENT_TABLE => $this->setUpObject()->getAllSelectFields( self::LOG_EVENT_TABLE ),
			self::PRIVATE_LOG_EVENT_TABLE => $this->setUpObject()->getAllSelectFields(
				self::PRIVATE_LOG_EVENT_TABLE
			),
		];
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedReturnValues[$table], $this->setUpObject()->getSelectFieldsForTable( $table, [
				self::CHANGES_TABLE => null,
				self::LOG_EVENT_TABLE => null,
				self::PRIVATE_LOG_EVENT_TABLE => null
			] ),
				true, true,
				'getSelectFieldsForTable() did not return the correct fields for the ' . $table . ' table.'
			);
		}
	}

	/**
	 * @covers ::fields
	 * @dataProvider provideFields
	 */
	public function testFields( $fieldsValue, $expectedFieldsValue ) {
		$object = $this->setUpObject();
		$object->fields( $fieldsValue );
		$this->assertArrayEquals(
			$expectedFieldsValue,
			$object->fields,
			true,
			true,
			'Fields were not added correctly.'
		);
	}

	public static function provideFields() {
		return [
			'Fields value as one field' => [ 'test', [ 'test' ] ],
			'Fields value as array' => [ [ 'timestamp', 'actor_name' ], [ 'timestamp', 'actor_name' ] ]
		];
	}

	/**
	 * @covers ::fields
	 */
	public function testFieldsWhereArgumentIsNull() {
		$this->testFields(
			null,
			array_keys( $this->setUpObject()->getAllSelectFields( self::CHANGES_TABLE ) )
		);
	}

	/**
	 * @covers ::subQueryWhere
	 * @dataProvider provideSubQueryWhere
	 */
	public function testSubQueryWhere( $argument, $expectedConditions ) {
		$object = $this->setUpObject();
		$object->subQueryWhere( $argument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedConditions[$table],
				TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->conds,
				true,
				true,
				'The fields in the ' . $table . ' table were not as expected.'
			);
		}
	}

	public static function provideSubQueryWhere() {
		return [
			'String condition for all tables' => [
				'test = 1',
				[
					self::CHANGES_TABLE => [ 'test = 1' ], self::LOG_EVENT_TABLE => [ 'test = 1' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'test = 1' ]
				]
			],
			'Array conditions for all tables' => [
				[ 'test' => 1 ],
				[
					self::CHANGES_TABLE => [ 'test' => 1 ], self::LOG_EVENT_TABLE => [ 'test' => 1 ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'test' => 1 ]
				]
			],
			'Only specifying some tables' => [
				[ self::CHANGES_TABLE => [ 'test' => 1 ], self::PRIVATE_LOG_EVENT_TABLE => [ 'test' => 3 ] ],
				[
					self::CHANGES_TABLE => [ 'test' => 1 ], self::LOG_EVENT_TABLE => [],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'test' => 3 ]
				]
			]
		];
	}

	/**
	 * @covers ::subQueryWhere
	 * @dataProvider provideSubQueryWhereWithTableSpecificArgument
	 */
	public function testSubQueryWhereWithTableSpecificArgument( $argumentAndExpectedValue ) {
		$this->testSubQueryWhere( $argumentAndExpectedValue, $argumentAndExpectedValue );
	}

	public static function provideSubQueryWhereWithTableSpecificArgument() {
		return [
			'One condition' => [ [
				self::CHANGES_TABLE => [ 'test' => 1 ], self::LOG_EVENT_TABLE => [ 'test' => 2 ],
				self::PRIVATE_LOG_EVENT_TABLE => [ 'test' => 3 ]
			] ],
			'Multiple conditions' => [ [
				self::CHANGES_TABLE => [ 'cuc_timestamp' => 1 ], self::LOG_EVENT_TABLE => [ 'cule_log_id' => 1 ],
				self::PRIVATE_LOG_EVENT_TABLE => [ 'cupe_title' => 'test', 'test = 1' ]
			] ]
		];
	}

	/**
	 * @covers ::subQueryOptions
	 * @dataProvider provideSubQueryOptions
	 */
	public function testSubQueryOptions( $optionsArgument, $expectedOptions ) {
		$object = $this->setUpObject();
		$object->subQueryOptions( $optionsArgument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedOptions[$table],
				TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->options,
				true, true, 'The options for the ' . $table . ' table was not as expected.'
			);
		}
	}

	public static function provideSubQueryOptions() {
		return [
			'Only specifying one table' => [
				[ self::CHANGES_TABLE => [ 'USE INDEX' => 'cuc_ip_hex_time' ] ],
				[
					self::CHANGES_TABLE => [ 'USE INDEX' => 'cuc_ip_hex_time' ],
					self::LOG_EVENT_TABLE => [], self::PRIVATE_LOG_EVENT_TABLE => []
				]
			],
			'Specifying for all tables' => [
				[
					self::CHANGES_TABLE => [ 'GROUP BY' => [ 'cuc_actor' ] ],
					self::LOG_EVENT_TABLE => [ 'GROUP BY' => [ 'cule_log_id' ] ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'GROUP BY' => [ 'cupe_actor' ] ]
				],
				[
					self::CHANGES_TABLE => [ 'GROUP BY' => [ 'cuc_actor' ] ],
					self::LOG_EVENT_TABLE => [ 'GROUP BY' => [ 'cule_log_id' ] ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'GROUP BY' => [ 'cupe_actor' ] ]
				]
			],
		];
	}

	/**
	 * @covers ::subQueryLimit
	 * @dataProvider provideSubQueryLimit
	 */
	public function testSubQueryLimit( $limitArgument, $expectedLimit ) {
		$object = $this->setUpObject();
		$object->subQueryLimit( $limitArgument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertSame(
				$expectedLimit[$table],
				TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->options['LIMIT'],
				'The LIMIT for the ' . $table . ' table was not as expected.'
			);
		}
	}

	public static function provideSubQueryLimit() {
		return [
			'Non-table specific' => [
				123,
				[ self::CHANGES_TABLE => 123, self::LOG_EVENT_TABLE => 123, self::PRIVATE_LOG_EVENT_TABLE => 123 ]
			]
		];
	}

	/**
	 * @covers ::subQueryUseIndex
	 * @dataProvider provideSubQueryUseIndex
	 */
	public function testSubQueryUseIndex( $useIndexArgument, $expectedUseIndexValue ) {
		$object = $this->setUpObject();
		$object->subQueryUseIndex( $useIndexArgument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$actualLimitValue = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->options;
			if ( $expectedUseIndexValue[$table] !== null ) {
				$actualLimitValue = $actualLimitValue['USE INDEX'];
			} else {
				$expectedUseIndexValue[$table] = [];
			}
			if ( is_array( $expectedUseIndexValue[$table] ) ) {
				$this->assertArrayEquals(
					$expectedUseIndexValue[$table], $actualLimitValue, true, true,
					'The USE INDEX for the ' . $table . ' table was not as expected.'
				);
			} else {
				$this->assertSame(
					$expectedUseIndexValue[$table], $actualLimitValue,
					'The USE INDEX for the ' . $table . ' table was not as expected.'
				);
			}
		}
	}

	public static function provideSubQueryUseIndex() {
		return [
			'Only specifying one table' => [
				[ self::CHANGES_TABLE => 'cuc_ip_hex_time' ],
				[
					self::CHANGES_TABLE => [ self::CHANGES_TABLE => 'cuc_ip_hex_time' ],
					self::LOG_EVENT_TABLE => null,
					self::PRIVATE_LOG_EVENT_TABLE => null
				]
			],
			'Specifying for all tables' => [
				[
					self::CHANGES_TABLE => 'cuc_ip_hex_time', self::LOG_EVENT_TABLE => 'cule_ip_hex_time',
					self::PRIVATE_LOG_EVENT_TABLE => 'cupe_ip_hex_time'
				],
				[
					self::CHANGES_TABLE => [ self::CHANGES_TABLE => 'cuc_ip_hex_time' ],
					self::LOG_EVENT_TABLE => [ self::LOG_EVENT_TABLE => 'cule_ip_hex_time' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ self::PRIVATE_LOG_EVENT_TABLE => 'cupe_ip_hex_time' ]
				]
			],
		];
	}

	/**
	 * @covers ::subQueryOrderBy
	 * @dataProvider provideSubQueryOrderBy
	 */
	public function testSubQueryOrderBy( $orderByArgument, $directionArgument, $expectedOrderByValue ) {
		$object = $this->setUpObject();
		$object->subQueryOrderBy( $orderByArgument, $directionArgument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$actualLimitValue = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->options;
			if ( $expectedOrderByValue[$table] !== null ) {
				$actualLimitValue = $actualLimitValue['ORDER BY'];
			} else {
				$expectedOrderByValue[$table] = [];
			}
			if ( is_array( $expectedOrderByValue[$table] ) ) {
				$this->assertArrayEquals(
					$expectedOrderByValue[$table], $actualLimitValue, true, true,
					'The ORDER BY value for the ' . $table . ' table was not as expected.'
				);
			} else {
				$this->assertSame(
					$expectedOrderByValue[$table], $actualLimitValue,
					'The ORDER BY value for the ' . $table . ' table was not as expected.'
				);
			}
		}
	}

	public static function provideSubQueryOrderBy() {
		return [
			'Non-table specific with null direction' => [
				'timestamp', null,
				[
					self::CHANGES_TABLE => [ 'timestamp' ], self::LOG_EVENT_TABLE => [ 'timestamp' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp' ]
				]
			],
			'Only specifying one table with null direction' => [
				[ self::CHANGES_TABLE => 'timestamp' ],
				null,
				[
					self::CHANGES_TABLE => [ 'timestamp' ],
					self::LOG_EVENT_TABLE => null, self::PRIVATE_LOG_EVENT_TABLE => null
				]
			],
			'Specifying for all tables with null direction' => [
				[
					self::CHANGES_TABLE => 'timestamp', self::LOG_EVENT_TABLE => 'log_id',
					self::PRIVATE_LOG_EVENT_TABLE => 'timestamp'
				],
				null,
				[
					self::CHANGES_TABLE => [ 'timestamp' ], self::LOG_EVENT_TABLE => [ 'log_id' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp' ]
				]
			],
			'Non-table specific with SORT_ASC direction' => [
				'timestamp', SelectQueryBuilder::SORT_ASC,
				[
					self::CHANGES_TABLE => [ 'timestamp ASC' ], self::LOG_EVENT_TABLE => [ 'timestamp ASC' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp ASC' ]
				]
			],
			'Specifying for all tables with SORT_ASC direction' => [
				[
					self::CHANGES_TABLE => 'timestamp', self::LOG_EVENT_TABLE => 'log_id',
					self::PRIVATE_LOG_EVENT_TABLE => 'timestamp'
				],
				SelectQueryBuilder::SORT_ASC,
				[
					self::CHANGES_TABLE => [ 'timestamp ASC' ], self::LOG_EVENT_TABLE => [ 'log_id ASC' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp ASC' ]
				]
			],
			'Specifying for all tables with SORT_DESC direction and multiple fields' => [
				[
					self::CHANGES_TABLE => [ 'timestamp', 'title' ], self::LOG_EVENT_TABLE => [ 'timestamp', 'log_id' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp', 'namespace' ]
				],
				SelectQueryBuilder::SORT_DESC,
				[
					self::CHANGES_TABLE => [ 'timestamp DESC', 'title DESC' ],
					self::LOG_EVENT_TABLE => [ 'timestamp DESC', 'log_id DESC' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp DESC', 'namespace DESC' ]
				]
			],
		];
	}

	/**
	 * @covers ::needsActorJoin
	 */
	public function testNeedsActorJoin() {
		$object = $this->setUpObject();
		foreach ( self::UNION_TABLES as $table ) {
			$subQueryObject = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] );
			$subQueryObject->lastAlias = 'testing';
			$this->assertArrayNotHasKey(
				$table . '_actor',
				$subQueryObject->tables,
				'Actor JOIN should not occur until needsActorJoin has been called.'
			);
		}
		$object->needsActorJoin();
		foreach ( self::UNION_TABLES as $table ) {
			$subQueryObject = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] );
			$this->assertSame(
				'testing',
				$subQueryObject->lastAlias,
				'The lastAlias value was changed by the needsActorJoin() call.'
			);
			$this->assertArrayHasKey(
				$table . '_actor',
				$subQueryObject->tables,
				'needsActorJoin has been called so the actor JOIN should have been applied to the subqueries.'
			);
		}
	}
}
