<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Prune data from the three CheckUser tables (cu_changes, cu_log_event and cu_private_event)
 * using a job to avoid doing this post send.
 */
class PruneCheckUserDataJob extends Job {
	/** @inheritDoc */
	public function __construct( $title, $params ) {
		parent::__construct( 'checkuserPruneCheckUserDataJob', $params );
	}

	/** @return bool */
	public function run() {
		$services = MediaWikiServices::getInstance();
		$fname = __METHOD__;

		$lb = $services->getDBLoadBalancer();
		$dbw = $lb->getMaintenanceConnectionRef( ILoadBalancer::DB_PRIMARY, $this->params['domainID'] );

		// per-wiki
		$key = "{$this->params['domainID']}:PruneCheckUserData";
		$scopedLock = $dbw->getScopedLockAndFlush( $key, $fname, 1 );
		if ( !$scopedLock ) {
			return true;
		}

		$cutoff = $dbw->timestamp(
			ConvertibleTimestamp::time() - $services->getMainConfig()->get( 'CUDMaxAge' )
		);

		$deleteOperation = static function (
			$table, $idField, $timestampField, $clientHintMapTypeIdentifier
		) use ( $dbw, $cutoff, $fname ) {
			// Get at most 500 rows to purge from the given $table, selecting the row ID and associated Client Hints
			// data reference ID
			$clientHintReferenceField =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[$clientHintMapTypeIdentifier];
			$idQueryBuilder = $dbw->newSelectQueryBuilder()
				->field( $idField )
				->table( $table )
				->conds( $dbw->expr( $timestampField, '<', $cutoff ) )
				->limit( 500 )
				->caller( $fname );
			if ( $clientHintReferenceField !== $idField ) {
				$idQueryBuilder->field( $clientHintReferenceField );
			}
			$result = $idQueryBuilder->fetchResultSet();
			// Group the row IDs and Client Hints data reference IDs into two arrays
			$ids = [];
			$referenceIds = [];
			foreach ( $result as $row ) {
				$ids[] = $row->$idField;
				$referenceIds[] = $row->$clientHintReferenceField;
			}
			// Perform the purging of the rows with IDs in $ids
			if ( $ids ) {
				$dbw->newDeleteQueryBuilder()
					->table( $table )
					->where( [ $idField => $ids ] )
					->caller( $fname )
					->execute();
			}
			// Return the Client Hints reference IDs for the now deleted rows for purging by
			// UserAgentClientHintsManager::deleteMappingRows
			return $referenceIds;
		};

		$deletedReferenceIds = new ClientHintsReferenceIds();

		$deletedReferenceIds->addReferenceIds(
			$deleteOperation(
				'cu_changes', 'cuc_id', 'cuc_timestamp',
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
			),
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
		);

		$deletedReferenceIds->addReferenceIds(
			$deleteOperation(
				'cu_private_event', 'cupe_id', 'cupe_timestamp',
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
			),
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
		);

		$deletedReferenceIds->addReferenceIds(
			$deleteOperation(
				'cu_log_event', 'cule_id', 'cule_timestamp',
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
			),
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
		);

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $services->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->deleteMappingRows( $deletedReferenceIds );

		return true;
	}
}
