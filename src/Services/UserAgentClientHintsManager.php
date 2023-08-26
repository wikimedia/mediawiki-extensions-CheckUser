<?php

namespace MediaWiki\CheckUser\Services;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Service to insert and delete user-agent client hint values and their associations with rows in cu_changes,
 * cu_log_event and cu_private_event.
 */
class UserAgentClientHintsManager {

	public const SUPPORTED_TYPES = [
		'revision',
	];

	/**
	 * TINYINT references for use in cu_useragent_clienthints_map.uachm_reference_type
	 */
	// Identifier for the cu_changes table
	public const IDENTIFIER_CU_CHANGES = 0;
	// Identifier for the cu_log_event table
	public const IDENTIFIER_CU_LOG_EVENT = 1;
	// Identifier for the cu_private_event table
	public const IDENTIFIER_CU_PRIVATE_EVENT = 2;

	public const IDENTIFIER_TO_TABLE_NAME_MAP = [
		self::IDENTIFIER_CU_CHANGES => 'cu_changes',
		self::IDENTIFIER_CU_LOG_EVENT => 'cu_log_event',
		self::IDENTIFIER_CU_PRIVATE_EVENT => 'cu_private_event',
	];
	public const IDENTIFIER_TO_COLUMN_NAME_MAP = [
		self::IDENTIFIER_CU_CHANGES => 'cuc_this_oldid',
		self::IDENTIFIER_CU_LOG_EVENT => 'cule_log_id',
		self::IDENTIFIER_CU_PRIVATE_EVENT => 'cupe_id',
	];
	private IDatabase $dbw;
	private IReadableDatabase $dbr;
	private LoggerInterface $logger;

	/**
	 * @param IConnectionProvider $connectionProvider
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IConnectionProvider $connectionProvider, LoggerInterface $logger
	) {
		$this->dbw = $connectionProvider->getPrimaryDatabase();
		$this->dbr = $connectionProvider->getReplicaDatabase();
		$this->logger = $logger;
	}

	/**
	 * Given an array of client hint data, a reference ID, and an identifier type, record the data to the
	 * cu_useragent_clienthints and cu_useragent_clienthints_map tables.
	 *
	 * @param ClientHintsData $clientHintsData
	 * @param int $referenceId An ID to use in `uachm_reference_id` column in the
	 *   cu_useragent_clienthints_map table
	 * @param string $type The type of event this data is associated with. Valid values are:
	 *  - revision
	 * @param bool $usePrimary If true, use the primary DB for SELECT queries.
	 * @return StatusValue
	 */
	public function insertClientHintValues(
		ClientHintsData $clientHintsData, int $referenceId, string $type, bool $usePrimary = false
	): StatusValue {
		// Check if there are rows to insert to the map table.
		$rows = $clientHintsData->toDatabaseRows();
		if ( !count( $rows ) ) {
			// Nothing to insert, so return early.
			// Having nothing to insert isn't considered "bad", so return a new good
			// For example, a browser could choose to provide no Client Hints data but
			// still send an empty API request.
			return StatusValue::newGood();
		}

		// Check for existing entry.
		$existingRecord = $this->dbr->newSelectQueryBuilder()
			->table( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => $this->getMapIdByType( $type ),
				'uachm_reference_id' => $referenceId
			] )
			->caller( __METHOD__ )
			->fetchRowCount();
		if ( $existingRecord ) {
			return StatusValue::newFatal(
				'checkuser-api-useragent-clienthints-mappings-exist',
				[ $type, $referenceId ]
			);
		}

		$rows = $this->excludeExistingClientHintData( $rows, $usePrimary );

