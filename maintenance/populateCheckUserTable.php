<?php

use MediaWiki\CheckUser\Hooks;
use MediaWiki\MediaWikiServices;
use Wikimedia\IPUtils;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Populate the cu_changes table needed for CheckUser queries with
 * data from recent changes.
 * This is automatically run during first installation within update.php
 * but --force parameter should be set if you want to manually run thereafter.
 */
class PopulateCheckUserTable extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate `cu_changes` table with entries from recentchanges' );
		$this->addOption( 'cutoff', 'Cut-off time for rc_timestamp' );
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
		$db = $this->getDB( DB_PRIMARY );

		// Check if the table is empty
		$rcRows = $db->newSelectQueryBuilder()
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( !$rcRows ) {
			$this->output( "recentchanges is empty; nothing to add.\n" );
			return true;
		}

		$cutoff = $this->getOption( 'cutoff' );
		if ( $cutoff ) {
			// Something leftover... clear old entries to minimize dupes
			$cutoff = wfTimestamp( TS_MW, $cutoff );
			$encCutoff = $db->addQuotes( $db->timestamp( $cutoff ) );
			$db->delete(
				'cu_changes',
				[ "cuc_timestamp < $encCutoff" ],
				__METHOD__
			);
			$cutoffCond = "AND rc_timestamp < $encCutoff";
		} else {
			$cutoffCond = "";
		}

		$start = (int)$db->newSelectQueryBuilder()
			->field( 'MIN(rc_id)' )
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchField();
		$end = (int)$db->newSelectQueryBuilder()
			->field( 'MAX(rc_id)' )
			->table( 'recentchanges' )
			->caller( __METHOD__ )
			->fetchField();
		// Do remaining chunk
		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;

		$this->output(
			"Starting population of cu_changes with recentchanges rc_id from $start to $end\n"
		);

		$services = MediaWikiServices::getInstance();
		$lbFactory = $services->getDBLoadBalancerFactory();

		$actorMigrationStage = $services->getMainConfig()->get( 'CheckUserActorMigrationStage' );
		$commentMigrationStage = $services->getMainConfig()->get( 'CheckUserCommentMigrationStage' );

		$commentStore = $services->getCommentStore();
		$rcQuery = RecentChange::getQueryInfo();
		$contLang = $services->getContentLanguage();

		while ( $blockStart <= $end ) {
			$this->output( "...migrating rc_id from $blockStart to $blockEnd\n" );
			$cond = "rc_id BETWEEN $blockStart AND $blockEnd $cutoffCond";
			$res = $db->newSelectQueryBuilder()
				->fields( $rcQuery['fields'] )
				->tables( $rcQuery['tables'] )
				->joinConds( $rcQuery['joins'] )
				->conds( $cond )
				->caller( __METHOD__ )
				->fetchResultSet();
			$cuChangesBatch = [];
			$cuPrivateEventBatch = [];
			$cuLogEventBatch = [];
			foreach ( $res as $row ) {
				$eventTablesMigrationStage = $services->getMainConfig()
					->get( 'CheckUserEventTablesMigrationStage' );
				$comment = $commentStore->getComment( 'rc_comment', $row );
				if (
					$row->rc_type == RC_LOG &&
					( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW )
				) {
					if ( $row->rc_logid == 0 ) {
						$cuPrivateEventBatch[] = [
							'cupe_timestamp' => $row->rc_timestamp,
							'cupe_actor' => $row->rc_actor,
							'cupe_namespace' => $row->rc_namespace,
							'cupe_title' => $row->rc_title,
							'cupe_comment_id' => $comment->id,
							'cupe_page' => $row->rc_cur_id,
							'cupe_log_action' => $row->rc_log_action,
							'cupe_log_type' => $row->rc_log_type,
							'cupe_params' => $row->rc_params,
							'cupe_ip' => $row->rc_ip,
							'cupe_ip_hex' => IPUtils::toHex( $row->rc_ip ),
						];
					} else {
						$cuLogEventBatch[] = [
							'cule_timestamp' => $row->rc_timestamp,
							'cule_actor' => $row->rc_actor,
							'cule_log_id' => $row->rc_logid,
							'cule_ip' => $row->rc_ip,
							'cule_ip_hex' => IPUtils::toHex( $row->rc_ip ),
						];
					}
				} else {
					$entry = [
						'cuc_timestamp' => $row->rc_timestamp,
						'cuc_namespace' => $row->rc_namespace,
						'cuc_title' => $row->rc_title,
						'cuc_comment' => $contLang->truncateForDatabase(
							$comment->text, Hooks::TEXT_FIELD_LENGTH
						),
						'cuc_minor' => $row->rc_minor,
						'cuc_page_id' => $row->rc_cur_id,
						'cuc_this_oldid' => $row->rc_this_oldid,
						'cuc_last_oldid' => $row->rc_last_oldid,
						'cuc_type' => $row->rc_type,
						'cuc_ip' => $row->rc_ip,
						'cuc_ip_hex' => IPUtils::toHex( $row->rc_ip ),
					];

					if ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
						$entry['cuc_user'] = $row->rc_user ?? 0;
						$entry['cuc_user_text'] = $row->rc_user_text;
					}
					if ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
						$entry['cuc_actor'] = $row->rc_actor;
					}

					if ( $commentMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
						$entry['cuc_comment_id'] = $comment->id;
					}

					$cuChangesBatch[] = $entry;
				}
			}
			if ( count( $cuChangesBatch ) ) {
				$db->insert( 'cu_changes', $cuChangesBatch, __METHOD__ );
			}
			if ( count( $cuPrivateEventBatch ) ) {
				$db->insert( 'cu_private_event', $cuPrivateEventBatch, __METHOD__ );
			}
			if ( count( $cuLogEventBatch ) ) {
				$db->insert( 'cu_log_event', $cuLogEventBatch, __METHOD__ );
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$lbFactory->waitForReplication( [ 'ifWritesSince' => 5 ] );
			$lbFactory->autoReconfigure();
		}

		$this->output( "...cu_changes table has been populated.\n" );
		return true;
	}
}

$maintClass = PopulateCheckUserTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
