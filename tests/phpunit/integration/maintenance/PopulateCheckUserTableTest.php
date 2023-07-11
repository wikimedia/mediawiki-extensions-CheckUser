<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use ManualLogEntry;
use MediaWiki\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 *
 * @coversDefaultClass \MediaWiki\CheckUser\Maintenance\PopulateCheckUserTable
 */
class PopulateCheckUserTableTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	public function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_changes',
			'cu_private_event',
			'cu_log_event',
			'recentchanges',
			'updatelog'
		];
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCheckUserTable::class;
	}

	/**
	 * @covers ::doDBUpdates
	 */
	protected function testNoPopulationOnEmptyRecentChangesTable() {
		$this->assertTrue(
			$this->maintenance->execute(),
			'Maintenance script should have returned true.'
		);
		$this->expectOutputString( 'recentchanges is empty; nothing to add.' );
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
		$this->assertRowCount(
			0, 'cu_log_event', 'cule_id',
			'No entries in recentchanges table, so no population should have occurred.'
		);
	}

	/**
	 * @covers ::doDBUpdates
	 * @dataProvider provideTestPopulation
	 */
	public function testPopulation(
		$numberOfRows, $expectedCuChangesCount, $expectedCuLogEventCount, $eventTableMigrationStage
	) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		// Set up recentchanges table
		for ( $i = 0; $i < $numberOfRows / 2; $i++ ) {
			$this->editPage( Title::newFromDBkey( 'CheckUserTestPage' ), 'Testing123' . $i );
			// Log action
			$logEntry = new ManualLogEntry( 'foo', 'bar' );
			$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
			$logEntry->setTarget( $this->getExistingTestPage() );
			$logEntry->setComment( 'Testing' );
			$logid = $logEntry->insert();
			$logEntry->publish( $logid );
		}
		$this->assertRowCount(
			$numberOfRows, 'recentchanges', '*',
			'recentchanges table not set up correctly for the test.'
		);
		// Clear cu_changes, cu_private_event and cu_log_event for the test
		//  because entries would have been added by Hooks.php for the above code
		//  that set-up the recentchanges table.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );
		// Run the script
		/** @var TestingAccessWrapper $maintenance */
		$maintenance = $this->maintenance;
		$this->assertTrue(
			$maintenance->execute(),
			'execute() should have returned true as moving entries should have completed successfully.'
		);
		$this->assertRowCount(
			$expectedCuLogEventCount, 'cu_log_event', 'cule_id',
			'Incorrect number of entries in cu_log_event after population.'
		);
		$this->assertRowCount(
			$expectedCuChangesCount, 'cu_changes', 'cuc_id',
			'Incorrect number of entries in cu_changes after population.'
		);
		if (
			( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) &&
			( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW )
		) {
			$this->assertRowCount(
				$expectedCuChangesCount / 2, 'cu_changes', 'cuc_id',
				'Entries in cu_changes that are logs should have cuc_only_for_read_old set to 1.',
				[ 'cuc_only_for_read_old' => 1 ]
			);
		}
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'Population script does not add entries to cu_private_event, so it should be empty.'
		);
	}

	public static function provideTestPopulation() {
		return [
			'recentchanges row count 4 with SCHEMA_COMPAT_WRITE_OLD' => [
				4, 4, 0, SCHEMA_COMPAT_WRITE_OLD
			],
			'recentchanges row count 4 with SCHEMA_COMPAT_WRITE_BOTH' => [
				4, 4, 2, SCHEMA_COMPAT_WRITE_BOTH
			],
			'recentchanges row count 4 with SCHEMA_COMPAT_WRITE_NEW' => [
				4, 2, 2, SCHEMA_COMPAT_WRITE_NEW
			],
		];
	}
}
