<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return MoveLogEntriesFromCuChanges::class;
	}

	protected function commonTestNoMove( $expectedCuChangesCount, $expectedCuPrivateRowCount = 0 ) {
		$this->assertRowCount(
			$expectedCuPrivateRowCount, 'cu_private_event', 'cupe_id',
			'Rows were moved to cu_private_event when they should not have been moved.'
		);
		$this->assertRowCount(
			$expectedCuChangesCount, 'cu_changes', 'cuc_id',
			'Rows were removed from cu_changes even though there was no move.'
		);
	}

	protected function commonTestMoved(
		$expectedCuChangesRowCount, $expectedCuChangesRowCountWithOnlyReadOld, $expectedCuPrivateRowCount
	) {
		$this->assertRowCount(
			$expectedCuPrivateRowCount, 'cu_private_event', 'cupe_id',
			'Rows were moved to cu_private_event when they should not have been moved.'
		);
		$this->assertRowCount(
			$expectedCuChangesRowCountWithOnlyReadOld, 'cu_changes', 'cuc_id',
			'Rows were not successfully marked as being only for READ_OLD in cu_changes.',
			[ 'cuc_only_for_read_old' => 1 ]
		);
		$this->assertRowCount(
			$expectedCuChangesRowCount, 'cu_changes', 'cuc_id',
			'Rows were removed from cu_changes when they should not have been.'
		);
	}

	/** @dataProvider provideSchemaNoMoveValues */
	public function testNoMoveWhenWrongSchemaStage( $schemaStage, $cuChangesRowCount, $cuPrivateEventCount ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $schemaStage );
		// Set up cu_changes
		$expectedRow = [];
		$this->commonTestsUpdateCheckUserData(
			array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
			[],
			$expectedRow
		);
		// Run the script
		$this->assertFalse(
			$this->maintenance->execute(),
			'execute() should have returned false so it can be run again as it failed.'
		);
		// Test cu_changes was untouched
		$this->commonTestNoMove( $cuChangesRowCount, $cuPrivateEventCount );
	}

	public static function provideSchemaNoMoveValues() {
		return [
			'Read and write old' => [ SCHEMA_COMPAT_OLD, 1, 0 ],
			'Read and write old, read new' => [ SCHEMA_COMPAT_OLD | SCHEMA_COMPAT_READ_NEW, 1, 0 ],
		];
	}

	public function testNoMoveIfCuChangesEmpty() {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		// Run the script
		$this->assertTrue(
			$this->maintenance->execute(),
			'execute() should have returned true so that the script can be skipped in the future.'
		);
		// Test no moving happened
		$this->commonTestNoMove( 0 );
	}

	/** @dataProvider provideBatchSize */
	public function testBatchSize( $numberOfRows, $batchSize, $firstSchemaStage = SCHEMA_COMPAT_OLD ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $firstSchemaStage );
		// Set up cu_changes
		$expectedRow = [];
		for ( $i = 0; $i < $numberOfRows / 2; $i++ ) {
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_EDIT ] ),
				[],
				$expectedRow
			);
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
		}
		$this->assertRowCount(
			$numberOfRows, 'cu_changes', 'cuc_id',
			'Database not set up correctly for the test'
		);
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		// Make a copy to prevent syntax error warnings for accessing protected method setBatchSize.
		$maintenance = $this->maintenance;
		$maintenance->setBatchSize( $batchSize );
		$this->assertTrue(
			$maintenance->execute(),
			'execute() should have returned true as moving entries should have completed successfully.'
		);
		// Test entries were moved
		$this->commonTestMoved( $numberOfRows, $numberOfRows / 2, $numberOfRows / 2 );
	}

	public static function provideBatchSize() {
		return [
			'cu_changes row count 3 and batch size 1' => [
				6, 4
			],
			'cu_changes row count 10 and batch size 5' => [
				10, 5
			],
			'cu_changes row count 10 and batch size 100' => [
				10, 100
			],
		];
	}

	/** @dataProvider provideBatchSize */
	public function testMoveAfterInsertionOnWriteBoth( $numberOfRows, $batchSize ) {
		$this->testBatchSize(
			$numberOfRows, $batchSize, SCHEMA_COMPAT_WRITE_BOTH
		);
	}
}
