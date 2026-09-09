<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * Centralizes cache logic for the user info card's "user is in an open Suggested
 * Investigations case" status.
 *
 * The trigger button is rendered once per user link, so a page can ask about hundreds of
 * users. The values are therefore cached, with a process cache in front of the shared one.
 *
 * The cached values expire on their own instead of being purged, because a case can change
 * its status through several code paths and none of them knows about the user info card.
 * The status only decides which icon a button shows, so a short period of staleness does
 * no harm.
 *
 * Callers must check that the viewer is allowed to see Suggested Investigations data
 * before they use the result.
 */
class UserInfoCardSuggestedInvestigationsCache {

	/**
	 * How long a status stays in the shared cache.
	 */
	private const TTL = 5 * WANObjectCache::TTL_MINUTE;

	/**
	 * Maximum number of users whose status is resolved in a single set of queries by
	 * computeOpenCaseStatus(), to keep the IN() lists bounded.
	 */
	private const LOOKUP_BATCH_SIZE = 500;

	public function __construct(
		private readonly WANObjectCache $wanCache,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly StatsFactory $statsFactory,
	) {
	}

	/**
	 * Check whether a user is in at least one open Suggested Investigations case.
	 */
	public function hasOpenCase( string $username ): bool {
		return $this->getUsersWithOpenCases( [ $username ] ) !== [];
	}

	/**
	 * Filter a list of usernames down to those that are in at least one open Suggested
	 * Investigations case.
	 *
	 * Callers that only care about a single user can use {@see self::hasOpenCase()}.
	 *
	 * @param string[] $usernames Names of the users to check
	 * @return list<string> Those of $usernames that are in an open case
	 */
	public function getUsersWithOpenCases( array $usernames ): array {
		if ( $usernames === [] || !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return [];
		}

		$keyedIds = $this->wanCache->makeMultiKeys(
			$usernames,
			fn ( string $username ) => $this->wanCache->makeKey(
				'checkuser-userinfocard-open-si-case',
				$username
			)
		);

		$values = $this->wanCache->getMultiWithUnionSetCallback(
			$keyedIds,
			self::TTL,
			function ( array $missingUsernames ) {
				$this->statsFactory->withComponent( 'CheckUser' )
					->getCounter( 'userinfocard_suggested_investigations_cache_miss_total' )
					->incrementBy( count( $missingUsernames ) );
				return $this->computeOpenCaseStatus( $missingUsernames );
			},
			[
				'pcTTL' => $this->wanCache::TTL_PROC_LONG,
			]
		);

		$usersWithOpenCases = [];
		foreach ( $keyedIds as $cacheKey => $username ) {
			if ( $values[$cacheKey] ) {
				$usersWithOpenCases[] = (string)$username;
			}
		}
		return $usersWithOpenCases;
	}

	/**
	 * Resolve the given usernames to local user IDs in one query and determine which of them
	 * are in at least one open case.
	 *
	 * @param int[]|string[] $usernames Usernames to check. WANObjectCache round-trips these
	 *   through array keys, so numeric usernames arrive as integers.
	 * @return array<int|string,int> Map of the given usernames to 1 if the user is in an open
	 *   case and 0 if not. Uses 1/0 rather than true/false because WANObjectCache treats false
	 *   as "do not cache".
	 */
	private function computeOpenCaseStatus( array $usernames ): array {
		// Assume no user is in a case, then set to 1 for those who are
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

			$userIdsWithOpenCases = $this->caseLookupService->getUserIdsWithCases(
				array_keys( $usersById ),
				[ CaseStatus::Open ]
			);
			foreach ( $userIdsWithOpenCases as $userId ) {
				$result[$usersById[$userId]] = 1;
			}
		}

		return $result;
	}
}
