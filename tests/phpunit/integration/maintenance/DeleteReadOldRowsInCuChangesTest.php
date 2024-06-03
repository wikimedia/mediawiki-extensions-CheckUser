<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges
 */
class DeleteReadOldRowsInCuChangesTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return DeleteReadOldRowsInCuChanges::class;
	}

	private function addReadOldRows( $count ) {
		$rows = [];
		$testUser = $this->getTestUser()->getUser();
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = [
				'cuc_actor' => $testUser->getActorId(), 'cuc_only_for_read_old' => 1, 'cuc_type' => RC_LOG,
				'cuc_ip'  => '1.2.3.4', 'cuc_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'cuc_timestamp'  => $this->getDb()->timestamp(), 'cuc_comment_id' => 0,
			];
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $rows )
			->execute();
	}

	private function addNormalRows( $count ) {
		$rows = [];
		$testUser = $this->getTestUser()->getUser();
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = [
				'cuc_actor' => $testUser->getActorId(), 'cuc_only_for_read_old' => 0, 'cuc_type' => RC_EDIT,
				'cuc_ip'  => '1.2.3.4', 'cuc_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				'cuc_timestamp'  => $this->getDb()->timestamp(), 'cuc_comment_id' => 0,
			];
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $rows )
			->execute();
	}

	/** @dataProvider provideSchemaValuesWhichResultInNoDelete */
	public function testNoDeleteWhenWrongSchemaStage( $schemaStage ) {
		if ( !$this->db->fieldExists( 'cu_changes', 'cuc_only_for_read_old' ) ) {
			// If the cuc_only_for_read_old column does not exist, then we cannot run the test so skip it.
			$this->markTestSkipped( 'This test requires the cuc_only_for_read_old column in the cu_changes table.' );
		}
		$this->overrideConfigValue( 'CheckUserEventTablesMigrationStage', $schemaStage );
		// Set up cu_changes with a read old row and normal row.
		$this->addReadOldRows( 1 );
		$this->addNormalRows( 1 );
		// Run the script
		$this->assertFalse(
			$this->maintenance->execute(),
			'::execute should have returned false so it can be run again as it failed.'
		);
		// Test cu_changes was untouched
		$this->assertRowCount(
			2, 'cu_changes', 'cuc_id',
			'Rows were deleted in cu_changes, even though the script should not have run.'
		);
	}

	public static function provideSchemaValuesWhichResultInNoDelete() {
		return [
			'Read and write old' => [ SCHEMA_COMPAT_OLD ],
			'Read and write old, read new' => [ SCHEMA_COMPAT_OLD | SCHEMA_COMPAT_READ_NEW ],
			'Write both, read new' => [ SCHEMA_COMPAT_WRITE_BOTH | SCHEMA_COMPAT_READ_NEW ],
		];
	}

	public function testNoDeleteIfCuChangesEmpty() {
		$this->overrideConfigValue( 'CheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		// Run the script
		$this->assertTrue(
			$this->maintenance->execute(),
			'::execute should have returned true as the script should have run successfully.'
		);
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'The row count in an empty cu_changes should not have changed after calling ::execute.'
		);
		$this->expectOutputString( "cu_changes is empty; nothing to delete.\n" );
	}

	/** @dataProvider provideRowCountsAndBatchSize */
	public function testExecute( $numberOfReadOldRows, $numberOfNormalRows, $batchSize ) {
		if ( !$this->db->fieldExists( 'cu_changes', 'cuc_only_for_read_old' ) ) {
			// If the cuc_only_for_read_old column does not exist, then we cannot run the test so skip it.
			$this->markTestSkipped( 'This test requires the cuc_only_for_read_old column in the cu_changes table.' );
		}
		$this->overrideConfigValue( 'CheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		// Set up cu_changes
		$this->addReadOldRows( $numberOfReadOldRows );
		$this->addNormalRows( $numberOfNormalRows );
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		// Make a copy to prevent syntax error warnings for accessing protected method setBatchSize.
		$maintenance = $this->maintenance;
		$maintenance->setBatchSize( $batchSize );
		$this->assertTrue(
			$maintenance->execute(),
			'::execute should have returned true as deleting entries only for use when reading old should ' .
			'have completed successfully.'
		);
		// Test entries were moved
		$this->assertRowCount(
			$numberOfNormalRows, 'cu_changes', 'cuc_id',
			'The row count in an empty cu_changes was not as expected after calling ::execute.'
		);
	}

	public static function provideRowCountsAndBatchSize() {
		return [
			'cu_changes read old row count 3, normal row count 2, and batch size 1' => [ 3, 2, 1 ],
			'cu_changes read old row count 2, normal row count 4, and batch size 10' => [ 2, 4, 10 ],
		];
	}

	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// Add the cuc_only_for_read_old column to cu_changes if it does not exist.
		if ( $db->fieldExists( 'cu_changes', 'cuc_only_for_read_old' ) ) {
			// Nothing to do if the cuc_only_for_read_old column already exists.
			return [];
		}
		// Create the cuc_only_for_read_old column in cu_changes using the SQL patch file associated with the current
		// DB type.
		return [
			'scripts' => [ __DIR__ . '/patches/' . $db->getType() . '/patch-cu_changes-add-cuc_only_for_read_old.sql' ],
			'alter' => [ 'cu_changes' ],
		];
	}
}
