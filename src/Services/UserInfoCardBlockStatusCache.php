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
 * Uses two cache keys per user:
 * - A local key (makeKey) for local blocks, scoped to the current wiki.
 * - A global key (makeGlobalKey) for global blocks and CentralAuth locks,
 *   shared across all wikis in the same memcached cluster.
 *
 * Hook handlers that react to block/unblock events call invalidateLocal();
 * hook handlers for global blocks/locks call invalidateGlobal();
 * the render handler calls isIndefinitelyBlockedOrLocked().
 */
class UserInfoCardBlockStatusCache {

	/**
	 * Maximum number of users whose block status is resolved in a single set of queries by
	 * getIndefinitelyBlockedOrLockedUsers(), to keep the IN() lists bounded.
	 */
	private const LOOKUP_BATCH_SIZE = 500;

	public function __construct(
		private readonly WANObjectCache $wanCache,
		private readonly CompositeIndefiniteBlockChecker $localBlockChecker,
		private readonly CompositeIndefiniteBlockChecker $globalBlockChecker,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly StatsFactory $statsFactory,
	) {
	}

	/**
	 * Invalidate the cached local blocked status for a user so it will be
	 * re-queried on next access.
	 */
	public function invalidateLocal( string $username ): void {
		$this->wanCache->delete( $this->makeLocalCacheKey( $username ) );
	}

	/**
	 * Invalidate the cached global blocked/locked status for a user so it will be
	 * re-queried on next access. Because this uses a global cache key, the
	 * invalidation is visible to all wikis sharing the same memcached cluster.
	 */
	public function invalidateGlobal( string $username ): void {
		$this->wanCache->delete( $this->makeGlobalCacheKey( $username ) );
	}

	/**
	 * Check whether a user is indefinitely blocked (locally or globally)
	 * or locked, using both a process-level cache and WANObjectCache
	 * with lazy population.
	 *
	 * Note: the global cache key assumes the local user is attached to a
	 * global account. For unattached accounts, global blocks and CentralAuth
	 * locks would not actually apply, but this edge case is rare on WMF
	 * wikis and is deliberately ignored.
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
		if ( $usernames === [] ) {
			return [];
		}

		$localStatus = $this->checkCache( $usernames, true );
		// Do the global lookup only for users unblocked locally
		$globalStatus = $this->checkCache(
			array_values( array_filter(
				$usernames,
				static fn ( string $username ) => !$localStatus[$username]
			) ),
			false
		);

		return array_values( array_filter(
			$usernames,
			static fn ( string $username ) => $localStatus[$username] || ( $globalStatus[$username] ?? false )
		) );
	}

	private function makeLocalCacheKey( string $username ): string {
		return $this->wanCache->makeKey( 'checkuser-userinfocard-local-blocked', $username );
	}

	private function makeGlobalCacheKey( string $username ): string {
		return $this->wanCache->makeGlobalKey( 'checkuser-userinfocard-global-blocked', $username );
	}

	/**
	 * Check the cache keys of the given type for several users at once, populating the ones that
	 * miss in a single batch.
	 *
	 * @param string[] $usernames
	 * @param bool $isLocal Whether checking for local or global blocks
	 * @return array<int|string,bool> Map of username to blocked status. Numeric usernames appear
	 *   as integer keys, per PHP array key coercion.
	 */
	private function checkCache( array $usernames, bool $isLocal ): array {
		if ( $usernames === [] ) {
			return [];
		}

		$checker = $isLocal ? $this->localBlockChecker : $this->globalBlockChecker;
		$keyedIds = $this->wanCache->makeMultiKeys(
			$usernames,
			fn ( string $username ) => $isLocal
				? $this->makeLocalCacheKey( $username )
				: $this->makeGlobalCacheKey( $username )
		);

		$values = $this->wanCache->getMultiWithUnionSetCallback(
			$keyedIds,
			$this->wanCache::TTL_INDEFINITE,
			function ( array $missingUsernames ) use ( $checker, $isLocal ) {
				$this->statsFactory->withComponent( 'CheckUser' )
					->getCounter( 'userinfocard_block_status_cache_miss' )
					->setLabel( 'type', $isLocal ? 'local' : 'global' )
					->incrementBy( count( $missingUsernames ) );
				return $this->computeIndefiniteBlockStatus( $missingUsernames, $checker );
			},
			[
				'pcTTL' => $this->wanCache::TTL_PROC_LONG,
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
	 * @return array<int|string,int> Map of the given usernames to 1 if blocked and 0 if not. Uses
	 *   1/0 rather than true/false because WANObjectCache treats false as "do not cache".
	 */
	private function computeIndefiniteBlockStatus(
		array $usernames,
		CompositeIndefiniteBlockChecker $checker
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
