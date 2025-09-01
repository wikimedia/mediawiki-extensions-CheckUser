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

use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService
 * @group Database
 */
class SuggestedInvestigationsCaseManagerServiceTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testCreateCase(): void {
		$users = [
			UserIdentityValue::newRegistered( 1, 'Test user 1' ),
			UserIdentityValue::newRegistered( 2, 'Test user 2' ),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		$service = $this->createService();
		$caseId = $service->createCase( $users, $signals );

		$caseIds = $this->getDb()->newSelectQueryBuilder()
			->select( 'sic_id' )
			->from( 'cusi_case' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$this->assertCount( 1, $caseIds, 'A single new case should be created' );
		$this->assertSame( $caseId, (int)$caseIds[0], 'The created case ID should be returned' );

		// Ensure we added users only to the newly created case
		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'Two users should be added to the case' );
		$this->assertSame( 0, $userCountIrrelevant, 'No users should be added to any other case' );

		// Ensure we added signals only to the newly created case
		$signalCountRelevant = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_signal' )
			->where( [ 'sis_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$signalCountAll = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_signal' )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertSame( 1, $signalCountRelevant, 'One signal should be added to the case' );
		$this->assertSame( 0, $signalCountAll - $signalCountRelevant, 'No signals should be added to any other case' );
	}

	/** @dataProvider provideDisallowCreateCase */
	public function testDisallowCreateCase( array $users, array $signals ): void {
		$service = $this->createService();
		$this->expectException( InvalidArgumentException::class );
		$service->createCase( $users, $signals );
	}

	public static function provideDisallowCreateCase(): array {
		return [
			'Disallow no users' => [
				[],
				[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ],
			],
			'Disallow no signals' => [
				[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
				[],
			],
			'Disallow multiple signals' => [
				[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
				[
					SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
					SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Dolor', 'sit amet', false ),
				],
			],
		];
	}

	public function testAddUsers(): void {
		$user1 = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$user2 = UserIdentityValue::newRegistered( 2, 'Test user 2' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 1, $userCountRelevant, 'There should be an initial user' );
		$this->assertSame( 0, $userCountIrrelevant, 'There should be no other initial user' );

		// The first is already added to this case
		$usersToAdd = [ $user1, $user2 ];

		$service = $this->createService();
		$service->addUsersToCase( $caseId, $usersToAdd );

		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'Second user should be added to the case' );
		$this->assertSame( 0, $userCountIrrelevant, 'No user should be added to any other case' );

		// Invoking the method again should not add any more users
		$service->addUsersToCase( $caseId, $usersToAdd );
		[ $userCountRelevant, $userCountIrrelevant ] = $this->countUsers( $caseId );
		$this->assertSame( 2, $userCountRelevant, 'No users should be added to the case again' );
		$this->assertSame( 0, $userCountIrrelevant, 'Again, No users should be added to any other case' );
	}

	private function countUsers( int $caseId ): array {
		$userCountRelevant = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->where( [ 'siu_sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$userCountAll = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cusi_user' )
			->caller( __METHOD__ )
			->fetchField();

		return [ $userCountRelevant, $userCountAll - $userCountRelevant ];
	}

	private function createService(): SuggestedInvestigationsCaseManagerService {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
	}
}
