<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use Wikimedia\Rdbms\IDatabase;

/**
 * This service provides methods which can be used to purge data from the
 * local CheckUser tables
 *
 * @internal For use by PruneCheckUserDataJob and purgeOldData.php only
 */
class CheckUserDataPurger {

	/**
	 * Purge 500 rows from the given local CheckUser result table.
	 *
	 * @param IDatabase $dbw A primary database connection for the database we are purging rows from. This is provided
	 *   via the arguments in case the connection has an exclusive lock for the purging of rows.
	 * @param string $table The relevant CheckUser result table (e.g. cu_changes)
	 * @param string $idField The ID field for the $table (e.g. cuc_id)
	 * @param string $timestampField The timestamp field for the $table (e.g. cuc_timestamp)
	 * @param string $cutoff The timestamp used as a "cutoff", where rows which have a timestamp before the given
	 *   cutoff are eligible to be purged from the database
	 * @param int $clientHintMapTypeIdentifier The UserAgentClientHintsManager::IDENTIFIER_* constant for the given
	 *   $table
	 * @param ClientHintsReferenceIds $deletedReferenceIds A {@link ClientHintsReferenceIds} instance used to collect
	 *   the reference IDs associated with rows that were purged by the call to this method. The caller is
	 *   responsible for purging the Client Hints data for these reference IDs.
	 */
	public function purgeDataFromLocalTable(
		IDatabase $dbw, string $table, string $idField, string $timestampField, string $cutoff,
		int $clientHintMapTypeIdentifier, ClientHintsReferenceIds $deletedReferenceIds
	) {
		// Get at most 500 rows to purge from the given $table, selecting the row ID and associated Client Hints
		// data reference ID
		$clientHintReferenceField =
			UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$clientHintMapTypeIdentifier];
		$idQueryBuilder = $dbw->newSelectQueryBuilder()
			->field( $idField )
			->table( $table )
			->conds( $dbw->expr( $timestampField, '<', $cutoff ) )
			->limit( 500 )
			->caller( __METHOD__ );
		if ( $clientHintReferenceField !== $idField ) {
			$idQueryBuilder->field( $clientHintReferenceField );
		}
		$result = $idQueryBuilder->fetchResultSet();
		// Group the row IDs into an array so that we can process them shortly. While doing this, also
		// add the reference IDs for these rows for purging to the ClientHintsReferenceIds object.
		$ids = [];
		foreach ( $result as $row ) {
			$ids[] = $row->$idField;
			$deletedReferenceIds->addReferenceIds( $row->$clientHintReferenceField, $clientHintMapTypeIdentifier );
		}
		// Perform the purging of the rows with IDs in $ids
		if ( $ids ) {
			$dbw->newDeleteQueryBuilder()
				->table( $table )
				->where( [ $idField => $ids ] )
				->caller( __METHOD__ )
				->execute();
		}
	}
}
