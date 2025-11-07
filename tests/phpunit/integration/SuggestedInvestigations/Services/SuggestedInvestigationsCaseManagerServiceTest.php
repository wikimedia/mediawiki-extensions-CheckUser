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

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

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

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$method = __METHOD__;
		$client->expects( $this->once() )
			->method( 'submitInteraction' )
			->willReturnCallback( function ( $context, $action, $interactionData ) use ( $method ) {
				$this->assertSame( RequestContext::getMain(), $context );
				$this->assertSame( 'case_open', $action );

				// We have to compare against the case ID from the database and not what is returned by ::createCase,
				// because ::createCase does not return until after this callback is run
				$caseIdFromDatabase = $this->getDb()->newSelectQueryBuilder()
					->select( 'sic_id' )
					->from( 'cusi_case' )
					->caller( $method )
					->fetchField();
				$this->assertSame(
					[
						'action_context' => json_encode( [
							'i' => (int)$caseIdFromDatabase, 's' => [ 'Lorem' ], 'u' => 2,
						] ),
					],
					$interactionData
				);
			} );

		// Mock SuggestedInvestigationsCaseManagerService::generateUrlIdentifier to return a predetermined value
		// so that we can assert against the value of sic_url_identifier in the new row
		$service = $this->getMockBuilder( SuggestedInvestigationsCaseManagerService::class )
			->setConstructorArgs( [
				new ServiceOptions(
					SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
					$this->getServiceContainer()->getMainConfig()
				),
				$this->getServiceContainer()->getConnectionProvider(),
				$client,
			] )
			->onlyMethods( [ 'generateUrlIdentifier' ] )
			->getMock();
		$service->method( 'generateUrlIdentifier' )
			->willReturn( 123 );

		$caseId = $service->createCase( $users, $signals );

		$caseRows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_url_identifier' ] )
			->from( 'cusi_case' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertCount( 1, $caseRows, 'A single new case should be created' );

		$matchingCaseRow = $caseRows->fetchRow();
		$this->assertSame( $caseId, (int)$matchingCaseRow['sic_id'], 'The created case ID should be returned' );
		$this->assertSame(
			123,
			(int)$matchingCaseRow['sic_url_identifier'],
			'The created case should use the mocked random URL identifier'
		);

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

	public function testCreateCaseOnUrlIdentifierConflict(): void {
		$users = [
			UserIdentityValue::newRegistered( 1, 'Test user 1' ),
			UserIdentityValue::newRegistered( 2, 'Test user 2' ),
		];
		$signals = [
			SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ),
		];

		// Mock SuggestedInvestigationsCaseManagerService::generateUrlIdentifier to return
		// the same ID in a row, and then choose a new ID.
		// This tests that the creation of a new case validates that a new URL identifier value is not
		// used in any existing cusi_case row.
		$service = $this->getMockBuilder( SuggestedInvestigationsCaseManagerService::class )
			->setConstructorArgs( [
				new ServiceOptions(
					SuggestedInvestigationsCaseManagerService::CONSTRUCTOR_OPTIONS,
					$this->getServiceContainer()->getMainConfig()
				),
				$this->getServiceContainer()->getConnectionProvider(),
				$this->createMock( SuggestedInvestigationsInstrumentationClient::class ),
			] )
			->onlyMethods( [ 'generateUrlIdentifier' ] )
			->getMock();
		$service->expects( $this->exactly( 3 ) )
			->method( 'generateUrlIdentifier' )
			->willReturnOnConsecutiveCalls( 123, 123, 1234 );

		$firstCaseId = $service->createCase( $users, $signals );
		$secondCaseId = $service->createCase( $users, $signals );

		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '123' );
		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $secondCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1234' );
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

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$client->expects( $this->exactly( 2 ) )
			->method( 'submitInteraction' )
			->with(
				RequestContext::getMain(),
				'case_updated',
				[
					'action_context' => json_encode( [
						'i' => $caseId, 's' => [ 'Lorem' ], 'u' => 2,
					] ),
				]
			);
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

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

	/**
	 * @dataProvider setCaseStatusDataProvider
	 */
	public function testSetCaseStatus(
		CaseStatus $oldStatus, CaseStatus $newStatus, string $reason,
		bool $shouldCreateInstrumentationEvent, bool $expectedHasNote,
		string $expectedActionSubtype
	): void {
		$user1 = UserIdentityValue::newRegistered( 1, 'Test user 1' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$service = $this->createService();
		$caseId = $service->createCase( [ $user1 ], [ $signal ] );
		$service->setCaseStatus( $caseId, $oldStatus );

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		if ( $shouldCreateInstrumentationEvent ) {
			$client->expects( $this->once() )
				->method( 'submitInteraction' )
				->with(
					RequestContext::getMain(),
					'case_status_change',
					[
						'action_context' => json_encode( [
							'i' => $caseId, 's' => [ 'Lorem' ], 'u' => 1,
							'n' => (int)$expectedHasNote,
						] ),
						'action_subtype' => $expectedActionSubtype,
					]
				);
		} else {
			$client->expects( $this->never() )
				->method( 'submitInteraction' );
		}
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$service = $this->createService();
		$service->setCaseStatus( $caseId, $newStatus, $reason );

		// Assert the new state has been persisted to the DB
		$this->assertEquals( $newStatus, $this->getCaseStatus( $caseId ) );
	}

	public static function setCaseStatusDataProvider(): array {
		return [
			'From Resolved to Open' => [
				'oldStatus' => CaseStatus::Resolved,
				'newStatus' => CaseStatus::Open,
				'reason' => '  ',
				'shouldCreateInstrumentationEvent' => true,
				'expectedHasNote' => false,
				'expectedActionSubtype' => 'open',
			],
			'From Open to Resolved' => [
				'oldStatus' => CaseStatus::Open,
				'newStatus' => CaseStatus::Resolved,
				'reason' => 'case closed',
				'shouldCreateInstrumentationEvent' => true,
				'expectedHasNote' => true,
				'expectedActionSubtype' => 'resolved',
			],
			'From Open to Invalid' => [
				'oldStatus' => CaseStatus::Open,
				'newStatus' => CaseStatus::Invalid,
				'reason' => '',
				'shouldCreateInstrumentationEvent' => true,
				'expectedHasNote' => false,
				'expectedActionSubtype' => 'invalid',
			],
			'From Resolved to Resolved' => [
				'oldStatus' => CaseStatus::Resolved,
				'newStatus' => CaseStatus::Resolved,
				'reason' => 'case closed',
				'shouldCreateInstrumentationEvent' => false,
				'expectedHasNote' => false,
				'expectedActionSubtype' => '',
			],
		];
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

	public function getCaseStatus( int $caseId ): CaseStatus {
		$rawStatus = $this->getDb()->newSelectQueryBuilder()
			->select( 'sic_status' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();

		return CaseStatus::from( $rawStatus );
	}

	private function createService(): SuggestedInvestigationsCaseManagerService {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
	}
}
