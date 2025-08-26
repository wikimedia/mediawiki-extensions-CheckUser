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

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations;

use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager;
use MediaWiki\Pager\IndexPager;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager
 * @group Database
 */
class SuggestedInvestigationsTablePagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private static User $testUser1;
	private static User $testUser2;

	public function testQuery() {
		$pager = new SuggestedInvestigationsTablePager(
			$this->getServiceContainer()->getConnectionProvider()
		);

		$results = $pager->reallyDoQuery( '', 10, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 1, $results->numRows() );

		$row = $results->fetchObject();
		$this->assertSame( '1', $row->sic_id );
		$this->assertSame( '0', $row->sic_status );
		$this->assertSame( '', $row->sic_status_reason );
		$this->assertArrayEquals(
			[ self::$testUser1->getName(), self::$testUser2->getName() ],
			$row->users,
		);
		$this->assertArrayEquals(
			[ [ 'name' => 'Lorem', 'value' => 'Test value' ] ],
			$row->signals,
		);
	}

	public function addDBDataOnce() {
		$this->enableSuggestedInvestigations();
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		self::$testUser1 = $user1 = $this->getMutableTestUser()->getUser();
		self::$testUser2 = $user2 = $this->getMutableTestUser()->getUser();

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'Test value', false );

		$caseManager->createCase( [ $user1, $user2 ], [ $signal ] );
	}
}
