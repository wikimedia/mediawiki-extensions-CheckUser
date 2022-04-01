<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser\Maintenance;

use LoggedUpdateMaintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for filling up cuc_actor.
 *
 * @author Zabe
 */
class PopulateCucActor extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the cuc_actor column.' );
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'PopulateCucActor';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = MediaWikiServices::getInstance();
		$actorStore = $services->getActorStore();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$mainLb = $lbFactory->getMainLB();
		$dbr = $mainLb->getConnectionRef( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$prevId = (int)$dbr->selectField(
			'cu_changes',
			'MIN(cuc_id)',
			[],
			__METHOD__
		);
		$curId = $prevId + $batchSize;
		$maxId = $dbr->selectField(
			'cu_changes',
			'MAX(cuc_id)',
			[],
			__METHOD__
		);

		if ( !$maxId ) {
			$this->output( 'The cu_changes table seems to be empty.\n' );
			return true;
		}

		$diff = $maxId - $prevId;
		$failed = 0;
		$sleep = (int)$this->getOption( 'sleep', 0 );

		do {
			$res = $dbr->select(
				'cu_changes',
				[ 'cuc_id', 'cuc_user_text' ],
				[
					'cuc_actor' => 0,
					"cuc_id BETWEEN $prevId AND $curId"
				],
				__METHOD__
			);

			foreach ( $res as $row ) {
				$actor = $actorStore->findActorIdByName( $row->cuc_user_text, $dbr );

				if ( !$actor ) {
					$failed++;
					continue;
				}

				$dbw->update(
					'cu_changes',
					[
						'cuc_actor' => $actor
					],
					[
						'cuc_id' => $row->cuc_id
					],
					__METHOD__
				);
			}

			$lbFactory->waitForReplication();

			if ( $sleep > 0 ) {
				sleep( $sleep );
			}

			$this->output( "Processed $batchSize rows out of $diff.\n" );

			$prevId = $curId;
			$curId += $batchSize;
		} while ( $prevId <= $maxId );

		$this->output( "Done. Migration failed for $failed row(s).\n" );
		return true;
	}
}

$maintClass = PopulateCucActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
