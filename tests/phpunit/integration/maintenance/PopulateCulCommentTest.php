<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCulComment
 */
class PopulateCulCommentTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	public function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_log',
			'comment'
		];
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulComment::class;
	}

	/** @dataProvider provideAddLogEntryReasonId */
	public function testDoDBUpdatesSingleRow( $reason, $plaintextReason ) {
		if ( $this->db->getType() === 'postgres' ) {
			// The test is unable to add the column to the database
			//  as the maintenance script even after adding the column
			//  is unable to see it exists.
			$this->markTestSkipped( 'This test does not work on postgres' );
		}
		$testTarget = $this->getTestUser()->getUserIdentity();
		// Create a test cu_log entry with a cul_reason value.
		$this->db->newInsertQueryBuilder()
			->insertInto( 'cu_log' )
			->row( [
				'cul_timestamp' => $this->db->timestamp( ConvertibleTimestamp::time() ),
				'cul_actor' => $this->getTestSysop()->getUser()->getActorId(),
				'cul_type' => 'user',
				'cul_target_id' => $testTarget->getId(),
				'cul_target_text' => $testTarget->getName(),
				'cul_reason' => $reason,
				'cul_reason_id' => 0,
				'cul_reason_plaintext_id' => 0
			] )
			->execute();
		// Run the maintenance script
		$this->createMaintenance()->doDBUpdates();
		// Check that cul_reason is correct
		$this->assertSelect(
			'cu_log',
			[ 'cul_reason' ],
			[],
			[ [ $reason ] ]
		);
		// Get the ID to the comment table stored in cu_log
		$row = $this->db->newSelectQueryBuilder()
			->fields( [ 'cul_reason_id', 'cul_reason_plaintext_id' ] )
			->table( 'cu_log' )
			->fetchRow();
		// Check that the comment IDs are for rows that have the correct
		//  expected reason.
		$this->assertSame(
			$reason,
			$this->db->newSelectQueryBuilder()
				->field( 'comment_text' )
				->table( 'comment' )
				->where( [ 'comment_id' => $row->cul_reason_id ] )
				->fetchField(),
			'The cul_reason_id is for the wrong comment.'
		);
		$this->assertSame(
			$plaintextReason,
			$this->db->newSelectQueryBuilder()
				->field( 'comment_text' )
				->table( 'comment' )
				->where( [ 'comment_id' => $row->cul_reason_plaintext_id ] )
				->fetchField(),
			'The cul_reason_plaintext_id is for the wrong comment.'
		);
	}

	public static function provideAddLogEntryReasonId() {
		return [
			[ 'Testing 1234', 'Testing 1234' ],
			[ 'Testing 1234 [[test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [[:mw:Testing|test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [test]', 'Testing 1234 [test]' ],
			[ 'Testing 1234 [https://example.com]', 'Testing 1234 [https://example.com]' ],
			[ 'Testing 1234 [[test]', 'Testing 1234 [[test]' ],
			[ 'Testing 1234 [test]]', 'Testing 1234 [test]]' ],
			[ 'Testing 1234 <var>', 'Testing 1234 <var>' ],
			[ 'Testing 1234 {{test}}', 'Testing 1234 {{test}}' ],
			[ 'Testing 12345 [[{{test}}]]', 'Testing 12345 [[{{test}}]]' ],
		];
	}

	public function addDBDataOnce() {
		// Create cul_reason on the test DB.
		//  This is broken for postgres so no cul_reason
		//  is added for that DB type.
		if ( $this->db->getType() === 'sqlite' ) {
			$this->db->query(
				"ALTER TABLE   " .
				$this->db->tableName( 'cu_log' ) .
				" ADD  cul_reason BLOB DEFAULT '' NOT NULL;",
				__METHOD__
			);
		} elseif ( $this->db->getType() !== 'postgres' ) {
			$this->db->query(
				"ALTER TABLE   " .
				$this->db->tableName( 'cu_log' ) .
				" ADD  cul_reason VARBINARY(255) DEFAULT '' NOT NULL;",
				__METHOD__
			);
		}
	}
}
