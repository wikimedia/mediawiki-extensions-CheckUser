<?php

namespace MediaWiki\CheckUser\Services;

use IDatabase;
use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use StatusValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Insert user-agent client hint values and their associations with CheckUser events into database tables.
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
	private IDatabase $dbw;
	private IReadableDatabase $dbr;

	/**
	 * @param IConnectionProvider $connectionProvider
	 */
	public function __construct( IConnectionProvider $connectionProvider ) {
		$this->dbw = $connectionProvider->getPrimaryDatabase();
		$this->dbr = $connectionProvider->getReplicaDatabase();
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

		$rows = $clientHintsData->toDatabaseRows();
		$rows = $this->excludeExistingClientHintData( $rows, $usePrimary );

		if ( count( $rows ) ) {
			$this->dbw->insert(
				'cu_useragent_clienthints',
				$rows,
				__METHOD__
			);
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
		foreach ( $rows as $row ) {
			$result = $db->newSelectQueryBuilder()
				->table( 'cu_useragent_clienthints' )
				->field( 'uach_id' )
				->where( $row )
				->caller( __METHOD__ )
				->fetchField();
			$clientHintIds[] = (int)$result;
		}

		$mapRows = [];
		foreach ( $clientHintIds as $clientHintId ) {
			$mapRows[] = [
				'uachm_uach_id' => $clientHintId,
				'uachm_reference_type' => $idType,
				'uachm_reference_id' => $foreignId,
			];

		}
		$this->dbw->insert(
			'cu_useragent_clienthints_map',
			$mapRows,
			__METHOD__
		);
		return StatusValue::newGood();
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
