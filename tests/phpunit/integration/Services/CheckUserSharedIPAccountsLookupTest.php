<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Extension\CheckUser\Services\CheckUserSharedIPAccountsLookup;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserSharedIPAccountsLookup
 */
class CheckUserSharedIPAccountsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;
	use SharedAccountsLookupTestTrait;

	private function getObjectUnderTest(): CheckUserSharedIPAccountsLookup {
		return $this->getServiceContainer()->get( 'CheckUserSharedIPAccountsLookup' );
	}

	private function assertLookupResult( array $expectedUserIds, bool $expectedComplete, array $actual ): void {
		$this->assertContainsOnlyInstancesOf( UserIdentity::class, $actual['users'] );
		$actualUserIds = array_map(
			static fn ( UserIdentity $user ) => $user->getId(),
			$actual['users']
		);
		sort( $expectedUserIds );
		sort( $actualUserIds );
		$this->assertSame( $expectedUserIds, $actualUserIds );
		$this->assertSame( $expectedComplete, $actual['complete'] );
	}

	public function testNoCheckUserDataForTarget() {
		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $this->getTestUser()->getUser() )
		);
	}

	public function testNamedAndTemporaryAccountsOnTheSameIP() {
		$this->enableAutoCreateTempUser();

		$target = $this->getTestUser()->getUser();
		$other = $this->getTestUser( [ 'sysop' ] )->getUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( '~check-user-test-01', new FauxRequest() )->getUser();

		foreach ( [ $target, $other, $tempUser ] as $user ) {
			$this->insertCuChangesRow( $this->acquireActorId( $user ), '1.2.3.4' );
		}
		// An anonymous actor on the same IP must not be reported. ActorStore refuses to create
		// an actor for an IP address while temporary accounts are on, so insert the row.
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'actor' )
			->row( [ 'actor_user' => null, 'actor_name' => '1.2.3.4' ] )
			->caller( __METHOD__ )
			->execute();
		$this->insertCuChangesRow( $this->getDb()->insertId(), '1.2.3.4' );
		// An account on a different IP must not be reported.
		$this->insertCuChangesRow(
			$this->acquireActorId( $this->getTestUser( [ 'bureaucrat' ] )->getUser() ),
			'5.6.7.8'
		);

		$this->assertLookupResult(
			[ $other->getId(), $tempUser->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testMatchesInEachResultTableAreMerged() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();
		$targetActorId = $this->acquireActorId( $target );
		$otherActorId = $this->acquireActorId( $other );

		$this->insertCuChangesRow( $targetActorId, '1.2.3.4' );
		$this->insertCuChangesRow( $otherActorId, '1.2.3.4' );
		$this->insertCuLogEventRow( $otherActorId, '1.2.3.4' );
		$this->insertCuPrivateEventRow( $otherActorId, '1.2.3.4' );
		// A private event of a logged out user has no actor.
		$this->insertCuPrivateEventRow( null, '1.2.3.4' );

		$this->assertLookupResult(
			[ $other->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testTargetIPsComeFromEachResultTable() {
		$target = $this->getTestUser()->getUser();
		$targetActorId = $this->acquireActorId( $target );
		$logEventIPUser = $this->getTestSysop()->getUser();
		$privateEventIPUser = $this->getTestUser( [ 'bureaucrat' ] )->getUser();

		// The target has no cu_changes rows.
		$this->insertCuLogEventRow( $targetActorId, '1.2.3.4' );
		$this->insertCuPrivateEventRow( $targetActorId, '5.6.7.8' );
		$this->insertCuChangesRow( $this->acquireActorId( $logEventIPUser ), '1.2.3.4' );
		$this->insertCuChangesRow( $this->acquireActorId( $privateEventIPUser ), '5.6.7.8' );

		$this->assertLookupResult(
			[ $logEventIPUser->getId(), $privateEventIPUser->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testRowWithoutIPAddressIsIgnored() {
		$target = $this->getTestUser()->getUser();
		$other = $this->getTestSysop()->getUser();

		$this->insertCuChangesRow( $this->acquireActorId( $target ), null );
		$this->insertCuChangesRow( $this->acquireActorId( $other ), null );

		$this->assertLookupResult(
			[],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testIPv6AddressesMatchInTheSame64Range() {
		$target = $this->getTestUser()->getUser();
		$sameRange = $this->getTestSysop()->getUser();
		$otherRange = $this->getTestUser( [ 'bureaucrat' ] )->getUser();

		$addresses = [
			[ $target, '2001:db8:0:1:0:0:0:1' ],
			[ $sameRange, '2001:db8:0:1:ffff:ffff:ffff:ffff' ],
			[ $otherRange, '2001:db8:0:2:0:0:0:1' ],
		];
		foreach ( $addresses as [ $user, $ip ] ) {
			$this->insertCuChangesRow( $this->acquireActorId( $user ), $ip );
		}

		$this->assertLookupResult(
			[ $sameRange->getId() ],
			true,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testOnlyTheMostRecentTargetIPsAreUsed() {
		$target = $this->getTestUser()->getUser();
		$targetActorId = $this->acquireActorId( $target );
		$oldestIPUser = $this->getTestSysop()->getUser();
		$newestIPUser = $this->getTestUser( [ 'bureaucrat' ] )->getUser();

		// One more IP address than the service looks at. 10.0.0.0 is the oldest.
		for ( $i = 0; $i <= 20; $i++ ) {
			$this->insertCuChangesRow( $targetActorId, "10.0.0.$i", $i + 1 );
		}
		$this->insertCuChangesRow( $this->acquireActorId( $oldestIPUser ), '10.0.0.0' );
		$this->insertCuChangesRow( $this->acquireActorId( $newestIPUser ), '10.0.0.20' );

		$this->assertLookupResult(
			[ $newestIPUser->getId() ],
			false,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}

	public function testIPWithTooManyRowsIsMarkedIncomplete() {
		$target = $this->getTestUser()->getUser();
		$other1 = $this->getTestSysop()->getUser();
		$other2 = $this->getTestUser( [ 'bureaucrat' ] )->getUser();

		// Other1 is buried below the Other2's edits, so it shouldn't be returned by the service
		$this->insertCuChangesRow( $this->acquireActorId( $other1 ), '1.2.3.4', 1 );
		$other2Id = $this->acquireActorId( $other2 );
		for ( $i = 1; $i <= 101; $i++ ) {
			$this->insertCuChangesRow( $other2Id, '1.2.3.4', $i + 1 );
		}
		$this->insertCuChangesRow( $this->acquireActorId( $target ), '1.2.3.4', 1000 );

		$this->assertLookupResult(
			[ $other2->getId() ],
			false,
			$this->getObjectUnderTest()->getUsersOnSharedIPs( $target )
		);
	}
}
