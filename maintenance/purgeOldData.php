<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Extension\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use PurgeRecentChanges;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __DIR__, 3 );
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class PurgeOldData extends Maintenance {
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
		$domainId = $this->getPrimaryDB()->getDomainID();
		$key = CheckUserDataPurger::getPurgeLockKey( $domainId );
		// Set the timeout at 60s, in case any job that has the lock is slow to run.
		$scopedLock = $this->getServiceContainer()->getLockManager()->scopedLock( $key, 60 );
		if ( $scopedLock ) {
			// Purge expired rows from each local CheckUser result table
			foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
				$this->output( "Purging data from $table..." );
				[ $count, $mappingRowsCount ] = $this->prune( $table, $cutoff );
				$this->output( "Purged $count rows and $mappingRowsCount client hint mapping rows.\n" );
			}

			if ( $this->getConfig()->get( 'CheckUserWriteToCentralIndex' ) ) {
				// Purge expired rows from the central index tables where the rows are associated with this wiki
				/** @var CheckUserCentralIndexManager $checkUserCentralIndexManager */
				$checkUserCentralIndexManager = $this->getServiceContainer()->get( 'CheckUserCentralIndexManager' );
				$centralRowsPurged = 0;
				do {
					$rowsPurgedInThisBatch = $checkUserCentralIndexManager->purgeExpiredRows(
						$cutoff,
						$domainId,
						$this->mBatchSize
					);
					$centralRowsPurged += $rowsPurgedInThisBatch;
					$this->waitForReplication();
				} while ( $rowsPurgedInThisBatch !== 0 );
				$this->output( "Purged $centralRowsPurged central index rows.\n" );
			}
		} else {
			$this->error( "Unable to acquire a lock to do the purging of CheckUser data. Skipping this." );
		}

		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$orphanedMappingRowsDeleted = $userAgentClientHintsManager->deleteOrphanedMapRows();
		$this->output( "Purged $orphanedMappingRowsDeleted orphaned client hint mapping rows.\n" );

		if ( $config->get( MainConfigNames::PutIPinRC ) ) {
			$this->output( "Purging data from recentchanges..." );
			$purgeRecentChanges = $this->createChild( PurgeRecentChanges::class );
			$purgeRecentChanges->execute();
		}

		if ( $scopedLock ) {
			$this->pruneUserAgentTable();
			$this->pruneClientHintsTable();
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
				$this->getPrimaryDB(),
				$table,
				$cutoff,
				$clientHintReferenceIds,
				__METHOD__,
				$this->mBatchSize
			);
			$deletedCount += $rowsPurgedInThisBatch;
			$this->waitForReplication();
		} while ( $rowsPurgedInThisBatch !== 0 );

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$mappingRowsDeleted = $userAgentClientHintsManager->deleteMappingRows( $clientHintReferenceIds );

		return [ $deletedCount, $mappingRowsDeleted ];
	}

	private function pruneUserAgentTable(): void {
		$this->output( "Pruning unused rows from cu_useragent...\n" );

		// All cu_useragent rows that are not referenced in any CheckUser result table
		$rowsDeleted = $this->pruneUnusedRows(
			'cu_useragent',
			'cuua_id',
			$this->getReplicaDB()->newSelectQueryBuilder()
				->select( 'cuua_id' )
				->from( 'cu_useragent' )
				->leftJoin( 'cu_changes', null, 'cuua_id = cuc_agent_id' )
				->leftJoin( 'cu_log_event', null, 'cuua_id = cule_agent_id' )
				->leftJoin( 'cu_private_event', null, 'cuua_id = cupe_agent_id' )
				->where( [
					'cuc_agent_id' => null,
					'cule_agent_id' => null,
					'cupe_agent_id' => null,
				] )
		);

		$this->output( "Pruned $rowsDeleted unused rows from the cu_useragent.\n" );
	}

	/**
	 * Deletes the rows of cu_useragent_clienthints that no action uses any more.
	 *
	 * Some values, such as an Api-User-Agent, are free text that can name a user, and values
	 * such as old browser versions are never used again.
	 */
	private function pruneClientHintsTable(): void {
		$this->output( "Pruning unused rows from cu_useragent_clienthints...\n" );

		$rowsDeleted = $this->pruneUnusedRows(
			'cu_useragent_clienthints',
			'uach_id',
			$this->getReplicaDB()->newSelectQueryBuilder()
				->select( 'uach_id' )
				->from( 'cu_useragent_clienthints' )
				->leftJoin( 'cu_useragent_clienthints_map', null, 'uachm_uach_id = uach_id' )
				->where( [ 'uachm_uach_id' => null ] )
		);

		$this->output( "Pruned $rowsDeleted unused rows from cu_useragent_clienthints.\n" );
	}

	/**
	 * Deletes, in batches, the rows of $table that $unusedIdsQueryBuilder selects.
	 *
	 * @param string $table
	 * @param string $idField The primary key of $table
	 * @param SelectQueryBuilder $unusedIdsQueryBuilder Selects $idField for the rows that are unused
	 * @return int The number of rows deleted
	 */
	private function pruneUnusedRows(
		string $table,
		string $idField,
		SelectQueryBuilder $unusedIdsQueryBuilder
	): int {
		// To avoid race conditions of newly added rows being deleted
		// before they are used, ignore rows that are in the 1% of newest rows
		// in the table.
		$dbw = $this->getPrimaryDB();
		$dbr = $this->getReplicaDB();
		$maxId = (int)$dbr->newSelectQueryBuilder()
			->select( "MAX($idField)" )
			->from( $table )
			->caller( __METHOD__ )
			->fetchField();
		$maxIdToDelete = min( [ $maxId - 1, (int)( $maxId * 0.99 ) ] );

		$rowsDeleted = 0;
		while ( true ) {
			// Get a batch of IDs of unused rows.
			$idsToDelete = $dbr->newSelectQueryBuilder()
				->merge( $unusedIdsQueryBuilder )
				->where( $dbr->expr( $idField, '<=', $maxIdToDelete ) )
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();
			if ( !$idsToDelete ) {
				break;
			}

			// Double check that these rows are unused using a primary DB connection
			// and if they are then perform the delete straight after.
			// This is to avoid issues if the replica DB is behind and a new row
			// is referencing any of the selected rows.
			$idsToDelete = $dbw->newSelectQueryBuilder()
				->merge( $unusedIdsQueryBuilder )
				->where( [ $idField => $idsToDelete ] )
				->caller( __METHOD__ )
				->fetchFieldValues();
			if ( $idsToDelete ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( $table )
					->where( [ $idField => $idsToDelete ] )
					->caller( __METHOD__ )
					->execute();
				$rowsDeleted += $dbw->affectedRows();
			}

			$this->waitForReplication();
		}

		return $rowsDeleted;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
