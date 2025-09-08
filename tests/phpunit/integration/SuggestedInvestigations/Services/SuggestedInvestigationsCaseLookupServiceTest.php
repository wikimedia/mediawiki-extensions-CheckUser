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

use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService
 * @group Database
 */
class SuggestedInvestigationsCaseLookupServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private static int $openCase;
	private static int $closedCase;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testGetCasesWhenSuggestedInvestigationsDisabled() {
		$this->disableSuggestedInvestigations();
		$service = $this->createService();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Suggested Investigations is not enabled' );
		$service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false )
		);
	}

	public function testLookupForOpenCase() {
		$service = $this->createService();

		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false )
		);

		$this->assertCount( 1, $cases );
		$this->assertSame( self::$openCase, $cases[0] );
	}

	/** @dataProvider provideLookupForClosedCase */
	public function testLookupForClosedCase( $onlyOpen ) {
		$service = $this->createService();

		$cases = $service->getCasesForSignal(
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
			$onlyOpen
		);

		if ( $onlyOpen ) {
			$this->assertCount( 0, $cases );
		} else {
			$this->assertCount( 1, $cases );
			$this->assertSame( self::$closedCase, $cases[0] );
		}
	}

	public function provideLookupForClosedCase() {
		return [
			'Looks up only for open cases' => [ 'onlyOpen' => true ],
			'Looks up for all cases' => [ 'onlyOpen' => false ],
		];
	}

	public function addDBDataOnce() {
		$this->enableSuggestedInvestigations();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$user1 = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$user2 = UserIdentityValue::newRegistered( 2, 'Test user 2' );

		self::$openCase = $caseManager->createCase(
			[ $user1, $user2 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
			]
		);

		self::$closedCase = $caseManager->createCase(
			[ $user1 ],
			[
				SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
			]
		);
		$caseManager->setCaseStatus( self::$closedCase, CaseStatus::Resolved );
	}

	private function createService(): SuggestedInvestigationsCaseLookupService {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' );
	}
}
