<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\CompositeIndefiniteBlockChecker;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Centralizes cache logic for the user info card's "indefinitely blocked" status.
 *
 * Uses three cache keys per user:
 * - A local key (makeKey) for local blocks.
 * - A local key (makeKey) for global blocks. The result depends on local settings
 *   (for example locally disabled global blocks), thus the key must not be shared
 *   across wikis. A global "check" key (makeGlobalKey) lets invalidateGlobalBlock()
 *   purge this key on all wikis in the same memcached cluster.
 * - A global key (makeGlobalKey) for CentralAuth locks, shared across all wikis
 *   in the same memcached cluster.
 *
 * If the global block checker or the lock checker is null, the related check is
 * skipped and the cache is not used for it. Thus a change of
 * $wgApplyGlobalBlocks does not cause incorrect cached values.
 *
 * Hook handlers that react to block/unblock events call invalidateLocal();
 * hook handlers for global blocks call invalidateGlobalBlock();
 * hook handlers for locks call invalidateLock();
 * the render handler calls isIndefinitelyBlockedOrLocked().
 */
class UserInfoCardBlockStatusCache {

	/**
	 * Maximum number of users whose block status is resolved in a single set of queries by
	 * getIndefinitelyBlockedOrLockedUsers(), to keep the IN() lists bounded.
	 */
	private const LOOKUP_BATCH_SIZE = 500;

	private const TYPE_LOCAL_BLOCK = 'local';
	private const TYPE_GLOBAL_BLOCK = 'global_block';
	private const TYPE_LOCK = 'lock';

	public function __construct(
		private readonly WANObjectCache $wanCache,
		private readonly CompositeIndefiniteBlockChecker $localBlockChecker,
		private readonly ?CompositeIndefiniteBlockChecker $globalBlockChecker,
		private readonly ?CompositeIndefiniteBlockChecker $lockChecker,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly StatsFactory $statsFactory,
	) {
	}

	/**
	 * Invalidate the cached local blocked status for a user so it will be
	 * re-queried on next access.
	 */
	public function invalidateLocal( string $username ): void {
		$this->wanCache->delete( $this->makeLocalBlockCacheKey( $username ) );
	}

	/**
	 * Invalidate the cached global blocked status for a user so it will be
	 * re-queried on next access. Because this touches a global "check" key, the
	 * invalidation is visible to all wikis sharing the same memcached cluster.
	 */
	public function invalidateGlobalBlock( string $username ): void {
		$this->wanCache->touchCheckKey( $this->makeGlobalBlockCheckKey( $username ) );
	}

	/**
	 * Invalidate the cached locked status for a user so it will be re-queried on
	 * next access. Because this uses a global cache key, the invalidation is
	 * visible to all wikis sharing the same memcached cluster.
	 */
	public function invalidateLock( string $username ): void {
		$this->wanCache->delete( $this->makeLockCacheKey( $username ) );
	}

	/**
	 * Check whether a user is indefinitely blocked (locally or globally)
	 * or locked, using both a process-level cache and WANObjectCache
	 * with lazy population.
	 *
	 * Note: the lock cache key assumes the local user is attached to a
	 * global account. For unattached accounts, CentralAuth locks would not
	 * actually apply, but this edge case is rare on WMF wikis and is
	 * deliberately ignored.
	 */
	public function isIndefinitelyBlockedOrLocked( string $username ): bool {
		return $this->getIndefinitelyBlockedOrLockedUsers( [ $username ] ) !== [];
	}

	/**
	 * Filter a list of usernames down to those that are indefinitely blocked
	 * (locally or globally) or locked.
	 *
	 * Callers that only care about a single user can use isIndefinitelyBlockedOrLocked().
	 *
	 * @param list<string> $usernames Names of the users to check
	 * @return string[] Those of $usernames that are indefinitely blocked or locked
	 */
	public function getIndefinitelyBlockedOrLockedUsers( array $usernames ): array {
		$blocked = [];
		// Do each lookup only for users that the previous lookups did not find as blocked
		$remaining = $usernames;
		foreach ( [ self::TYPE_LOCAL_BLOCK, self::TYPE_GLOBAL_BLOCK, self::TYPE_LOCK ] as $type ) {
			if ( $remaining === [] ) {
				break;
			}
			foreach ( $this->checkCache( $remaining, $type ) as $username => $isBlocked ) {
				if ( $isBlocked ) {
					$blocked[$username] = true;
				}
			}
			$remaining = array_values( array_filter(
				$remaining,
				static fn ( string $username ) => !isset( $blocked[$username] )
			) );
		}

		return array_values( array_filter(
			$usernames,
			static fn ( string $username ) => isset( $blocked[$username] )
		) );
	}

