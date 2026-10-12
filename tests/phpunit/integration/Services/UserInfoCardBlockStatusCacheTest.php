<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Covers the block status lookups, which resolve usernames to user IDs with a real query and so
 * cannot be unit tested. The block checkers are still mocked, so that which users count as blocked
 * stays under the test's control; what is being tested here is the batching, the cache interaction
 * and the type handling around them.
 *
 * @covers \MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache
 * @group CheckUser
 * @group Database
 */
class UserInfoCardBlockStatusCacheTest extends MediaWikiIntegrationTestCase {

	private WANObjectCache $wanCache;
	private CompositeIndefiniteBlockChecker $localBlockChecker;
	private CompositeIndefiniteBlockChecker $globalBlockChecker;
	private CompositeIndefiniteBlockChecker $lockChecker;

	/** @var int[][] Arguments of every getUserIdsNotIndefinitelyBlocked() call on the local checker */
	private array $localCalls = [];

	/** @var int[][] Arguments of every getUserIdsNotIndefinitelyBlocked() call on the global block checker */
	private array $globalCalls = [];

	/** @var int[][] Arguments of every getUserIdsNotIndefinitelyBlocked() call on the lock checker */
	private array $lockCalls = [];

	protected function setUp(): void {
		parent::setUp();

		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$this->localBlockChecker = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$this->globalBlockChecker = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$this->lockChecker = $this->createMock( CompositeIndefiniteBlockChecker::class );
		$this->localCalls = [];
		$this->globalCalls = [];
		$this->lockCalls = [];
	}

	/**
	 * Make the checkers treat the given user IDs as indefinitely blocked, recording every call so
	 * that tests can assert on how many queries the service made and with which IDs.
	 *
	 * @param int[] $locallyBlockedIds
	 * @param int[] $globallyBlockedIds
	 * @param int[] $lockedIds
	 */
	private function setBlockedIds(
		array $locallyBlockedIds,
		array $globallyBlockedIds = [],
		array $lockedIds = []
	): void {
		$this->localBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturnCallback( function ( array $ids ) use ( $locallyBlockedIds ) {
				$this->localCalls[] = $ids;
				return array_values( array_diff( $ids, $locallyBlockedIds ) );
			} );
		$this->globalBlockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturnCallback( function ( array $ids ) use ( $globallyBlockedIds ) {
				$this->globalCalls[] = $ids;
				return array_values( array_diff( $ids, $globallyBlockedIds ) );
			} );
		$this->lockChecker->method( 'getUserIdsNotIndefinitelyBlocked' )
			->willReturnCallback( function ( array $ids ) use ( $lockedIds ) {
				$this->lockCalls[] = $ids;
				return array_values( array_diff( $ids, $lockedIds ) );
			} );
	}

	private function newService(
		bool $globalBlocksEnabled = true,
		bool $locksEnabled = true
	): UserInfoCardBlockStatusCache {
		return new UserInfoCardBlockStatusCache(
			$this->wanCache,
			$this->localBlockChecker,
			$globalBlocksEnabled ? $this->globalBlockChecker : null,
			$locksEnabled ? $this->lockChecker : null,
			$this->getServiceContainer()->getUserIdentityLookup(),
			StatsFactory::newNull()
		);
	}

