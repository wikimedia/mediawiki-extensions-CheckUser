<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsRelatedCasesSummary;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Computes, for each Suggested Investigations case, the other cases that are "related" to it.
 *
 * Two cases are related when they have the identical set of accounts (same users, no extras on
 * either side) AND a non-empty intersection of signals, where signals are compared by name.
 * Relatedness spans all case statuses (open, resolved and invalid) and a case is never
 * related to itself.
 */
class SuggestedInvestigationsRelatedCasesLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	/**
	 * Computes the related-cases summary for each provided case.
	 *
	 * @param array<int,UserIdentity[]> $caseIdToUsers Maps each case ID to the list of accounts in that case.
	 * @return array<int,SuggestedInvestigationsRelatedCasesSummary> Maps each input case ID to its related-cases
	 *   summary. The output contains items for all input cases.
	 */
	public function getCasesRelatedToCases( array $caseIdToUsers ): array {
		if ( !$caseIdToUsers ) {
			return [];
		}

		// First, find all possible candidate cases, based on users
		// The candidate cases here do overlap with the input cases and don't have to agree with input cases on
		// the number of users or the signals - we'll check that later.
		// But for sure all related cases are in the candidate set.
		$userIds = array_map( static fn ( $u ) => $u->getId(), array_merge( ...$caseIdToUsers ) );
		$userIdsInCandidateCases = $this->getCasesWithUsers( $userIds );
		foreach ( array_keys( $userIdsInCandidateCases ) as $caseId ) {
			sort( $userIdsInCandidateCases[$caseId] );
		}

		$mainCaseIds = array_keys( $caseIdToUsers );

		$sameUsersCandidates = [];
		$casesToCheckSignalsFor = [];
		foreach ( $mainCaseIds as $mainCaseId ) {
			$mainCaseUserIds = $userIdsInCandidateCases[$mainCaseId];

			foreach ( $userIdsInCandidateCases as $candidateCaseId => $candidateCaseUserIds ) {
				if ( $mainCaseId === $candidateCaseId ) {
					continue;
				}
				if ( $mainCaseUserIds === $candidateCaseUserIds ) {
					$sameUsersCandidates[$mainCaseId][] = $candidateCaseId;
					$casesToCheckSignalsFor[$mainCaseId] = true;
					$casesToCheckSignalsFor[$candidateCaseId] = true;
				}
			}
		}

		// Finally, out of case pairs that agree based on their user sets, keep only those that
		// have non-empty intersection of signals (name only, value is irrelevant)
		$relatedCasesResult = [];
		$signals = $this->getSignalsInCases( array_keys( $casesToCheckSignalsFor ) );
		foreach ( $sameUsersCandidates as $mainCaseId => $candidateCaseIds ) {
			$mainCaseSignals = $signals[$mainCaseId] ?? [];

			$relatedCases = [];
			foreach ( $candidateCaseIds as $candidateCaseId ) {
				$candidateSignals = $signals[$candidateCaseId] ?? [];

				if ( array_intersect( $mainCaseSignals, $candidateSignals ) ) {
					$relatedCases[] = $candidateCaseId;
				}
			}
			$relatedCasesResult[$mainCaseId] = new SuggestedInvestigationsRelatedCasesSummary( $relatedCases );
		}

		foreach ( array_keys( $caseIdToUsers ) as $caseId ) {
			if ( !isset( $relatedCasesResult[$caseId] ) ) {
				$relatedCasesResult[$caseId] = new SuggestedInvestigationsRelatedCasesSummary( [] );
			}
		}
		return $relatedCasesResult;
	}

	/**
	 * @param int[] $userIds
	 * @return array<int,list<int>> Array of candidate cases, mapping case id to users in that case. Values
	 *     of the inner array are guaranteed to be sorted ascending.
	 */
	private function getCasesWithUsers( array $userIds ): array {
		$dbr = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$candidateRelatedCases = [];

		$batchSize = 500;
		$lastFoundSicId = 0;
		$lastFoundUserId = 0;
		do {
			$result = $dbr->newSelectQueryBuilder()
				->select( [
					'found_case' => 'siu_found.siu_sic_id',
					'found_user' => 'siu_found.siu_user_id',
				] )
				->distinct()
				->from( 'cusi_user', 'siu_known' )
				->join( 'cusi_user', 'siu_found', [
					'siu_known.siu_sic_id = siu_found.siu_sic_id',
				] )
				->where( [
					'siu_known.siu_user_id' => $userIds,
					$dbr->buildComparison( '>', [
						'siu_found.siu_sic_id' => $lastFoundSicId,
						'siu_found.siu_user_id' => $lastFoundUserId,
					] ),
				] )
				->orderBy(
					[ 'siu_found.siu_sic_id', 'siu_found.siu_user_id' ],
					SelectQueryBuilder::SORT_ASC
				)
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$lastFoundSicId = (int)$row->found_case;
				$lastFoundUserId = (int)$row->found_user;
				$candidateRelatedCases[$lastFoundSicId][] = $lastFoundUserId;
			}
		} while ( $result->numRows() >= $batchSize );

		return $candidateRelatedCases;
	}

	/**
	 * @param int[] $caseIds
	 * @return array<int,list<string>> Signal names keyed by case id. Signal names in the inner array are
	 *     guaranteed to be sorted ascending
	 */
	private function getSignalsInCases( array $caseIds ): array {
		if ( !$caseIds ) {
			return [];
		}

		$dbr = $this->connectionProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );
		$signalsInCases = [];

		$batchSize = 500;
		$lastFoundSicId = 0;
		$lastFoundName = '';
		do {
			$result = $dbr->newSelectQueryBuilder()
				->select( [ 'sis_sic_id', 'sis_name' ] )
				->distinct()
				->from( 'cusi_signal' )
				->where( [
					'sis_sic_id' => $caseIds,
					$dbr->buildComparison( '>', [
						'sis_sic_id' => $lastFoundSicId,
						'sis_name' => $lastFoundName,
					] ),
				] )
				->orderBy(
					[ 'sis_sic_id', 'sis_name' ],
					SelectQueryBuilder::SORT_ASC
				)
				->limit( $batchSize )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $result as $row ) {
				$lastFoundSicId = (int)$row->sis_sic_id;
				$lastFoundName = $row->sis_name;
				$signalsInCases[$lastFoundSicId][] = $lastFoundName;
			}
		} while ( $result->numRows() >= $batchSize );

		return $signalsInCases;
	}
}