	private function makeLocalBlockCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-local-blocked', $username );
	}

	private function makeGlobalBlockCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-global-blocked', $username );
	}

	private function makeGlobalBlockCheckKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-global-blocked-check', $username );
	}

	private function makeLockCacheKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-locked', $username );
	}

	/**
	 * Check the cache keys of the given type for several users at once, populating the ones that
	 * miss in a single batch.
	 *
	 * @param string[] $usernames
	 * @param string $type One of the TYPE_* constants
	 * @return array<int|string,bool> Map of username to blocked status. Numeric usernames appear
	 *   as integer keys, per PHP array key coercion. Empty if the check does not apply on
	 *   this wiki.
	 */
	private function checkCache( array $usernames, string $type ): array {
		$checker = match ( $type ) {
			self::TYPE_LOCAL_BLOCK => $this->localBlockChecker,
			self::TYPE_GLOBAL_BLOCK => $this->globalBlockChecker,
			self::TYPE_LOCK => $this->lockChecker,
		};
		if ( $checker === null ) {
			return [];
		}

		$keyedIds = $this->wanCache->makeMultiKeys(
			$usernames,
			fn ( string $username ) => match ( $type ) {
				self::TYPE_LOCAL_BLOCK => $this->makeLocalBlockCacheKey( $username ),
				self::TYPE_GLOBAL_BLOCK => $this->makeGlobalBlockCacheKey( $username ),
				self::TYPE_LOCK => $this->makeLockCacheKey( $username ),
			}
		);

		$checkKeys = [];
		if ( $type === self::TYPE_GLOBAL_BLOCK ) {
			foreach ( $keyedIds as $cacheKey => $username ) {
				$checkKeys[$cacheKey] = $this->makeGlobalBlockCheckKey( (string)$username );
			}
		}

		$values = $this->wanCache->getMultiWithUnionSetCallback(
			$keyedIds,
			$this->wanCache::TTL_INDEFINITE,
			function ( array $missingUsernames, array &$ttls ) use ( $checker, $type ) {
				$this->statsFactory->withComponent( 'CheckUser' )
					->getCounter( 'userinfocard_block_status_cache_miss' )
					->setLabel( 'type', $type )
					->incrementBy( count( $missingUsernames ) );
				return $this->computeIndefiniteBlockStatus( $missingUsernames, $checker, $ttls );
			},
			[
				'pcTTL' => $this->wanCache::TTL_PROC_LONG,
				'checkKeys' => $checkKeys,
			]
		);

		$status = [];
		foreach ( $keyedIds as $cacheKey => $username ) {
			$status[$username] = (bool)$values[$cacheKey];
		}
		return $status;
	}

	/**
	 * Resolve the given usernames to local user IDs in one query and determine which of them are
	 * indefinitely blocked according to the given checker.
	 *
	 * @param int[]|string[] $usernames Usernames to check. WANObjectCache round-trips these
	 *   through array keys, so numeric usernames arrive as integers.
	 * @param CompositeIndefiniteBlockChecker $checker
	 * @param array<int|string,int> &$ttls Map of the given usernames to cache TTL. Set to
	 *   TTL_UNCACHEABLE for users that are not registered on this wiki, because the result
	 *   can change when the account is created (for example by CentralAuth auto-creation).
	 * @return array<int|string,int> Map of the given usernames to 1 if blocked and 0 if not. Uses
	 *   1/0 rather than true/false because WANObjectCache treats false as "do not cache".
	 */
	private function computeIndefiniteBlockStatus(
		array $usernames,
		CompositeIndefiniteBlockChecker $checker,
		array &$ttls
	): array {
		// Assume all users are unblocked, then set to 1 if checker says so
		$result = array_fill_keys( $usernames, 0 );

		foreach ( array_chunk( $usernames, self::LOOKUP_BATCH_SIZE ) as $usernameChunk ) {
			$usersById = [];
			$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder()
				->whereUserNames( array_map( 'strval', $usernameChunk ) )
				->registered()
				->caller( __METHOD__ )
				->fetchUserIdentities();
			foreach ( $userIdentities as $user ) {
				$usersById[$user->getId()] = $user->getName();
			}
			$registeredNames = array_flip( $usersById );
			foreach ( $usernameChunk as $username ) {
				if ( !isset( $registeredNames[$username] ) ) {
					$ttls[$username] = $this->wanCache::TTL_UNCACHEABLE;
				}
			}
			if ( $usersById === [] ) {
				continue;
			}

			$unblockedIds = $checker->getUserIdsNotIndefinitelyBlocked( array_keys( $usersById ) );
			$blockedIds = array_diff( array_keys( $usersById ), $unblockedIds );
			foreach ( $blockedIds as $blockedId ) {
				$result[$usersById[$blockedId]] = 1;
			}
		}

		return $result;
	}
}
