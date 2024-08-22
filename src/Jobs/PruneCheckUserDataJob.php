<?php

namespace MediaWiki\CheckUser\Jobs;

use Job;
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

		$deletedReferenceIds = new ClientHintsReferenceIds();

		/** @var CheckUserDataPurger $checkUserDataPurger */
		$checkUserDataPurger = $services->get( 'CheckUserDataPurger' );

		$checkUserDataPurger->purgeDataFromLocalTable(
			$dbw, 'cu_changes', 'cuc_id', 'cuc_timestamp', $cutoff,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES, $deletedReferenceIds
		);

		$checkUserDataPurger->purgeDataFromLocalTable(
			$dbw, 'cu_private_event', 'cupe_id', 'cupe_timestamp', $cutoff,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT, $deletedReferenceIds
		);

		$checkUserDataPurger->purgeDataFromLocalTable(
			$dbw, 'cu_log_event', 'cule_id', 'cule_timestamp', $cutoff,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT, $deletedReferenceIds
		);

		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $services->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->deleteMappingRows( $deletedReferenceIds );

		return true;
	}
}
