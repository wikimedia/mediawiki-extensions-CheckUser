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
			$batch = [];
			foreach ( $res as $row ) {
				$entry = [
					'cuc_timestamp' => $row->rc_timestamp,
					'cuc_user' => $row->rc_user ?? 0,
					'cuc_user_text' => $row->rc_user_text,
					'cuc_namespace' => $row->rc_namespace,
					'cuc_title' => $row->rc_title,
					'cuc_comment' => $contLang->truncateForDatabase(
						$commentStore->getComment( 'rc_comment', $row )->text, Hooks::TEXT_FIELD_LENGTH
					),
					'cuc_minor' => $row->rc_minor,
					'cuc_page_id' => $row->rc_cur_id,
					'cuc_this_oldid' => $row->rc_this_oldid,
					'cuc_last_oldid' => $row->rc_last_oldid,
					'cuc_type' => $row->rc_type,
					'cuc_ip' => $row->rc_ip,
					'cuc_ip_hex' => IPUtils::toHex( $row->rc_ip ),
				];

				if ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
					$entry['cuc_actor'] = $row->rc_actor;
				}

				$batch[] = $entry;
			}
			if ( count( $batch ) ) {
				$db->insert( 'cu_changes', $batch, __METHOD__ );
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			$lbFactory->waitForReplication( [ 'ifWritesSince' => 5 ] );
		}

		$this->output( "...cu_changes table has been populated.\n" );
		return true;
	}
}

$maintClass = PopulateCheckUserTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
