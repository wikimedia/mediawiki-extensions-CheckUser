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

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class SuggestedInvestigationsCaseManagerService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly IConnectionProvider $dbProvider,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Inserts a new Suggested Investigations case to the database with the provided users and signals.
	 *
	 * For now, only cases with one signal are supported, so the $signals array must contain exactly one element.
	 * @throws InvalidArgumentException If $users is empty or more than one signal is provided, this throws.
	 * @param UserIdentity[] $users
	 * @phan-param non-empty-array $users
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 * @phan-param non-empty-array $signals
	 * @return int The ID of the created case
	 */
	public function createCase( array $users, array $signals ): int {
		$this->assertSuggestedInvestigationsEnabled();
		if ( count( $users ) === 0 ) {
			throw new InvalidArgumentException( 'At least one user must be provided to create a case' );
		}
		if ( count( $signals ) !== 1 ) {
			throw new InvalidArgumentException( 'Exactly one signal must be provided to create a case' );
		}

		$dbw = $this->getPrimaryDatabase();

		try {
			$dbw->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );
			$dbw->newInsertQueryBuilder()
				->insert( 'cusi_case' )
				->row( [
					'sic_created_timestamp' => $dbw->timestamp()
				] )
				->caller( __METHOD__ )
				->execute();
			$caseId = $dbw->insertId();

			// We don't need to check if the case exists, so let's just use the internal versions of methods
			$this->addUsersToCaseInternal( $caseId, $users );
			$this->addSignalsToCaseInternal( $caseId, $signals );
			$dbw->endAtomic( __METHOD__ );
		} catch ( \Exception $e ) {
			// Ensure we cancel the atomic block if an exception is thrown
			$dbw->cancelAtomic( __METHOD__ );
			throw $e;
		}

		return $caseId;
	}

	/**
	 * Adds an array of users to an existing Suggested Investigations case. If a user
	 * is already attached to the case, they will not be added again.
	 * @throws InvalidArgumentException When $caseId does not match an existing case
	 * @param int $caseId
	 * @param UserIdentity[] $users
	 */
	public function addUsersToCase( int $caseId, array $users ): void {
		$this->assertSuggestedInvestigationsEnabled();
		if ( !$this->caseExists( $caseId ) ) {
			throw new InvalidArgumentException( "Case ID $caseId does not exist" );
		}
		if ( count( $users ) === 0 ) {
			return;
		}

		$this->addUsersToCaseInternal( $caseId, $users );
	}

	/**
	 * Adds users to a case, skipping the input data checks.
	 * @param int $caseId
	 * @param UserIdentity[] $users
	 */
	private function addUsersToCaseInternal( int $caseId, array $users ): void {
		$dbw = $this->getPrimaryDatabase();

		// Using array_values to silence Phan warning about $rows being associative
		$rows = array_map( static fn ( $user ) => [
			'siu_sic_id' => $caseId,
			'siu_user_id' => $user->getId(),
		], array_values( $users ) );

		$dbw->newInsertQueryBuilder()
			->insert( 'cusi_user' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Adds signals to a case, skipping the input data checks.
	 *
	 * Currently, there's no public method to add a signal, as we support only having a single signal
	 * on a case. Once that changes, we can expose a public method, similar to {@link addUsersToCase}.
	 *
	 * @param int $caseId
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals
	 */
	private function addSignalsToCaseInternal( int $caseId, array $signals ): void {
		$dbw = $this->getPrimaryDatabase();

		// Using array_values to silence Phan warning about $rows being associative
		$rows = array_map( static fn ( $signal ) => [
			'sis_sic_id' => $caseId,
			'sis_name' => $signal->getName(),
			'sis_value' => $signal->getValue(),
		], array_values( $signals ) );

		$dbw->newInsertQueryBuilder()
			->insert( 'cusi_signal' )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/** Helper function to check if a case with given ID exists */
	private function caseExists( int $caseId ): bool {
		$dbw = $this->getReplicaDatabase();
		$rowCount = $dbw->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		return $rowCount > 0;
	}

	/** Helper function to return early if SI is not enabled, so we don't interact with non-existing tables in DB */
	private function assertSuggestedInvestigationsEnabled(): void {
		if ( !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			throw new \RuntimeException( 'Suggested Investigations is not enabled' );
		}
	}

	/** Returns a connection to the primary database with SI tables */
	private function getPrimaryDatabase(): IDatabase {
		return $this->dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
	}

	/** Returns a connection to the replica database with SI tables */
	private function getReplicaDatabase(): IReadableDatabase {
		return $this->dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
	}
}
