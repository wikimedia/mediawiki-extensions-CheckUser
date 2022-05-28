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
use Title;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for filling up cul_reason_id
 * and cul_reason_plaintext_id.
 *
 * Copied and modified from populateCucActor.php
 *
 * @author Dreamy Jazz and Zabe
 */
class PopulateLogReasonCommentIDs extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Populate the cul_reason_id and cul_reason_plaintext_id column
		with non-negative integers.' );
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
		return 'PopulateCulReasonCommentIDs';
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$services = MediaWikiServices::getInstance();
		$commentStore = $services->getCommentStore();
		$lbFactory = $services->getDBLoadBalancerFactory();
		$mainLb = $lbFactory->getMainLB();
		$dbr = $mainLb->getConnectionRef( DB_REPLICA, 'vslow' );
		$dbw = $mainLb->getConnectionRef( DB_PRIMARY );
		$batchSize = $this->getBatchSize();

		$prevId = (int)$dbr->selectField(
			'cu_log',
			'MIN(cul_id)',
			[],
			__METHOD__
		);
		$maxId = $dbr->selectField(
			'cu_log',
			'MAX(cul_id)',
			[],
			__METHOD__
		);

		$curId = $prevId + $batchSize;

		if ( !$maxId ) {
			$this->output( 'The cu_log table seems to be empty.\n' );
			return true;
		}

		if (
			!$dbr->fieldExists( 'cu_log', 'cul_reason_id' ) ||
			!$dbr->fieldExists( 'cu_log', 'cul_reason_plaintext_id' )
		) {
			$this->output( 'The cu_log table seems to be missing cul_reason_id and cul_reason_plaintext_id.\n
			Are you sure you have updated the DB?\n' );
			return false;
		}

		$diff = $maxId - $prevId;
		$sleep = (int)$this->getOption( 'sleep', 0 );

		do {
			$res = $dbr->select(
				'cu_log',
				[ 'cul_id', 'cul_reason_id', 'cul_reason_plaintext_id', 'cul_reason' ],
				[
					'(cul_reason_id is NULL OR cul_reason_plaintext_id is NULL)',
					"cul_id BETWEEN $prevId AND $curId"
				],
				__METHOD__
			);

			foreach ( $res as $row ) {
				// Get (or generate) the associated plaintext and non-plaintext reason
				// from the value in cul_reason.

				$title = Title::newFromText( 'Special:CheckUser' );
				$plaintextReason = \ApiErrorFormatter::stripMarkup(
					MediaWikiServices::getInstance()->getCommentFormatter()->formatBlock(
						$row->cul_reason, $title, false, false, false
					)
				);

				$dbw->update(
					'cu_log',
					array_merge(
						$commentStore->insert( $dbw, 'cul_reason', $row->cul_reason ),
						$commentStore->insert( $dbw, 'cul_reason_plaintext', $plaintextReason )
					),
					[
						'cul_id' => $row->cul_id
					],
					__METHOD__
				);
			}

			$lbFactory->waitForReplication();

			if ( $sleep > 0 ) {
				sleep( $sleep );
			}

			$this->output( "Processed batch of $batchSize rows out of $diff total rows.\n" );

			$prevId = $curId;
			$curId += $batchSize;
		} while ( $prevId <= $maxId );

		$this->output( "Done.\n" );
		return true;
	}
}

$maintClass = PopulateLogReasonCommentIDs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
