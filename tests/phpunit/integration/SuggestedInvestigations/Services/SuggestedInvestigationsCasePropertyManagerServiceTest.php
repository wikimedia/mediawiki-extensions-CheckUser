<?php

declare( strict_types=1 );

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

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService
 * @group Database
 */
class SuggestedInvestigationsCasePropertyManagerServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testUpdateEditRelatedPropertiesForCasesOnCreateCase(): void {
		$users = [
			$this->getTestUser()->getUser(),
			$this->getTestUser( [ 'read' ] )->getUser(),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		$caseId = $this->getServiceContainer()
			->get( 'CheckUserSuggestedInvestigationsCaseManager' )
			->createCase( $users, $signals );

		$caseProperties = $this->getDb()->newSelectQueryBuilder()
			->select( [
				'sicp_sic_id',
				'sicp_property',
				'sicp_value',
			] )
			->where( [ 'sicp_sic_id' => $caseId ] )
			->from( 'cusi_case_property' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals(
			[
				(object)[
					'sicp_sic_id' => (string)$caseId,
					'sicp_property' =>
						SuggestedInvestigationsCasePropertyManagerService::PROPERTY_SHARED_PAGE_EDITS_COUNT,
					'sicp_value' => '0',
				],
			],
			iterator_to_array( $caseProperties ),
			'Case property is added to case on case creation'
		);
	}

	public function testUpdateEditRelatedPropertiesForCasesOnUpdateCase(): void {
		$service = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$user1 = $this->getTestUser()->getUser();
		$user2 = $this->getTestUser( [ 'user' ] )->getUser();

		$caseId = $service->createCase(
			[ $user1 ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Foo', 'bar', false ) ]
		);

		// Make edits with both users on a shared page before updating the case with the new user
		$this->editPage(
			'Test page',
			'Test Content 1',
			'test',
			NS_MAIN,
			$user1
		);
		$this->editPage(
			'Test page',
			'Test Content 2',
			'test',
			NS_MAIN,
			$user2
		);
		$service->updateCase( $caseId, [ $user1, $user2 ], [] );

		$caseProperties = $this->getDb()->newSelectQueryBuilder()
			->select( [
				'sicp_sic_id',
				'sicp_property',
				'sicp_value',
			] )
			->where( [ 'sicp_sic_id' => $caseId ] )
			->from( 'cusi_case_property' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals(
			[
				(object)[
					'sicp_sic_id' => (string)$caseId,
					'sicp_property' =>
						(string)SuggestedInvestigationsCasePropertyManagerService::PROPERTY_SHARED_PAGE_EDITS_COUNT,
					'sicp_value' => '2',
				],
			],
			iterator_to_array( $caseProperties ),
			'On case update, reflect updated state of shared edit case property'
		);
	}
}
