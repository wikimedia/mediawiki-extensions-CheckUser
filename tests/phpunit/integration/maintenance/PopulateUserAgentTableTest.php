<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateUserAgentTable;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Context\RequestContext;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateUserAgentTable
 */
class PopulateUserAgentTableTest extends MaintenanceBaseTestCase {
	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateUserAgentTable::class;
	}

	public function testPopulationFailsWhenMigrationStageIsNotWriteNew(): void {
		$this->overrideConfigValue( 'CheckUserUserAgentTableMigrationStage', SCHEMA_COMPAT_OLD );

		$this->assertFalse( $this->maintenance->execute() );

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'This script requires write new for the user agent table migration stage',
			$actualOutput
		);
	}

	public function testSuccessfulPopulation() {
		$preExistingUserAgentTableId = $this->setUpDatabaseForPopulationTest();

		// Check the DB has been set up correctly for the test,
		// so that we can see a change in the DB after the script runs
		$this->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValue( $preExistingUserAgentTableId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_changes' )
			->where( $this->getDb()->expr( 'cuc_agent_id', '!=', [ 0, $preExistingUserAgentTableId ] ) )
			->caller( __METHOD__ )
			->assertEmptyResult();

		$this->maintenance->execute();

		// Check that the cu_useragent table now has been populated
		$this->newSelectQueryBuilder()
			->select( 'cuua_text' )
			->from( 'cu_useragent' )
			->caller( __METHOD__ )
			->assertFieldValues( [ 'test1', 'test2', 'test3' ] );

		// Check the CheckUser result tables have their agent_id columns populated as expected
		$this->newSelectQueryBuilder()
			->select( [ 'cuc_id', 'cuua_text' ] )
			->from( 'cu_changes' )
			->join( 'cu_useragent', null, 'cuc_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test1' ],
				[ 2, 'test2' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cule_id', 'cuua_text' ] )
			->from( 'cu_log_event' )
			->join( 'cu_useragent', null, 'cule_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test2' ],
			] );
		$this->newSelectQueryBuilder()
			->select( [ 'cupe_id', 'cuua_text' ] )
			->from( 'cu_private_event' )
			->join( 'cu_useragent', null, 'cupe_agent_id = cuua_id' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ 1, 'test3' ],
				[ 2, 'test3' ],
			] );

		// Assert on the output
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			"Populating cuc_agent_id in cu_changes\n" .
				"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cule_agent_id in cu_log_event\n" .
				"... 1 rows populated\nDone. Populated 1 rows.",
			$actualOutput
		);
		$this->assertStringContainsString(
			"Populating cupe_agent_id in cu_private_event\n" .
				"... 2 rows populated\nDone. Populated 2 rows.",
			$actualOutput
		);
	}

	/**
	 * Sets up the database to have rows which need their agent_id column populating
	 * so we can test the population functionality of the maintenance script
	 *
	 * @return int The ID of the cu_useragent row that is pre-existing before
	 *   the maintenance script is run
	 */
	private function setUpDatabaseForPopulationTest(): int {
		$this->overrideConfigValue( 'CheckUserUserAgentTableMigrationStage', SCHEMA_COMPAT_WRITE_BOTH );
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$target = $this->getTestUser()->getUserIdentity();

		// Avoid the creation of the test user causing pre-existing entries in the
		// CheckUser result tables, as we will later assert on the row IDs
		$this->truncateTables( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] );

		// Write some testing entries to cu_changes, cu_log_event, and cu_private_event
		// writing both for the user agent table migration
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'test1' );
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName() ],
		) );
		$checkUserInsert->updateCheckUserData( $rc );

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'test2' );
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName() ],
		) );
		$checkUserInsert->updateCheckUserData( $rc );

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'test2' );
		$logId = $this->newLogEntry();
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => $logId,
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			]
		) );
		$checkUserInsert->updateCheckUserData( $rc );

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'test3' );
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => 0,
				'rc_log_type' => 'test', 'rc_log_action' => 'test2',
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			]
		) );
		$checkUserInsert->updateCheckUserData( $rc );

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'test3' );
		$rc = new RecentChange;
		$rc->setAttribs( array_merge(
			self::getDefaultRecentChangeAttribs(),
			[
				'rc_source' => RecentChange::SRC_LOG, 'rc_logid' => 0,
				'rc_log_type' => 'test2', 'rc_log_action' => 'test3',
				'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
			]
		) );
		$checkUserInsert->updateCheckUserData( $rc );

		// Drop all entries from cu_useragent except from the one for 'test1'
		// and replace the associated references with 0 to simulate the
		// row having been written when the user agent table migration was write old
		$existingUserAgentTableId = $this->getDb()->newSelectQueryBuilder()
			->select( 'cuua_id' )
			->from( 'cu_useragent' )
			->where( [ 'cuua_text' => 'test1' ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'cu_useragent' )
			->where( $this->getDb()->expr( 'cuua_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_changes' )
			->set( [ 'cuc_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cuc_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_log_event' )
			->set( [ 'cule_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cule_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cu_private_event' )
			->set( [ 'cupe_agent_id' => 0 ] )
			->where( $this->getDb()->expr( 'cupe_agent_id', '!=', $existingUserAgentTableId ) )
			->caller( __METHOD__ )
			->execute();

		return $existingUserAgentTableId;
	}
}
