<?php

namespace MediaWiki\CheckUser\Maintenance;

use Maintenance;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

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
		$cudMaxAge = $config->get( 'CUDMaxAge' );

		$this->output( "Purging data from cu_changes..." );
		[ $count, $mappingRowsCount ] = $this->prune(
			'cu_changes', 'cuc_timestamp', $cudMaxAge, UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
		);
		$this->output(
			"Purged $count rows and $mappingRowsCount client hint mapping rows purged.\n"
		);

		$this->output( "Purging data from cu_private_event..." );
		[ $count, $mappingRowsCount ] = $this->prune(
			'cu_private_event', 'cupe_timestamp', $cudMaxAge,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
		);
		$this->output(
			"Purged $count rows and $mappingRowsCount client hint mapping rows purged.\n"
		);

		$this->output( "Purging data from cu_log_event..." );
		[ $count, $mappingRowsCount ] = $this->prune(
			'cu_log_event', 'cule_timestamp', $cudMaxAge,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
		);
		$this->output(
			"Purged $count rows and $mappingRowsCount client hint mapping rows purged.\n"
		);

		if ( $config->get( 'CheckUserPurgeOldClientHintsData' ) ) {
			$userAgentClientHintsManager = MediaWikiServices::getInstance()->get( 'UserAgentClientHintsManager' );
			$orphanedMappingRowsDeleted = $userAgentClientHintsManager->deleteOrphanedMapRows();
			$this->output( "Purged $orphanedMappingRowsDeleted orphaned client hint mapping rows.\n" );
		}

		if ( $config->get( MainConfigNames::PutIPinRC ) ) {
			$this->output( "Purging data from recentchanges..." );
			$rcMaxAge = $config->get( MainConfigNames::RCMaxAge );
			$count = $this->prune( 'recentchanges', 'rc_timestamp', $rcMaxAge, null );
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
	 * @return int[] An array of two integers: The first being the rows deleted in $table and
	 *  the second in cu_useragent_clienthints_map.
	 */
	protected function prune( string $table, string $ts_column, int $maxAge, ?int $clientHintMappingId ) {
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = MediaWikiServices::getInstance()->get( 'UserAgentClientHintsManager' );
		$referenceColumn = $userAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$clientHintMappingId] ?? null;
		$clientHintReferenceIds = new ClientHintsReferenceIds();
		$shouldDeleteAssociatedClientData = $this->getConfig()->get( 'CheckUserPurgeOldClientHintsData' );

		$dbw = $this->getDB( DB_PRIMARY );
		$expiredCond = "$ts_column < " . $dbw->addQuotes( $dbw->timestamp( ConvertibleTimestamp::time() - $maxAge ) );

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
		if ( $shouldDeleteAssociatedClientData ) {
			$mappingRowsDeleted = $userAgentClientHintsManager->deleteMappingRows( $clientHintReferenceIds );
		}

		return [ $deletedCount, $mappingRowsDeleted ];
	}
}

$maintClass = PurgeOldData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
