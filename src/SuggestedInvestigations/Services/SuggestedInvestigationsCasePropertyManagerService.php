<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

class SuggestedInvestigationsCasePropertyManagerService {

	/**
	 * Array of all supported case properties:
	 *   - The key is the id int used to identify the property
	 *   - The value is the function name that is used to calculate the
	 *     property's value.
	 */
	public const ALL_PROPERTIES = [
		self::PROPERTY_SHARED_PAGE_EDITS_COUNT => 'updateCountOfEditsOnSharedPages',
	];
	public const PROPERTY_SHARED_PAGE_EDITS_COUNT = 1;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		private readonly SuggestedInvestigationsSharedPagesLookup $sharedPagesLookup,
	) {
	}

	/**
	 * Updates a given set of $properties for a given set of $caseIds
	 * @param int[] $caseIds
	 * @param int[] $properties
	 */
	public function updatePropertiesForCases(
		array $caseIds,
		array $properties
	): void {
		$this->caseLookupService->assertCasesExist( $caseIds );

		$userIdsInCases = [];
		foreach ( $caseIds as $caseId ) {
			$userIdsInCase = $this->caseLookupService->getUsersInCase( $caseId );
			$userIdsInCases[ $caseId ] = $userIdsInCase;
		}

		$dbw = $this->dbProvider->getPrimaryDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		foreach ( $properties as $propertyKey ) {
			$methodName = self::ALL_PROPERTIES[
				$propertyKey
			] ?? null;
			if ( !$methodName ) {
				throw new InvalidArgumentException( "Invalid property id, $propertyKey" );
			}
			$this->$methodName( $dbw, $userIdsInCases );
		}
	}

	/**
	 * Update the edit-related properties of all cases the user ids belong to. This is
	 * a small wrapper function that converts user ids into cases to update.
	 *
	 * @param int[] $userIds
	 */
	public function updateEditRelatedPropertiesForCasesWithUsers( array $userIds ): void {
		$caseIds = [];
		foreach ( $userIds as $userId ) {
			$caseIds = array_merge( $caseIds, $this->caseLookupService->getOpenCaseIdsForUser( $userId ) );
		}
		$caseIds = array_unique( $caseIds );
		if ( !count( $caseIds ) ) {
			return;
		}

		$this->updateEditRelatedPropertiesForCases( $caseIds );
	}

	/**
	 * Update the edit-related properties of all case ids passed through:
	 * - Count of edits on shared pages
	 *
	 * @param int[] $caseIds
	 */
	public function updateEditRelatedPropertiesForCases( array $caseIds ): void {
		$this->caseLookupService->assertCasesExist( $caseIds );
		$this->updatePropertiesForCases( $caseIds, [ self::PROPERTY_SHARED_PAGE_EDITS_COUNT ] );
	}

	private function updateCountOfEditsOnSharedPages( IDatabase $dbw, array $userIdsInCases ): void {
		$sharedPagesForCases = $this->sharedPagesLookup->getSharedPagesForCases( $userIdsInCases );
		$rows = [];
		foreach ( $sharedPagesForCases as $caseId => $sharedPages ) {
			$rows[] = [
				'sicp_sic_id' => $caseId,
				'sicp_property' => self::PROPERTY_SHARED_PAGE_EDITS_COUNT,
				'sicp_value' => count( $sharedPages->getRevisionIds() ),
			];
		}
		$dbw->newInsertQueryBuilder()
			->insertInto( 'cusi_case_property' )
			->rows( $rows )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'sicp_sic_id', 'sicp_property' ] )
			->set( [
				'sicp_value = ' . $dbw->buildExcludedValue( 'sicp_value' ),
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
