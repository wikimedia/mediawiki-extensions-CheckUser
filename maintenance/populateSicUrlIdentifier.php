<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IDatabase;

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

		// If suggested investigations is not enabled, then return early. We cannot do this check if
		// this is being run by install.php as config is not fully available there.
		// If being run by install.php, the cusi_case table should exist and so we should just continue.
		if (
			$this->getConfig()->has( 'CheckUserSuggestedInvestigationsEnabled' ) &&
			!$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' )
		) {
			$this->output( "Nothing to do as CheckUser Suggested Investigations is not enabled.\n" );
			return true;
		}

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
					->set( [ 'sic_url_identifier' => $this->generateUniqueUrlIdentifier( $dbw ) ] )
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

	/**
	 * Manually derived from {@link SuggestedInvestigationsCaseManagerService::generateUniqueUrlIdentifier}
	 * to avoid having to instantiate the service and all its dependencies. This maintenance script is not
	 * expected to be permanent but until its removal, if the functions this copied from change then this should
	 * be similarly updated.
	 */
	public function generateUniqueUrlIdentifier( IDatabase $db ): int {
		do {
			// SuggestedInvestigationsCaseManagerService::generateUrlIdentifier
			// SuggestedInvestigationsCaseManagerService::getMaxInteger
			$urlIdentifier = random_int(
				1,
				match ( $db->getType() ) {
					'postgres' => 2147483647,
					default => 4294967295,
				}
			);

			$isUrlIdentifierAlreadyInUse = $db->newSelectQueryBuilder()
				->select( '1' )
				->from( 'cusi_case' )
				->where( [ 'sic_url_identifier' => $urlIdentifier ] )
				->caller( __METHOD__ )
				->fetchField();
		} while ( $isUrlIdentifierAlreadyInUse !== false );

		return $urlIdentifier;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateSicUrlIdentifier::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
