<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

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

		$encCutoff = $dbw->addQuotes( $dbw->timestamp(
			time() - $services->getMainConfig()->get( 'CUDMaxAge' )
		) );

		$deleteOperation = static function (
			$table, $idField, $timestampField
		) use ( $dbw, $encCutoff, $fname ) {
			$ids = $dbw->newSelectQueryBuilder()
				->table( $table )
				->field( $idField )
				->conds( [ "$timestampField < $encCutoff" ] )
				->limit( 500 )
				->caller( $fname )
				->fetchFieldValues();
			if ( $ids ) {
				$dbw->newDeleteQueryBuilder()
					->table( $table )
					->where( [ $idField => $ids ] )
					->caller( $fname )
					->execute();
			}
		};

		$deleteOperation( 'cu_changes', 'cuc_id', 'cuc_timestamp' );

		$deleteOperation( 'cu_private_event', 'cupe_id', 'cupe_timestamp' );

		$deleteOperation( 'cu_log_event', 'cule_id', 'cule_timestamp' );

		return true;
	}
}