		if ( count( $rows ) ) {
			$this->dbw->newInsertQueryBuilder()
				->insert( 'cu_useragent_clienthints' )
				->ignore()
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
			// We just inserted rows to cu_useragent_clienthints, so
			// use the primary DB for subsequent SELECT queries.
			$usePrimary = true;
		}
		return $this->insertMappingRows( $clientHintsData, $referenceId, $type, $usePrimary );
	}

	/**
	 * Given an identifier for the type of event (e.g. 'revision'), return the relevant TINYINT
	 * for the table that the database entry for cu_useragent_clienthints_map refers to
	 *
	 * @param string $type
	 * @return int One of self::IDENTIFIER_* constants
	 */
	private function getMapIdByType( string $type ): int {
		switch ( $type ) {
			case 'revision':
				return self::IDENTIFIER_CU_CHANGES;
			default:
				throw new LogicException( "Invalid type $type" );
		}
	}

	/**
	 * Insert rows into the cu_useragent_clienthints_map table.
	 *
	 * This links a foreign ID (e.g. "revision 1234") with client hint data values stored in cu_useragent_clienthints.
	 *
	 * @param ClientHintsData $clientHintsData
	 * @param int $foreignId
	 * @param string $type
	 * @param bool $usePrimary If true, use the primary DB for SELECT queries.
	 * @return StatusValue
	 * @see insertClientHintValues, which invokes this method.
	 *
	 */
	private function insertMappingRows(
		ClientHintsData $clientHintsData, int $foreignId, string $type, bool $usePrimary = false
	): StatusValue {
		// Get the cu_useragent_clienthints ID for each pair of client hint name/value
		$clientHintIds = [];
		$rows = $clientHintsData->toDatabaseRows();
		// We might need primary DB if the call is happening in the context of a server-side hook,
		$db = $usePrimary ? $this->dbw : $this->dbr;

		// TINYINT reference to cu_changes, cu_log_event or cu_private_event.
		$idType = $this->getMapIdByType( $type );
		$mapRows = [];
		foreach ( $rows as $row ) {
			$result = $db->newSelectQueryBuilder()
				->table( 'cu_useragent_clienthints' )
				->field( 'uach_id' )
				->where( $row )
				->caller( __METHOD__ )
				->fetchField();
			if ( $result !== false ) {
				$mapRows[] = [
					'uachm_uach_id' => (int)$result,
					'uachm_reference_type' => $idType,
					'uachm_reference_id' => $foreignId,
				];
			} else {
				$this->logger->warning(
					"Lookup failed for cu_useragent_clienthints row with name {name} and value {value}.",
					[ $row['uach_name'], $row['uach_value'] ]
				);
			}
		}

		if ( count( $mapRows ) ) {
			$this->dbw->newInsertQueryBuilder()
				->insert( 'cu_useragent_clienthints_map' )
				->rows( $mapRows )
				->caller( __METHOD__ )
				->execute();
		}
		return StatusValue::newGood();
	}

	/**
	 * Given reference IDs this method finds and deletes
	 * the mapping entries for these reference IDs.
	 *
	 * If the cu_useragent_clienthint rows associated with the
	 * deleted mapping rows are now "orphaned" (not referenced
	 * by any mapping row), this method will delete them.
	 *
	 * @param ClientHintsReferenceIds $clientHintsReferenceIds
	 * @return StatusValue
	 */
	public function deleteMappingRows( ClientHintsReferenceIds $clientHintsReferenceIds ): StatusValue {
		// An array to store rows in cu_useragent_clienthints that were associated with
		//  now deleted cu_useragent_clienthints_map rows.
		$clientHintIds = [];
		$mappingRowsDeleted = 0;
		$orphanedClientHintRowsDeleted = 0;
		foreach ( $clientHintsReferenceIds->getReferenceIds() as $mapId => $referenceIds ) {
			if ( !count( $referenceIds ) ) {
				continue;
			}
			// Get the IDs for the associated rows in the cu_useragent_clienthints table
			$clientHintIds = array_merge(
				$clientHintIds,
				$this->dbr->newSelectQueryBuilder()
					->table( 'cu_useragent_clienthints_map' )
					->field( 'uachm_uach_id' )
					->where( [
						'uachm_reference_id' => $referenceIds,
						'uachm_reference_type' => $mapId
					] )
					->caller( __METHOD__ )
					->distinct()
					->fetchFieldValues()
			);
			// Delete the rows in cu_useragent_clienthints_map associated with these reference IDs
			$this->dbw->newDeleteQueryBuilder()
				->table( 'cu_useragent_clienthints_map' )
				->where( [
					'uachm_reference_id' => $referenceIds,
					'uachm_reference_type' => $mapId
				] )
				->caller( __METHOD__ )
				->execute();
			$mappingRowsDeleted += $this->dbw->affectedRows();
		}
		if ( count( $clientHintIds ) ) {
			// Check if the cu_useragent_clienthints rows with IDs in $clientHintIds are now orphaned.
			// If they are orphaned, delete them.
			//
			// Read from primary as the deletes just occurred which would affect the query.
			$orphanedClientHintRowIds = array_values( array_diff(
				$clientHintIds,
				$this->dbw->newSelectQueryBuilder()
					->table( 'cu_useragent_clienthints_map' )
					->field( 'uachm_uach_id' )
					->where( [
						'uachm_uach_id' => $clientHintIds
					] )
					->distinct()
					->caller( __METHOD__ )
					->fetchFieldValues()
			) );
			if ( count( $orphanedClientHintRowIds ) ) {
				// Now delete the orphaned cu_useragent_clienthints rows.
				$this->dbw->newDeleteQueryBuilder()
					->table( 'cu_useragent_clienthints' )
					->where( [
						'uach_id' => $orphanedClientHintRowIds
					] )
					->caller( __METHOD__ )
					->execute();
				$orphanedClientHintRowsDeleted = $this->dbw->affectedRows();
			}
		}
		if ( !$mappingRowsDeleted ) {
			$this->logger->info( "No mapping rows deleted." );
		} else {
			$this->logger->debug(
				"Deleted {mapping_rows_deleted} mapping rows and " .
				"{orphaned_client_hint_rows_deleted} orphaned client hint data rows.",
				[
					'mapping_rows_deleted' => $mappingRowsDeleted,
					'orphaned_client_hint_rows_deleted' => $orphanedClientHintRowsDeleted
				]
			);
		}
		return StatusValue::newGood( [ $mappingRowsDeleted, $orphanedClientHintRowsDeleted ] );
	}

	/**
	 * Helper method to avoid duplicate INSERT for existing client hint values.
	 *
	 * E.g. if "architecture: arm" already exists as a name/value pair, exclude this from the set of rows to insert.
	 *
	 * @param array[] $rows An array of arrays, where each array contains a key/value pair:
	 *  uach_name => "some name",
	 *  uach_value => "some value"
	 * @param bool $usePrimary If true, use the primary DB for SELECT queries.
	 * @return array[] An array of arrays to insert to the cu_useragent_clienthints table, see the @param $rows
	 *  documentation for the format.
	 */
	private function excludeExistingClientHintData( array $rows, bool $usePrimary = false ): array {
		$rowsToInsert = [];
		$db = $usePrimary ? $this->dbw : $this->dbr;
		foreach ( $rows as $row ) {
			$result = $db->newSelectQueryBuilder()
				->table( 'cu_useragent_clienthints' )
				->where( $row )
				->caller( __METHOD__ )
				->fetchRowCount();
			if ( $result === 0 ) {
				$rowsToInsert[] = $row;
			}
		}
		return $rowsToInsert;
	}

}
