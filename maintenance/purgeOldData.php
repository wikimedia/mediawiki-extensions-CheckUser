<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname(__FILE__).'/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class PurgeOldIPAddressData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Purge old IP data in CheckUser and RecentChanges";
	}

	public function execute() {
		global $wgCUDMaxAge, $wgRCMaxAge, $wgPutIPinRC;

		$dbw = wfGetDB( DB_MASTER );

		$this->output( "Purging data from cu_changes..." );
		$cutoff = $dbw->timestamp( time() - $wgCUDMaxAge );
		$dbw->delete( 'cu_changes', array( "cuc_timestamp < '{$cutoff}'" ), __METHOD__ );
		$this->output( $dbw->affectedRows() . " rows.\n" );

		if ( $wgPutIPinRC ) {
			$this->output( "Purging data from recentchanges..." );
			$cutoff = $dbw->timestamp( time() - $wgRCMaxAge );
			$dbw->delete( 'recentchanges', array( "rc_timestamp < '{$cutoff}'" ), __METHOD__ );
			$this->output( $dbw->affectedRows() . " rows.\n" );
		}

		$this->output( "Done.\n" );
	}
}

$maintClass = "PurgeOldIPAddressData";
require_once( RUN_MAINTENANCE_IF_MAIN );
