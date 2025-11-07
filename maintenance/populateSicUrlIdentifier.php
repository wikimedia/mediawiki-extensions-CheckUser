<?php

namespace MediaWiki\CheckUser\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script that populates the `sic_url_identifier` column in the `cusi_case` table.
 */
class PopulateSicUrlIdentifier extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the sic_url_identifier column in cusi_case.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$this->output( "Populating sic_url_identifier in cusi_case...\n" );

		if ( !$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			$this->output( "Nothing to do as CheckUser Suggested Investigations is not enabled.\n" );

			// Return false as if CheckUserSuggestedInvestigationsEnabled is later set to true, there may
			// be rows to populate
			return false;
		}

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );

		$dbProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$dbr = $dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$count = 0;
		do {
			$idsBatch = $dbr->newSelectQueryBuilder()
				->select( [ 'sic_id' ] )
				->from( 'cusi_case' )
				->where( [ 'sic_url_identifier' => 0 ] )
				->limit( $this->getBatchSize() ?? 200 )
				->caller( __METHOD__ )
				->fetchFieldValues();

			foreach ( $idsBatch as $id ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'cusi_case' )
					->set( [ 'sic_url_identifier' => $caseManager->generateUniqueUrlIdentifier( true ) ] )
					->where( [ 'sic_id' => $id ] )
					->caller( __METHOD__ )
					->execute();
				$count += $dbw->affectedRows();
			}
			$this->output( "... $count rows populated\n" );

			sleep( intval( $this->getOption( 'sleep', 0 ) ) );
			$this->waitForReplication();
		} while ( count( $idsBatch ) > 0 );

		$this->output( "Done. Populated $count rows.\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateSicUrlIdentifier::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
