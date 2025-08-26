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

namespace MediaWiki\CheckUser\SuggestedInvestigations;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\CodexTablePager;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

class SuggestedInvestigationsTablePager extends CodexTablePager {

	/** Database with the users table */
	private IReadableDatabase $userDb;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		?IContextSource $context = null,
		?LinkRenderer $linkRenderer = null
	) {
		// If we didn't set mDb here, the parent constructor would set it to the database replica
		// for the default database domain
		$this->mDb = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		parent::__construct(
			$this->msg( 'checkuser-suggestedinvestigations-table-caption' )->text(),
			$context,
			$linkRenderer
		);

		$this->mDefaultDirection = self::QUERY_DESCENDING;

		$this->userDb = $this->connectionProvider->getReplicaDatabase();
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		// TODO: Actually implement formatValue() method (T403007).
		if ( is_array( $value ) ) {
			return $name == 'signals' ? print_r( $value, true )
				: implode( ', ', $value );
		}
		return (string)$value;
	}

	/** @inheritDoc */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$cases = parent::reallyDoQuery( $offset, $limit, $order );

		$caseIds = [];
		foreach ( $cases as $case ) {
			$caseIds[] = $case->sic_id;
		}

		$signals = $this->querySignalsForCases( $caseIds );
		$caseUsers = $this->queryUsersForCases( $caseIds );

		$result = [];

		foreach ( $cases as $caseRow ) {
			$caseRow->signals = $signals[$caseRow->sic_id] ?? [];
			$caseRow->users = $caseUsers[$caseRow->sic_id] ?? [];
			$result[] = $caseRow;
		}

		return new FakeResultWrapper( $result );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		return [
			'tables' => [
				'cusi_case'
			],
			'fields' => [
				'sic_id',
				'sic_status',
				'sic_created_timestamp',
				'sic_status_reason'
			]
		];
	}

	/**
	 * Returns an array that maps each case ID to an array of signals. The signals are returned
	 * as arrays with 'name' and 'value' keys.
	 */
	private function querySignalsForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'sis_sic_id', 'sis_name', 'sis_value' ] )
			->from( 'cusi_signal' )
			->where( [
				'sis_sic_id' => $caseIds
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$signalsForCases = [];
		foreach ( $result as $row ) {
			$caseId = $row->sis_sic_id;
			if ( !isset( $signalsForCases[$caseId] ) ) {
				$signalsForCases[$caseId] = [];
			}

			$signalsForCases[$caseId][] = [
				'name' => $row->sis_name,
				'value' => $row->sis_value
			];
		}

		return $signalsForCases;
	}

	/**
	 * Returns an array that maps each case ID to an array of usernames associated with that case.
	 * @return string[][]
	 */
	private function queryUsersForCases( array $caseIds ): array {
		if ( count( $caseIds ) === 0 ) {
			return [];
		}

		$dbr = $this->getDatabase();
		$resultCaseUserId = $dbr->newSelectQueryBuilder()
			->select( [ 'siu_sic_id', 'siu_user_id' ] )
			->from( 'cusi_user' )
			->where( [
				'siu_sic_id' => $caseIds
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$userIds = [];
		foreach ( $resultCaseUserId as $row ) {
			$userIds[] = $row->siu_user_id;
		}
		$userIds = array_unique( $userIds );

		$dbrUsers = $this->userDb;
		$userIdToName = [];
		foreach ( array_chunk( $userIds, 100 ) as $userIdChunk ) {
			$resultUsers = $dbrUsers->newSelectQueryBuilder()
				->select( [ 'user_id', 'user_name' ] )
				->from( 'user' )
				->where( [
					'user_id' => $userIdChunk
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $resultUsers as $row ) {
				$userIdToName[$row->user_id] = $row->user_name;
			}
		}

		$usersForCases = [];
		foreach ( $resultCaseUserId as $row ) {
			$caseId = $row->siu_sic_id;
			if ( !isset( $usersForCases[$caseId] ) ) {
				$usersForCases[$caseId] = [];
			}

			$userId = $row->siu_user_id;
			$userName = $userIdToName[$userId];
			$usersForCases[$caseId][] = $userName;
		}

		return $usersForCases;
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		return $field === 'sic_created_timestamp'
			|| $field === 'sic_status';
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'sic_created_timestamp';
	}

	/** @inheritDoc */
	public function getIndexField() {
		return [
			'sic_created_timestamp' => [ 'sic_created_timestamp', 'sic_id' ],
			'sic_status' => [ 'sic_status', 'sic_created_timestamp', 'sic_id' ],
		];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		return [
			'users' => $this->msg( 'checkuser-suggestedinvestigations-header-users' )->text(),
			'signals' => $this->msg( 'checkuser-suggestedinvestigations-header-signals' )->text(),
			'sic_created_timestamp' => $this->msg( 'checkuser-suggestedinvestigations-header-created' )->text(),
			'sic_status' => $this->msg( 'checkuser-suggestedinvestigations-header-status' )->text(),
			'sic_status_reason' => $this->msg( 'checkuser-suggestedinvestigations-header-notes' )->text(),
			'actions' => $this->msg( 'checkuser-suggestedinvestigations-header-actions' )->text(),
		];
	}
}
