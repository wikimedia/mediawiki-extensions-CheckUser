<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\CentralAuth\User\GlobalUserSelectQueryBuilderFactory;
use MediaWiki\User\UserIdentityLookup;

class CentralAuthLockCheck implements IndefiniteBlockCheckInterface {

	public function __construct(
		private readonly GlobalUserSelectQueryBuilderFactory $globalUserSelectQueryBuilderFactory,
		private readonly UserIdentityLookup $userIdentityLookup
	) {
	}

	/** @inheritDoc */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array {
		if ( $userIds === [] ) {
			return [];
		}

		$userNames = $this->resolveUserNames( $userIds );
		if ( $userNames === [] ) {
			return [];
		}

		return $this->fetchLockedUserIds( $userNames );
	}

	/**
	 * Resolve local user IDs to usernames
	 *
	 * @param int[] $userIds
	 * @return string[]
	 */
	private function resolveUserNames( array $userIds ): array {
		$userIdentities = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIds )
			->caller( __METHOD__ )
			->fetchUserIdentities();

		$userNames = [];
		foreach ( $userIdentities as $userIdentity ) {
			$userNames[] = $userIdentity->getName();
		}

		return $userNames;
	}

	/**
	 * @param string[] $globalUserNames
	 * @return int[]
	 */
	private function fetchLockedUserIds( array $globalUserNames ): array {
		$lockedUserIdentities = $this->globalUserSelectQueryBuilderFactory
			->newGlobalUserSelectQueryBuilder()
			->whereUserNames( $globalUserNames )
			->whereLocked( true )
			->caller( __METHOD__ )
			->fetchLocalUserIdentities();

		$blockedUserIds = [];
		foreach ( $lockedUserIdentities as $userIdentity ) {
			$blockedUserIds[] = $userIdentity->getId();
		}

		return $blockedUserIds;
	}
}
