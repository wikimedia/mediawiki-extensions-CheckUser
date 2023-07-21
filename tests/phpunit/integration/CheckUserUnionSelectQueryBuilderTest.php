<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilder
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder
 */
class CheckUserUnionSelectQueryBuilderTest extends MediaWikiIntegrationTestCase {

	// Make a copy to reduce the number of characters used to specify a table
	public const PRIVATE_LOG_EVENT_TABLE = CheckUserUnionSelectQueryBuilder::PRIVATE_LOG_EVENT_TABLE;
	public const LOG_EVENT_TABLE = CheckUserUnionSelectQueryBuilder::LOG_EVENT_TABLE;
	public const CHANGES_TABLE = CheckUserUnionSelectQueryBuilder::CHANGES_TABLE;

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->get( 'CheckUserUnionSelectQueryBuilderFactory' )
				->newCheckUserSelectQueryBuilder( $this->getDb() )
		);
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
	 * @dataProvider provideSubQueryFields
	 */
	public function testGetSelectFieldsForTableWhenFieldsSameForAllTables( $fields, $expectedReturnValues ) {
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedReturnValues[$table], $this->setUpObject()->getSelectFieldsForTable( $table, [
				self::CHANGES_TABLE => $fields,
				self::LOG_EVENT_TABLE => $fields,
				self::PRIVATE_LOG_EVENT_TABLE => $fields
			] ),
				true, true,
				'getSelectFieldsForTable() did not return the correct fields for the ' . $table . ' table.'
			);
		}
	}

	public function provideNullValue( $postgresType ) {
		if ( $this->getDb()->getType() === 'postgres' ) {
			return 'CAST(Null AS ' . $postgresType . ')';
		} else {
			return 'Null';
		}
	}

	public function provideSubQueryFields() {
		return [
			'Only alias fields existing in all tables' => [
				[ 'timestamp', 'title', 'namespace' ],
				[
					self::CHANGES_TABLE => [
						'timestamp' => 'cuc_timestamp', 'title' => 'cuc_title', 'namespace' => 'cuc_namespace'
					],
					self::LOG_EVENT_TABLE => [
						'timestamp' => 'cule_timestamp', 'title' => 'log_title', 'namespace' => 'log_namespace'
					],
					self::PRIVATE_LOG_EVENT_TABLE => [
						'timestamp' => 'cupe_timestamp', 'title' => 'cupe_title', 'namespace' => 'cupe_namespace'
					],
				]
			],
			'Only alias fields' => [
				[ 'timestamp', 'log_id', 'log_params', 'minor' ],
				[
					self::CHANGES_TABLE => [
						'timestamp' => 'cuc_timestamp', 'log_id' => $this->provideNullValue( 'int' ),
						'log_params' => 'Null', 'minor' => 'cuc_minor'
					],
					self::LOG_EVENT_TABLE => [
						'timestamp' => 'cule_timestamp', 'log_id' => 'cule_log_id', 'log_params' => 'log_params',
						'minor' => $this->provideNullValue( 'smallint' )
					],
					self::PRIVATE_LOG_EVENT_TABLE => [
						'timestamp' => 'cupe_timestamp', 'log_id' => $this->provideNullValue( 'int' ),
						'log_params' => 'cupe_params', 'minor' => $this->provideNullValue( 'smallint' )
					],
				]
			],
			'One non-timestamp alias field' => [
				[ 'log_id' ],
				[
					self::CHANGES_TABLE => [
						'timestamp' => 'cuc_timestamp', 'log_id' => $this->provideNullValue( 'int' )
					],
					self::LOG_EVENT_TABLE => [ 'timestamp' => 'cule_timestamp', 'log_id' => 'cule_log_id' ],
					self::PRIVATE_LOG_EVENT_TABLE => [
						'timestamp' => 'cupe_timestamp', 'log_id' => $this->provideNullValue( 'int' )
					],
				]
			],
			'One alias field of timestamp' => [
				[ 'timestamp' ],
				[
					self::CHANGES_TABLE => [ 'timestamp' => 'cuc_timestamp' ],
					self::LOG_EVENT_TABLE => [ 'timestamp' => 'cule_timestamp' ],
					self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp' => 'cupe_timestamp' ],
				]
			],
			'Mixture of alias and non-alias fields' => [
				[ 'timestamp', 'COUNT(*)', 'first' => 'MIN(*)' ],
				[
					self::CHANGES_TABLE => [ 'timestamp' => 'cuc_timestamp', 'COUNT(*)', 'first' => 'MIN(*)' ],
					self::LOG_EVENT_TABLE => [ 'timestamp' => 'cule_timestamp', 'COUNT(*)', 'first' => 'MIN(*)' ],
					self::PRIVATE_LOG_EVENT_TABLE => [
						'timestamp' => 'cupe_timestamp', 'COUNT(*)', 'first' => 'MIN(*)'
					],
				]
			]
		];
	}

	/**
	 * @covers ::subQueryFields
	 * @dataProvider provideSubQueryFields
	 * @dataProvider provideTableSpecificSubQueryFields
	 */
	public function testSubQueryFields( $subQueryFieldsArgument, $expectedSubQueryFields ) {
		$object = $this->setUpObject();
		$object->subQueryFields( $subQueryFieldsArgument );
		foreach ( CheckUserUnionSelectQueryBuilder::UNION_TABLES as $table ) {
			$this->assertArrayEquals(
				$expectedSubQueryFields[$table],
				TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] )->fields,
				true,
				true,
				'The fields in the ' . $table . ' table were not as expected.'
			);
		}
	}

	public function testSubQueryFieldsWhenFieldsCountNotEqual() {
		$this->expectException( InvalidArgumentException::class );
		$this->testSubQueryFields( [
			self::CHANGES_TABLE => [],
			self::PRIVATE_LOG_EVENT_TABLE => [ 'timestamp' ],
			self::LOG_EVENT_TABLE => [ 'timestamp', 'log_id' ]
		], [] );
	}

	/**
	 * @covers ::needsCommentJoin
	 * @dataProvider provideNeedsCommentJoin
	 */
	public function testNeedsCommentJoin( $table, $joinTableAlias ) {
		$object = $this->setUpObject();
		$subQueryObject = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] );
		$this->assertArrayNotHasKey(
			$joinTableAlias,
			$subQueryObject->tables,
			'Comment JOIN should not occur until needsCommentJoin has been called.'
		);
		$subQueryObject->lastAlias = 'testing';
		$object->needsCommentJoin();
		$subQueryObject = TestingAccessWrapper::newFromObject( $object->subQueriesForUnion[$table] );
		$this->assertSame(
			'testing',
			$subQueryObject->lastAlias,
			'The lastAlias value was changed by the needsActorJoin() call.'
		);
		$this->assertArrayHasKey(
			$joinTableAlias,
			$subQueryObject->tables,
			'needsCommentJoin has been called so the comment JOIN should have been applied to the subqueries.'
		);
	}

	public static function provideNeedsCommentJoin() {
		return [
			'Comment join for cu_changes' => [
				'cu_changes', 'comment_cuc_comment'
			],
			'Comment join for cu_log_event' => [
				'cu_log_event', 'comment_log_comment'
			],
			'Comment join for cu_private_event' => [
				'cu_private_event', 'comment_cupe_comment'
			],
		];
	}
}
