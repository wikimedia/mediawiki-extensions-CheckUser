<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Service to insert and delete rows in the CheckUser central index tables
 */
class CheckUserCentralIndexManager implements CheckUserQueryInterface {
	private IConnectionProvider $dbProvider;

	/**
	 * @param IConnectionProvider $dbProvider
	 */
	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * Purge a given number of expired rows from the central index tables where the wiki is the local wiki.
	 *
	 * We need to purge rows per-wiki, as each wiki can have it's own value for the expiry of CU data.
	 *
	 * @param string $cutoff The timestamp used as a "cutoff", where rows which have a timestamp before the given
	 *    cutoff are eligible to be purged from the database
	 * @param string $domain The DB name of the wiki that we are purging rows from
	 * @param int $maximumRowsToPurge The maximum number of rows to purge from cuci_temp_edit and cuci_user
	 * @return int The number of rows that were purged
	 */
	public function purgeExpiredRows( string $cutoff, string $domain, int $maximumRowsToPurge = 100 ): int {
		// Find the ID associated with this DB domain, or if it is not present in the cuci_wiki_map table then
		// return early as there will be no matching rows to purge.
		$dbr = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );
		$wikiId = $dbr->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => $domain ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $wikiId === false ) {
			return 0;
		}

		// First purge rows from cuci_temp_edit
		$dbw = $this->dbProvider->getPrimaryDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );
		$ipsToPurge = $dbw->newSelectQueryBuilder()
			->forUpdate()
			->select( 'cite_ip_hex' )
			->from( 'cuci_temp_edit' )
			->where( [ 'cite_ciwm_id' => $wikiId, $dbw->expr( 'cite_timestamp', '<', $cutoff ) ] )
			->limit( $maximumRowsToPurge )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( count( $ipsToPurge ) ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'cuci_temp_edit' )
				->where( [ 'cite_ciwm_id' => $wikiId, 'cite_ip_hex' => $ipsToPurge ] )
				->caller( __METHOD__ )
				->execute();
		}

		// Then purge rows from cuci_user
		$centralIdsToPurge = $dbw->newSelectQueryBuilder()
			->forUpdate()
			->select( 'ciu_central_id' )
			->from( 'cuci_user' )
			->where( [ 'ciu_ciwm_id' => $wikiId, $dbw->expr( 'ciu_timestamp', '<', $cutoff ) ] )
			->limit( $maximumRowsToPurge )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( count( $centralIdsToPurge ) ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'cuci_user' )
				->where( [ 'ciu_ciwm_id' => $wikiId, 'ciu_central_id' => $centralIdsToPurge ] )
				->caller( __METHOD__ )
				->execute();
		}

		// Return the sum of the rows found for purging. We do this, instead of ::affectedRows, because the
		// aforementioned method does not work if a DELETE statement was not run (like in the case of
		// 0 rows found for purging).
		return count( $ipsToPurge ) + count( $centralIdsToPurge );
	}
}
