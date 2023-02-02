<?php

namespace MediaWiki\CheckUser\Maintenance;

use LogEntryBase;
use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Move log entries from cu_changes to cu_private_event. This removes
 * the log entries from cu_changes which means that this maintenance script
 * should only be run when reading and writing to the new tables.
 *
 * Based on parts of multiple other maintenance scripts in this extension.
 */
class MoveLogEntriesFromCuChanges extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Move log entries from cu_changes to cu_private_event' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'CheckUser' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		$services = MediaWikiServices::getInstance();

		$eventTableMigrationStage = $services->getMainConfig()->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTableMigrationStage !== SCHEMA_COMPAT_NEW ) {
			$this->output(
				"Event table migration config must be set to write and read new otherwise moved entries would " .
				"not be displayed in check results. Change the migration config and try again."
			);
			return false;
		}

		// Check if the table is empty
		$cuChangesRows = $dbw->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( !$cuChangesRows ) {
			$this->output( "cu_changes is empty; nothing to move.\n" );
			return true;
		}

		$start = (int)$dbw->newSelectQueryBuilder()
			->field( 'MIN(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();
		$end = (int)$dbw->newSelectQueryBuilder()
			->field( 'MAX(cuc_id)' )
			->table( 'cu_changes' )
			->caller( __METHOD__ )
			->fetchField();
		// Do remaining chunk
		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;

		$this->output(
			"Moving log entries from cu_changes to cu_private_event with cuc_id from $start to $end\n"
		);

		$lbFactory = $services->getDBLoadBalancerFactory();
		$commentMigrationStage = $services->getMainConfig()->get( 'CheckUserCommentMigrationStage' );
		$commentStore = $services->getCommentStore();

		while ( $blockStart <= $end ) {
			$this->output( "...checking and moving log entries with cuc_id from $blockStart to $blockEnd\n" );
			$cond = "cuc_id BETWEEN $blockStart AND $blockEnd";
			$res = $dbw->newSelectQueryBuilder()
				->fields( [
					'cuc_id',
					'cuc_namespace',
					'cuc_title',
					'cuc_actor',
					'cuc_actiontext',
					'cuc_comment',
					'cuc_comment_id',
					'cuc_page_id',
					'cuc_timestamp',
					'cuc_ip',
					'cuc_ip_hex',
					'cuc_xff',
					'cuc_xff_hex',
					'cuc_agent',
					'cuc_private'
				] )
				->table( 'cu_changes' )
				->conds( $cond )
				->where( [ 'cuc_type' => RC_LOG ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$batch = [];
			$removeBatch = [];
			foreach ( $res as $row ) {
				$entry = [
					'cupe_timestamp' => $row->cuc_timestamp,
					'cupe_namespace' => $row->cuc_namespace,
					'cupe_title' => $row->cuc_title,
					'cupe_actor' => $row->cuc_actor,
					'cupe_page' => $row->cuc_page_id,
					'cupe_log_action' => 'migrated-cu_changes-log-event',
					'cupe_log_type' => 'checkuser-private-event',
					'cupe_params' => LogEntryBase::makeParamBlob( [ '4::actiontext' => $row->cuc_actiontext ] ),
					'cupe_ip' => $row->cuc_ip,
					'cupe_ip_hex' => $row->cuc_ip_hex,
					'cupe_xff' => $row->cuc_ip,
					'cupe_xff_hex' => $row->cuc_ip_hex,
					'cupe_agent' => $row->cuc_agent,
					'cupe_private' => $row->cuc_private
				];

				if ( $commentMigrationStage & SCHEMA_COMPAT_READ_NEW ) {
					$entry['cupe_comment_id'] = $row->cuc_comment_id;
				} else {
					$entry['cupe_comment_id'] = $commentStore->createComment( $dbw, $row->cuc_comment )->id;
				}

				$batch[] = $entry;
				$removeBatch[] = $row->cuc_id;
			}
			if ( count( $batch ) ) {
				$dbw->insert( 'cu_private_event', $batch, __METHOD__ );
			}
			if ( count( $removeBatch ) ) {
				$dbw->delete( 'cu_changes', [ 'cuc_id' => $removeBatch ], __METHOD__ );
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$lbFactory->waitForReplication( [ 'ifWritesSince' => 5 ] );
			$lbFactory->autoReconfigure();
		}

		$this->output( "...all log entries in cu_changes have been moved to cu_private_event table.\n" );
		return true;
	}
}

$maintClass = MoveLogEntriesFromCuChanges::class;
require_once RUN_MAINTENANCE_IF_MAIN;
