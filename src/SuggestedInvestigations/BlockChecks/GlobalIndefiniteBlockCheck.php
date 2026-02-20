<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityLookup;

class GlobalIndefiniteBlockCheck implements IndefiniteBlockCheckInterface {

	public function __construct(
		private readonly GlobalBlockLookup $globalBlockLookup,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly bool $applyGlobalBlocksEnabled
	) {
	}

	/** @inheritDoc */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array {
		if ( !$this->applyGlobalBlocksEnabled ) {
			return [];
		}

		$blockedUserIds = [];
		foreach ( $userIds as $userId ) {
			if ( $this->isIndefinitelyGloballyBlocked( $userId ) ) {
				$blockedUserIds[] = $userId;
			}
		}

		return $blockedUserIds;
	}

	private function isIndefinitelyGloballyBlocked( int $userId ): bool {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( $userIdentity === null ) {
			return false;
		}

		$centralId = $this->centralIdLookup->centralIdFromLocalUser(
			$userIdentity, CentralIdLookup::AUDIENCE_RAW
		);
		if ( $centralId === 0 ) {
			return false;
		}

		$globalBlock = $this->globalBlockLookup->getGlobalBlockingBlock( null, $centralId );
		// this will also check if the GlobalBlock is locally whitelisted

		return $globalBlock !== null && $this->isIndefiniteBlock( $globalBlock );
	}

	private function isIndefiniteBlock( object $globalBlock ): bool {
		return isset( $globalBlock->gb_expiry ) && wfIsInfinity( $globalBlock->gb_expiry );
	}

}
