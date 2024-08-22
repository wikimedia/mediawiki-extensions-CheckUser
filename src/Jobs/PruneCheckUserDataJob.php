<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserDataPurger;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Prune data from the three CheckUser tables (cu_changes, cu_log_event and cu_private_event)
 * using a job to avoid doing this post send.
 */
class PruneCheckUserDataJob extends Job implements CheckUserQueryInterface {
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
		$key = CheckUserDataPurger::getPurgeLockKey( $this->params['domainID'] );
		$scopedLock = $dbw->getScopedLockAndFlush( $key, $fname, 1 );
		if ( !$scopedLock ) {
			return true;
		}

		$cutoff = $dbw->timestamp(
			ConvertibleTimestamp::time() - $services->getMainConfig()->get( 'CUDMaxAge' )
		);

		$deletedReferenceIds = new ClientHintsReferenceIds();

		/** @var CheckUserDataPurger $checkUserDataPurger */
		$checkUserDataPurger = $services->get( 'CheckUserDataPurger' );

		foreach ( self::RESULT_TABLES as $table ) {
			$checkUserDataPurger->purgeDataFromLocalTable(
				$dbw, $table, $cutoff, $deletedReferenceIds, __METHOD__
			);
		}

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $services->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->deleteMappingRows( $deletedReferenceIds );

		return true;
	}
}