	private function getLocalCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-local-blocked', $username );
	}

	private function getLockCacheKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-locked', $username );
	}

	private function getGlobalCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-global-blocked', $username );
	}

	private function getGlobalCheckKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-global-blocked-check', $username );
	}

	private function assertGlobalCacheValuePurged( string $username ): void {
		$curTTL = null;
		$this->wanCache->get(
			$this->getGlobalCacheKey( $username ),
			$curTTL,
			[ $this->getGlobalCheckKey( $username ) ]
		);
		$this->assertLessThan(
			0,
			$curTTL,
			'Global block status cache value should be purged after invalidateGlobalBlock()'
		);
	}

	public function testReturnsOnlyBlockedUsers(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$unblocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );

		$this->assertSame(
			[ $blocked->getName() ],
			$this->newService()->getIndefinitelyBlockedOrLockedUsers(
				[ $unblocked->getName(), $blocked->getName() ]
			)
		);
	}

	public function testPreservesInputOrder(): void {
		$first = $this->getMutableTestUser()->getUser();
		$second = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $first->getId(), $second->getId() ] );

		$names = [ $second->getName(), $first->getName() ];
		$this->assertSame( $names, $this->newService()->getIndefinitelyBlockedOrLockedUsers( $names ) );
	}

	public function testCombinesLocalAndGlobalBlocksAndLocks(): void {
		$locallyBlocked = $this->getMutableTestUser()->getUser();
		$globallyBlocked = $this->getMutableTestUser()->getUser();
		$locked = $this->getMutableTestUser()->getUser();
		$unblocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $locallyBlocked->getId() ], [ $globallyBlocked->getId() ], [ $locked->getId() ] );

		$this->assertSame(
			[ $locallyBlocked->getName(), $globallyBlocked->getName(), $locked->getName() ],
			$this->newService()->getIndefinitelyBlockedOrLockedUsers( [
				$locallyBlocked->getName(),
				$globallyBlocked->getName(),
				$locked->getName(),
				$unblocked->getName(),
			] )
		);
	}

	public function testSkipsGlobalBlockCheckAndCacheWhenGlobalBlocksDisabled(): void {
		$globallyBlocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [], [ $globallyBlocked->getId() ] );

		$this->assertFalse(
			$this->newService( false )->isIndefinitelyBlockedOrLocked( $globallyBlocked->getName() )
		);
		$this->assertSame( [], $this->globalCalls );
		$this->assertFalse( $this->wanCache->get( $this->getGlobalCacheKey( $globallyBlocked->getName() ) ) );
		$this->assertCount( 1, $this->lockCalls );
	}

	public function testResolvesEveryUserInOneCheckerCall(): void {
		$users = [
			$this->getMutableTestUser()->getUser(),
			$this->getMutableTestUser()->getUser(),
			$this->getMutableTestUser()->getUser(),
		];
		$userIds = array_map( static fn ( $user ) => $user->getId(), $users );
		$usernames = array_map( static fn ( $user ) => $user->getName(), $users );
		$this->setBlockedIds( [] );

		$this->newService()->getIndefinitelyBlockedOrLockedUsers( $usernames );

		// The point of the batched path: one check for all users, not one per user.
		$this->assertCount( 1, $this->localCalls );
		$this->assertCount( 1, $this->globalCalls );
		$this->assertCount( 1, $this->lockCalls );
		sort( $userIds );
		$actualIds = $this->localCalls[0];
		sort( $actualIds );
		$this->assertSame( $userIds, $actualIds );
	}

	public function testSkipsGlobalLookupForLocallyBlockedUsers(): void {
		$locallyBlocked = $this->getMutableTestUser()->getUser();
		$other = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $locallyBlocked->getId() ] );

		$this->newService()->getIndefinitelyBlockedOrLockedUsers(
			[ $locallyBlocked->getName(), $other->getName() ]
		);

		// The global checkers are the expensive ones, so a local block must short-circuit them.
		$this->assertSame( [ [ $other->getId() ] ], $this->globalCalls );
		$this->assertSame( [ [ $other->getId() ] ], $this->lockCalls );
	}

	public function testStoresResultUnderTheKeysInvalidationUses(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );
		$service = $this->newService();

		$service->getIndefinitelyBlockedOrLockedUsers( [ $blocked->getName() ] );

		// The batched path has to write the same keys the single-user path and the block/unblock
		// hook handlers use, or its entries would never be invalidated.
		$this->assertSame( 1, $this->wanCache->get( $this->getLocalCacheKey( $blocked->getName() ) ) );

		$service->invalidateLocal( $blocked->getName() );
		$this->assertFalse( $this->wanCache->get( $this->getLocalCacheKey( $blocked->getName() ) ) );
	}

	public function testCachesUnblockedStatusAsZero(): void {
		$unblocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [] );

		$this->newService()->getIndefinitelyBlockedOrLockedUsers( [ $unblocked->getName() ] );

		// 0 rather than false, because WANObjectCache treats false as "do not cache".
		$this->assertSame( 0, $this->wanCache->get( $this->getLocalCacheKey( $unblocked->getName() ) ) );
		$this->assertSame( 0, $this->wanCache->get( $this->getGlobalCacheKey( $unblocked->getName() ) ) );
		$this->assertSame( 0, $this->wanCache->get( $this->getLockCacheKey( $unblocked->getName() ) ) );
	}

	public function testDoesNotRequeryOnSecondCall(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );
		$service = $this->newService();

		$this->assertSame(
			[ $blocked->getName() ],
			$service->getIndefinitelyBlockedOrLockedUsers( [ $blocked->getName() ] )
		);
		$this->assertSame(
			[ $blocked->getName() ],
			$service->getIndefinitelyBlockedOrLockedUsers( [ $blocked->getName() ] ),
		);
		$this->assertCount( 1, $this->localCalls );
	}

	public function testReadsCacheWrittenBySingleUserMethod(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );
		$service = $this->newService();

		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( $blocked->getName() ) );
		$this->assertSame(
			[ $blocked->getName() ],
			$service->getIndefinitelyBlockedOrLockedUsers( [ $blocked->getName() ] )
		);
		$this->assertCount(
			1,
			$this->localCalls,
		);
	}

	public function testSingleUserIsLocallyBlocked(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );

		$this->assertTrue( $this->newService()->isIndefinitelyBlockedOrLocked( $blocked->getName() ) );
		// A local block must short-circuit the global checkers
		$this->assertSame( [], $this->globalCalls );
		$this->assertSame( [], $this->lockCalls );
	}

	public function testSingleUserIsGloballyBlocked(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [], [ $blocked->getId() ] );

		$this->assertTrue( $this->newService()->isIndefinitelyBlockedOrLocked( $blocked->getName() ) );
	}

	public function testSingleUserIsLocked(): void {
		$locked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [], [], [ $locked->getId() ] );

		$this->assertTrue( $this->newService()->isIndefinitelyBlockedOrLocked( $locked->getName() ) );
	}

	public function testSingleUserIsNotBlocked(): void {
		$unblocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [] );

		$this->assertFalse( $this->newService()->isIndefinitelyBlockedOrLocked( $unblocked->getName() ) );
		// 0 rather than false, because WANObjectCache treats false as "do not cache".
		$this->assertSame( 0, $this->wanCache->get( $this->getLocalCacheKey( $unblocked->getName() ) ) );
		$this->assertSame( 0, $this->wanCache->get( $this->getGlobalCacheKey( $unblocked->getName() ) ) );
		$this->assertSame( 0, $this->wanCache->get( $this->getLockCacheKey( $unblocked->getName() ) ) );
	}

	public function testSingleUserDoesNotRequeryOnSecondCall(): void {
		$unblocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [] );
		$service = $this->newService();

		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( $unblocked->getName() ) );
		$this->assertFalse( $service->isIndefinitelyBlockedOrLocked( $unblocked->getName() ) );

		$this->assertCount( 1, $this->localCalls );
		$this->assertCount( 1, $this->globalCalls );
		$this->assertCount( 1, $this->lockCalls );
	}

	public function testSingleUnknownUserIsNotBlocked(): void {
		$this->setBlockedIds( [] );

		$this->assertFalse( $this->newService()->isIndefinitelyBlockedOrLocked( 'This user does not exist' ) );
		$this->assertSame( [], $this->localCalls );
		$this->assertSame( [], $this->globalCalls );
		$this->assertSame( [], $this->lockCalls );
	}

	public function testInvalidateLocalAfterSingleUserQuery(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [ $blocked->getId() ] );
		$service = $this->newService();

		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( $blocked->getName() ) );
		$this->assertSame( 1, $this->wanCache->get( $this->getLocalCacheKey( $blocked->getName() ) ) );

		$service->invalidateLocal( $blocked->getName() );
		$this->assertFalse( $this->wanCache->get( $this->getLocalCacheKey( $blocked->getName() ) ) );
	}

	public function testInvalidateLockAfterSingleUserQuery(): void {
		$locked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [], [], [ $locked->getId() ] );
		$service = $this->newService();

		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( $locked->getName() ) );
		$this->assertSame( 1, $this->wanCache->get( $this->getLockCacheKey( $locked->getName() ) ) );

		$service->invalidateLock( $locked->getName() );
		$this->assertFalse( $this->wanCache->get( $this->getLockCacheKey( $locked->getName() ) ) );
	}

	public function testInvalidateGlobalBlockAfterSingleUserQuery(): void {
		$blocked = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [], [ $blocked->getId() ] );
		$service = $this->newService();

		$this->assertTrue( $service->isIndefinitelyBlockedOrLocked( $blocked->getName() ) );
		$this->assertSame( 1, $this->wanCache->get( $this->getGlobalCacheKey( $blocked->getName() ) ) );

		$service->invalidateGlobalBlock( $blocked->getName() );
		$this->assertGlobalCacheValuePurged( $blocked->getName() );
	}

	public function testReturnsStringsForNumericUsernames(): void {
		$numericUser = ( new \TestUser( '12345' ) )->getUser();
		$this->setBlockedIds( [ $numericUser->getId() ] );

		$this->assertSame( [ '12345' ], $this->newService()->getIndefinitelyBlockedOrLockedUsers( [ '12345' ] ) );
	}

	public function testIgnoresUnknownUsers(): void {
		$this->setBlockedIds( [] );

		$this->assertSame(
			[],
			$this->newService()->getIndefinitelyBlockedOrLockedUsers( [ 'This user does not exist' ] )
		);
		$this->assertSame(
			[],
			$this->localCalls,
		);
	}

	public function testDoesNotCacheStatusOfUnknownUsers(): void {
		$known = $this->getMutableTestUser()->getUser();
		$this->setBlockedIds( [] );

		$this->newService()->getIndefinitelyBlockedOrLockedUsers( [ $known->getName(), 'This user does not exist' ] );

		// The account can be created later (for example by CentralAuth auto-creation), and the
		// lock key is shared across wikis, so the status of unknown users must not be cached.
		foreach ( [
			$this->getLocalCacheKey( 'This user does not exist' ),
			$this->getGlobalCacheKey( 'This user does not exist' ),
			$this->getLockCacheKey( 'This user does not exist' ),
		] as $key ) {
			$this->assertFalse( $this->wanCache->get( $key ), $key );
		}
		$this->assertSame( 0, $this->wanCache->get( $this->getLockCacheKey( $known->getName() ) ) );
	}

	public function testReturnsEmptyArrayForNoUsernames(): void {
		$this->setBlockedIds( [] );

		$this->assertSame( [], $this->newService()->getIndefinitelyBlockedOrLockedUsers( [] ) );
		$this->assertSame( [], $this->localCalls );
		$this->assertSame( [], $this->globalCalls );
		$this->assertSame( [], $this->lockCalls );
	}

	public function testInvalidateLocal(): void {
		$this->wanCache->set( $this->getLocalCacheKey( 'TestUser' ), 1 );
		$service = $this->newService();
		$service->invalidateLocal( 'TestUser' );

		$this->assertFalse(
			$this->wanCache->get( $this->getLocalCacheKey( 'TestUser' ) ),
			'Local WANObjectCache entry should be deleted after invalidateLocal()'
		);
	}

	public function testInvalidateLock(): void {
		$this->wanCache->set( $this->getLockCacheKey( 'TestUser' ), 1 );
		$service = $this->newService();
		$service->invalidateLock( 'TestUser' );

		$this->assertFalse(
			$this->wanCache->get( $this->getLockCacheKey( 'TestUser' ) ),
			'Lock WANObjectCache entry should be deleted after invalidateLock()'
		);
	}

	public function testInvalidateGlobalBlock(): void {
		$this->wanCache->set( $this->getGlobalCacheKey( 'TestUser' ), 1 );
		$service = $this->newService();
		$service->invalidateGlobalBlock( 'TestUser' );

		$this->assertGlobalCacheValuePurged( 'TestUser' );
	}
}
