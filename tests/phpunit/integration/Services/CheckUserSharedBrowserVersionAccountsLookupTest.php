<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Services\CheckUserSharedBrowserVersionAccountsLookup;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserSharedBrowserVersionAccountsLookup
 */
class CheckUserSharedBrowserVersionAccountsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserClientHintsCommonTestTrait;
	use SharedAccountsLookupTestTrait;

	private function getObjectUnderTest(): CheckUserSharedBrowserVersionAccountsLookup {
		return $this->getServiceContainer()->get( 'CheckUserSharedBrowserVersionAccountsLookup' );
	}

	private function assertLookupResult(
		array $expectedUserIds,
		bool $expectedComplete,
		array $actual,
		string $message = ''
	): void {
		$this->assertContainsOnlyInstancesOf( UserIdentity::class, $actual['users'] );
		$actualUserIds = array_map(
			static fn ( UserIdentity $user ) => $user->getId(),
			$actual['users']
		);
		sort( $expectedUserIds );
		sort( $actualUserIds );
		$this->assertSame( $expectedUserIds, $actualUserIds, $message );
		$this->assertSame( $expectedComplete, $actual['complete'], $message );
	}

	/**
	 * Create an actor row for an account that does not exist, which is enough for this service.
	 *
	 * @param int $index A number that makes the account unique
	 * @return int[] The actor ID and the user ID
	 */
	private function newFakeAccountActor( int $index ): array {
		$userId = 100000 + $index;
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'actor' )
			->row( [ 'actor_user' => $userId, 'actor_name' => "FakeAccount$index" ] )
			->caller( __METHOD__ )
			->execute();
		return [ $this->getDb()->insertId(), $userId ];
	}

	private function insertClientHints( ClientHintsData $data, int $referenceId, string $type ): void {
		/** @var UserAgentClientHintsManager $manager */
		$manager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$this->assertStatusGood( $manager->insertClientHintValues( $data, $referenceId, $type ) );
	}

	/** Build Client Hints data that differs from other data only in the browser version. */
	private function newClientHintsDataForVersion( string $version ): ClientHintsData {
		return self::getExampleClientHintsDataObjectFromJsApi(
			'x86',
			'64',
			null,
			[
				[ 'brand' => 'Not.A/Brand', 'version' => '8.0.0.0' ],
				[ 'brand' => 'Testing Browser', 'version' => $version ],
			]
		);
	}

	private function insertFullVersionListValue( string $value ): int {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent_clienthints' )
			->row( [ 'uach_name' => 'fullVersionList', 'uach_value' => $value ] )
			->caller( __METHOD__ )
			->execute();
		return $this->getDb()->insertId();
	}

	private function mapClientHintValue( int $uachId, int $referenceType, int $referenceId ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent_clienthints_map' )
			->row( [
				'uachm_uach_id' => $uachId,
				'uachm_reference_type' => $referenceType,
				'uachm_reference_id' => $referenceId,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Add map rows that point at no event, so that a value has more map rows without more
	 * accounts.
	 */
	private function padMapRows( int $uachId, int $count, int $firstReferenceId ): void {
		$rows = [];
		for ( $referenceId = $firstReferenceId; $referenceId < $firstReferenceId + $count; $referenceId++ ) {
			$rows[] = [
				'uachm_uach_id' => $uachId,
				'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
				'uachm_reference_id' => $referenceId,
			];
		}
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent_clienthints_map' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	public function testNoCheckUserDataForTarget() {
		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()
				->getUsersWithSharedBrowserVersion( $this->getTestUser()->getUser() )
		);
	}

	public function testSharedBrowserVersionViaRevision() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();
		$clientHints = $this->newClientHintsDataForVersion( '1.2.3.4' );

		$targetRevisionId = $this->editPage( 'Target page', 'Text', '', NS_MAIN, $target )
			->getNewRevision()->getId();
		$otherRevisionId = $this->editPage( 'Other page', 'Text', '', NS_MAIN, $other )
			->getNewRevision()->getId();

		// Only the target needs a CheckUser row, because the service finds the other account
		// through the revision table.
		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4', 0, $targetRevisionId );
		$this->insertClientHints( $clientHints, $targetRevisionId, 'revision' );
		$this->insertClientHints( $clientHints, $otherRevisionId, 'revision' );

		$this->assertLookupResult(
			[ $other->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testDifferentBrowserVersionDoesNotMatch() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();

		$targetRevisionId = $this->editPage( 'Target page', 'Text', '', NS_MAIN, $target )
			->getNewRevision()->getId();
		$otherRevisionId = $this->editPage( 'Other page', 'Text', '', NS_MAIN, $other )
			->getNewRevision()->getId();

		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4', 0, $targetRevisionId );
		$this->insertClientHints(
			$this->newClientHintsDataForVersion( '1.2.3.4' ),
			$targetRevisionId,
			'revision'
		);
		// The other account has no fullVersionList value in common with the target.
		$this->insertClientHints(
			self::getExampleClientHintsDataObjectFromJsApi(
				'x86',
				'64',
				null,
				[
					[ 'brand' => 'Other/Brand', 'version' => '9.0.0.0' ],
					[ 'brand' => 'Testing Browser', 'version' => '5.6.7.8' ],
				]
			),
			$otherRevisionId,
			'revision'
		);

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testSharedGreaseBrandDoesNotMatch() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();

		$targetRevisionId = $this->editPage( 'Target page', 'Text', '', NS_MAIN, $target )
			->getNewRevision()->getId();
		$otherRevisionId = $this->editPage( 'Other page', 'Text', '', NS_MAIN, $other )
			->getNewRevision()->getId();

		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4', 0, $targetRevisionId );
		// Both accounts use the GREASE brand "Not.A/Brand 8.0.0.0", but different browsers.
		$this->insertClientHints(
			$this->newClientHintsDataForVersion( '1.2.3.4' ),
			$targetRevisionId,
			'revision'
		);
		$this->insertClientHints(
			$this->newClientHintsDataForVersion( '5.6.7.8' ),
			$otherRevisionId,
			'revision'
		);

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testSharedBrowserVersionViaLogEntryAndPrivateEvent() {
		$target = $this->getTestUser()->getUser();
		$logPerformer = $this->getTestSysop()->getUser();
		$privateEventPerformer = $this->getTestUser( [ 'bureaucrat' ] )->getUser();

		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );

		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $logPerformer );
		$logEntry->setTarget( $this->getExistingTestPage()->getTitle() );
		$logEntry->setComment( 'A very good reason' );
		$logId = $logEntry->insert();

		$otherEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $privateEventPerformer ), '5.6.7.8' );

		$uachId = $this->insertFullVersionListValue( 'Testing Browser 1.2.3.4' );
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
			$targetEventId
		);
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
			$logId
		);
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
			$otherEventId
		);

		$this->assertLookupResult(
			[ $logPerformer->getId(), $privateEventPerformer->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testDeletedRevisionIsStillFound() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();

		$targetRevisionId = $this->editPage( 'Target page', 'Text', '', NS_MAIN, $target )
			->getNewRevision()->getId();
		$otherRevisionId = $this->editPage( 'Doomed page', 'Text', '', NS_MAIN, $other )
			->getNewRevision()->getId();

		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4', 0, $targetRevisionId );
		$uachId = $this->insertFullVersionListValue( 'Testing Browser 1.2.3.4' );
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			$targetRevisionId
		);
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			$otherRevisionId
		);

		$this->deletePage( 'Doomed page', 'Test deletion' );

		$this->assertLookupResult(
			[ $other->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target ),
			'A revision of a deleted page is in the archive table and must still count'
		);
	}

	public function testOversizedClusterIsIgnoredButRareValueIsKept() {
		$target = $this->getTestUser()->getUser();
		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );

		$commonValueId = $this->insertFullVersionListValue( 'Common Browser 1.0.0.0' );
		$rareValueId = $this->insertFullVersionListValue( 'Rare Browser 1.0.0.0' );
		$privateEventType = UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;
		$this->mapClientHintValue( $commonValueId, $privateEventType, $targetEventId );
		$this->mapClientHintValue( $rareValueId, $privateEventType, $targetEventId );

		// One more account than the service accepts for a single browser version.
		for ( $i = 0; $i < 51; $i++ ) {
			[ $actorId ] = $this->newFakeAccountActor( $i );
			$eventId = $this->insertCuPrivateEventRow( $actorId, '5.6.7.8' );
			$this->mapClientHintValue( $commonValueId, $privateEventType, $eventId );
		}

		$rareUser = $this->getTestSysop()->getUser();
		$rareUserEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $rareUser ), '9.10.11.12' );
		$this->mapClientHintValue( $rareValueId, $privateEventType, $rareUserEventId );

		$this->assertLookupResult(
			[ $rareUser->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target ),
			'The oversized cluster adds nothing and does not make the result incomplete'
		);
	}

	public function testValueWithTooManyEventsIsIgnoredWithoutALookup() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();
		$privateEventType = UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;

		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );
		$otherEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $other ), '5.6.7.8' );

		$uachId = $this->insertFullVersionListValue( 'Busy Browser 1.0.0.0' );
		$this->mapClientHintValue( $uachId, $privateEventType, $targetEventId );
		$this->mapClientHintValue( $uachId, $privateEventType, $otherEventId );
		// The service rejects the value on the row count alone, before it resolves any account.
		$this->padMapRows( $uachId, 1001, 1000000 );

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testClusterThatDoesNotFitTheRowBudgetIsSkipped() {
		$target = $this->getTestUser()->getUser();
		$inLastFittingCluster = $this->getTestSysop()->getUser();
		$inSkippedCluster = $this->getTestUser( [ 'bureaucrat' ] )->getUser();
		$privateEventType = UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;

		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );

		// The target uses each value once, so the service reads them in the order of their IDs.
		// The first three values use the full row budget of 3000 map rows.
		$valueIds = [];
		for ( $i = 0; $i < 4; $i++ ) {
			$valueIds[$i] = $this->insertFullVersionListValue( "Testing Browser $i.0.0.0" );
			$this->mapClientHintValue( $valueIds[$i], $privateEventType, $targetEventId );
		}
		$this->padMapRows( $valueIds[0], 999, 1000000 );
		$this->padMapRows( $valueIds[1], 999, 2000000 );
		$this->padMapRows( $valueIds[2], 998, 3000000 );

		$lastFittingEventId = $this->insertCuPrivateEventRow(
			$this->acquireActorId( $inLastFittingCluster ),
			'5.6.7.8'
		);
		$this->mapClientHintValue( $valueIds[2], $privateEventType, $lastFittingEventId );

		$skippedEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $inSkippedCluster ), '9.10.11.12' );
		$this->mapClientHintValue( $valueIds[3], $privateEventType, $skippedEventId );

		$this->assertLookupResult(
			[ $inLastFittingCluster->getId() ],
			false,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target ),
			'A cluster that uses the last rows of the budget counts, the next one does not'
		);
	}

	public function testTooManyBrowserValuesMakeTheResultIncomplete() {
		$target = $this->getTestUser()->getUser();
		$privateEventType = UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;

		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );
		// One more value than the service looks at.
		for ( $i = 0; $i < 21; $i++ ) {
			$uachId = $this->insertFullVersionListValue( "Testing Browser $i.0.0.0" );
			$this->mapClientHintValue( $uachId, $privateEventType, $targetEventId );
		}

		$this->assertLookupResult(
			[],
			false,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testHintThatIsNotAFullVersionListDoesNotMatch() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();
		$privateEventType = UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;

		$targetEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $target ), '1.2.3.4' );
		$otherEventId = $this->insertCuPrivateEventRow( $this->acquireActorId( $other ), '5.6.7.8' );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent_clienthints' )
			->row( [ 'uach_name' => 'platform', 'uach_value' => 'Windows' ] )
			->caller( __METHOD__ )
			->execute();
		$uachId = $this->getDb()->insertId();
		$this->mapClientHintValue( $uachId, $privateEventType, $targetEventId );
		$this->mapClientHintValue( $uachId, $privateEventType, $otherEventId );

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}

	public function testReferenceIdOfZeroIsNotUsed() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();

		// The target has an event with no related revision, so cuc_this_oldid is 0.
		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4' );
		$otherRevisionId = $this->editPage( 'Other page', 'Text', '', NS_MAIN, $other )
			->getNewRevision()->getId();

		$uachId = $this->insertFullVersionListValue( 'Testing Browser 1.2.3.4' );
		$this->mapClientHintValue( $uachId, UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES, 0 );
		$this->mapClientHintValue(
			$uachId,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			$otherRevisionId
		);

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersWithSharedBrowserVersion( $target )
		);
	}
}
