<?php

use Wikimedia\Rdbms\SelectQueryBuilder;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeOldData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge expired rows in CheckUser and RecentChanges' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		$config = $this->getConfig();
		$CUDMaxAge = $config->get( 'CUDMaxAge' );
		$RCMaxAge = $config->get( 'RCMaxAge' );
		$PutIPinRC = $config->get( 'PutIPinRC' );

		$this->output( "Purging data from cu_changes..." );
		$count = $this->prune( 'cu_changes', 'cuc_timestamp', $CUDMaxAge );
		$this->output( $count . " rows.\n" );

		if ( $PutIPinRC ) {
			$this->output( "Purging data from recentchanges..." );
			$count = $this->prune( 'recentchanges', 'rc_timestamp', $RCMaxAge );
			$this->output( $count . " rows.\n" );
		}

		$this->output( "Done.\n" );
	}

	/**
	 * @param string $table
	 * @param string $ts_column
	 * @param int $maxAge
	 *
	 * @return int
	 */
	protected function prune( $table, $ts_column, $maxAge ) {
		$dbw = $this->getDB( DB_PRIMARY );
		$expiredCond = "$ts_column < " . $dbw->addQuotes( $dbw->timestamp( time() - $maxAge ) );

		$count = 0;
		while ( true ) {
			// Get the first $this->mBatchSize (or less) items
			$res = $dbw->newSelectQueryBuilder()
				->field( $ts_column )
				->table( $table )
				->conds( $expiredCond )
				->orderBy( $ts_column, SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ )
				->fetchResultSet();
			if ( !$res->numRows() ) {
				// all cleared
				break;
			}
			// Record the start and end timestamp for the set
			$blockStart = $dbw->addQuotes( $res->fetchRow()[$ts_column] );
			$res->seek( $res->numRows() - 1 );
			$blockEnd = $dbw->addQuotes( $res->fetchRow()[$ts_column] );
			$res->free();

			// Do the actual delete...
			$this->beginTransaction( $dbw, __METHOD__ );
			$dbw->delete( $table,
				[ "$ts_column BETWEEN $blockStart AND $blockEnd" ], __METHOD__ );
			$count += $dbw->affectedRows();
			$this->commitTransaction( $dbw, __METHOD__ );

		}

		return $count;
	}
}

$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
