<?php

namespace MediaWiki\CheckUser;

use JobSpecification;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\MediaWikiServices;
use RecentChange;

class Hooks implements RecentChange_saveHook {

	/**
	 * Hook function for RecentChange_save. Saves data about the RecentChange object, along with private user data
	 * (such as their IP address and user agent string) from the main request, in the CheckUser result tables
	 * so that it can be queried by a CheckUser if they run a check.
	 *
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry
	 *
	 * @param RecentChange $rc
	 * @deprecated since 1.43. Use CheckUserInsert::updateCheckUserData instead.
	 */
	public static function updateCheckUserData( RecentChange $rc ) {
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
		$checkUserInsert->updateCheckUserData( $rc );
	}

	/**
	 * Hook function to prune data from the cu_changes table
	 *
	 * The chance of actually pruning data is 1/10.
	 */
	private function maybePruneIPData() {
		if ( mt_rand( 0, 9 ) == 0 ) {
			$this->pruneIPData();
		}
	}

	/**
	 * Prunes at most 500 entries from the cu_changes,
	 * cu_private_event, and cu_log_event tables separately
	 * that have exceeded the maximum time that they can
	 * be stored.
	 */
	private function pruneIPData() {
		$services = MediaWikiServices::getInstance();
		$services->getJobQueueGroup()->push(
			new JobSpecification(
				'checkuserPruneCheckUserDataJob',
				[
					'domainID' => $services
						->getDBLoadBalancer()
						->getConnection( DB_PRIMARY )
						->getDomainID()
				],
				[],
				null
			)
		);
	}

	/**
	 * @param RecentChange $recentChange
	 */
	public function onRecentChange_save( $recentChange ) {
		self::updateCheckUserData( $recentChange );
		$this->maybePruneIPData();
	}
}
