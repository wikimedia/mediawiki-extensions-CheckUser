<?php

namespace MediaWiki\CheckUser\Maintenance;

use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for fixing trailing spaces issue in cu_log (see T275704)
 *
 * @codeCoverageIgnore No need to cover, single-use script.
 */
class FixTrailingSpacesInLogs extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Remove trailing spaces from all cu_log entries, if there are any' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'CheckUserFixTrailingSpacesInLogs';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$mainLb = $lbFactory->getMainLB();
		$dbr = $mainLb->getLazyConnectionRef( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getLazyConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$maxId = $dbr->selectField(
			'cu_log',
			'MAX(cul_id)',
			[],
			__METHOD__
		);
		$prevId = 0;
		$curId = $batchSize;
		do {
			$dbw->update(
				'cu_log',
				[
					"cul_target_text = TRIM(cul_target_text)"
				],
				[
					"cul_id > $prevId",
					"cul_id <= $curId"
				],
				__METHOD__
			);
			$lbFactory->waitForReplication();

			$this->output( "Processed $batchSize rows out of $maxId.\n" );
			$prevId = $curId;
			$curId += $batchSize;
		} while ( $prevId <= $maxId );

		return true;
	}
}

$maintClass = FixTrailingSpacesInLogs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
