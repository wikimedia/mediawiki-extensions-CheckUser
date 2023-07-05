<?php

namespace MediaWiki\CheckUser\Maintenance;

use Maintenance;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MediaWikiServices;
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
		[ $count, $mappingRowsCount, $clientHintRowsCount ] = $this->prune(
			'cu_changes', 'cuc_timestamp', $CUDMaxAge, UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
		);
		$this->output(
			"Purged $count rows, with $mappingRowsCount client hint mapping rows purged " .
			"and $clientHintRowsCount client hint rows purged.\n"
		);

		$this->output( "Purging data from cu_private_event..." );
		[ $count, $mappingRowsCount, $clientHintRowsCount ] = $this->prune(
			'cu_private_event', 'cupe_timestamp', $CUDMaxAge,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
		);
		$this->output(
			"Purged $count rows, with $mappingRowsCount client hint mapping rows purged " .
			"and $clientHintRowsCount client hint rows purged.\n"
		);

		$this->output( "Purging data from cu_log_event..." );
		[ $count, $mappingRowsCount, $clientHintRowsCount ] = $this->prune(
			'cu_log_event', 'cule_timestamp', $CUDMaxAge,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
		);
		$this->output(
			"Purged $count rows, with $mappingRowsCount client hint mapping rows purged " .
			"and $clientHintRowsCount client hint rows purged.\n"
		);

		if ( $PutIPinRC ) {
			$this->output( "Purging data from recentchanges..." );
			$count = $this->prune( 'recentchanges', 'rc_timestamp', $RCMaxAge, null );
			$this->output( "Purged " . $count[0] . " rows.\n" );
		}

		$this->output( "Done.\n" );
	}

	/**
	 * @param string $table
	 * @param string $ts_column
	 * @param int $maxAge
	 * @param int|null $clientHintMappingId The mapping ID associated with this table for cu_useragent_clienthints_map
	 *
	 * @return int[] An array of three integers: The first being the rows deleted in $table,
	 *  the second in cu_useragent_clienthints_map, and the third in cu_useragent_clienthints.
	 */
	protected function prune( string $table, string $ts_column, int $maxAge, ?int $clientHintMappingId ) {
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = MediaWikiServices::getInstance()->get( 'UserAgentClientHintsManager' );
		$referenceColumn = $userAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$clientHintMappingId] ?? null;
		$clientHintReferenceIds = new ClientHintsReferenceIds();
		$shouldDeleteAssociatedClientData = $this->getConfig()->get( 'CheckUserPurgeOldClientHintsData' );

		$dbw = $this->getDB( DB_PRIMARY );
		$expiredCond = "$ts_column < " . $dbw->addQuotes( $dbw->timestamp( time() - $maxAge ) );

		$deletedCount = 0;
		while ( true ) {
			// Get the first $this->mBatchSize (or less) items
			$queryBuilder = $dbw->newSelectQueryBuilder()
				->table( $table )
				->conds( $expiredCond )
				->orderBy( $ts_column, SelectQueryBuilder::SORT_ASC )
				->limit( $this->mBatchSize )
				->caller( __METHOD__ );
			if ( $shouldDeleteAssociatedClientData && $clientHintMappingId !== null ) {
				$res = $queryBuilder->fields( [
					$ts_column,
					$referenceColumn
				] )->fetchResultSet();
				foreach ( $res as $row ) {
					$clientHintReferenceIds->addReferenceIds( $row->$referenceColumn, $clientHintMappingId );
				}
				$res->seek( 0 );
			} else {
				$res = $queryBuilder->field( $ts_column )->fetchResultSet();
			}
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
			$dbw->newDeleteQueryBuilder()
				->table( $table )
				->where( "$ts_column BETWEEN $blockStart AND $blockEnd" )
				->caller( __METHOD__ )
				->execute();
			$deletedCount += $dbw->affectedRows();
			$this->commitTransaction( $dbw, __METHOD__ );
		}

		$mappingRowsDeleted = 0;
		$orphanedClientHintRowsDeleted = 0;
		if ( $shouldDeleteAssociatedClientData ) {
			$status = $userAgentClientHintsManager->deleteMappingRows( $clientHintReferenceIds );
			if ( $status->isGood() ) {
				[ $mappingRowsDeleted, $orphanedClientHintRowsDeleted ] = $status->getValue();
				if ( $mappingRowsDeleted || $orphanedClientHintRowsDeleted ) {
					$this->commitTransaction( $dbw, __METHOD__ );
				}
			} else {
				$this->output( 'Deletion of client hint data did not succeed.' );
			}
		}

		return [ $deletedCount, $mappingRowsDeleted, $orphanedClientHintRowsDeleted ];
	}
}

$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
