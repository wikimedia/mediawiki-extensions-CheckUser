<?php

namespace MediaWiki\CheckUser\Maintenance;

use Maintenance;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MainConfigNames;
use PurgeRecentChanges;
use Wikimedia\Timestamp\ConvertibleTimestamp;

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeOldData extends Maintenance implements CheckUserQueryInterface {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Purge expired rows in CheckUser and RecentChanges' );
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		$config = $this->getConfig();
		$cudMaxAge = $config->get( 'CUDMaxAge' );
		$cutoff = $this->getPrimaryDB()->timestamp( ConvertibleTimestamp::time() - $cudMaxAge );

		// Get an exclusive lock to purge the expired CheckUser data, so that no job attempts to do this while
		// we are doing it here.
		$key = CheckUserDataPurger::getPurgeLockKey( $this->getPrimaryDB()->getDomainID() );
		// Set the timeout at 60s, in case any job that has the lock is slow to run.
		$scopedLock = $this->getPrimaryDB()->getScopedLockAndFlush( $key, __METHOD__, 60 );
		if ( $scopedLock ) {
			foreach ( self::RESULT_TABLES as $table ) {
				$this->output( "Purging data from $table..." );
				[ $count, $mappingRowsCount ] = $this->prune( $table, $cutoff );
				$this->output( "Purged $count rows and $mappingRowsCount client hint mapping rows.\n" );
			}
		} else {
			$this->error( "Unable to acquire a lock to do the purging of CheckUser data. Skipping this." );
		}

		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$orphanedMappingRowsDeleted = $userAgentClientHintsManager->deleteOrphanedMapRows();
		$this->output( "Purged $orphanedMappingRowsDeleted orphaned client hint mapping rows.\n" );

		if ( $config->get( MainConfigNames::PutIPinRC ) ) {
			$this->output( "Purging data from recentchanges..." );
			$this->runChild( PurgeRecentChanges::class );
		}

		$this->output( "Done.\n" );
	}

	/**
	 * Prunes data from the given CheckUser result table
	 *
	 * @param string $table
	 * @param string $cutoff
	 * @return int[] An array of two integers: The first being the rows deleted in $table and
	 *  the second in cu_useragent_clienthints_map.
	 */
	protected function prune( string $table, string $cutoff ) {
		/** @var CheckUserDataPurger $checkUserDataPurger */
		$checkUserDataPurger = $this->getServiceContainer()->get( 'CheckUserDataPurger' );
		$clientHintReferenceIds = new ClientHintsReferenceIds();

		$deletedCount = 0;
		do {
			$rowsPurgedInThisBatch = $checkUserDataPurger->purgeDataFromLocalTable(
				$this->getPrimaryDB(), $table, $cutoff, $clientHintReferenceIds, __METHOD__, $this->mBatchSize
			);
			$deletedCount += $rowsPurgedInThisBatch;
			$this->waitForReplication();
		} while ( $rowsPurgedInThisBatch !== 0 );

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$mappingRowsDeleted = $userAgentClientHintsManager->deleteMappingRows( $clientHintReferenceIds );

		return [ $deletedCount, $mappingRowsDeleted ];
	}
}

$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
