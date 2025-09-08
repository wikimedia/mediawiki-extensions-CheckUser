<?php
/*
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
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Config\ServiceOptions;
use RuntimeException;
use Wikimedia\Rdbms\IConnectionProvider;

class SuggestedInvestigationsCaseLookupService {

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
	 * Looks up cases that match a given signal. Ignores the allowMerging flag on the signal.
	 * @throws InvalidArgumentException if a negative signal match is provided
	 * @param SuggestedInvestigationsSignalMatchResult $signal
	 * @param bool $onlyOpen If true, only return cases that are open.
	 * @return int[]
	 */
	public function getCasesForSignal(
		SuggestedInvestigationsSignalMatchResult $signal,
		bool $onlyOpen = true
	): array {
		if ( !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			throw new RuntimeException( 'Suggested Investigations is not enabled' );
		}

		if ( !$signal->isMatch() ) {
			throw new InvalidArgumentException( 'Cannot look up for a negative signal match' );
		}

		$dbr = $this->dbProvider->getReplicaDatabase();

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( 'sis_sic_id' )
			->from( 'cusi_signal' )
			->where( [
				'sis_name' => $signal->getName(),
				'sis_value' => $signal->getValue(),
			] )
			->caller( __METHOD__ );

		if ( $onlyOpen ) {
			$queryBuilder->join( 'cusi_case', null, 'sis_sic_id = sic_id' )
				->where( [ 'sic_status' => CaseStatus::Open->value ] );
		}

		return array_map( 'intval', $queryBuilder->fetchFieldValues() );
	}
}
