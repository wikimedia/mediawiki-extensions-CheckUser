<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityLookup;
use stdClass;

class GlobalBlockCheck implements IndefiniteBlockCheckInterface, BlockCheckInterface {

	public function __construct(
		private readonly GlobalBlockLookup $globalBlockLookup,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly bool $applyGlobalBlocksEnabled,
	) {
	}

	/** @inheritDoc */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array {
		if ( !$this->applyGlobalBlocksEnabled ) {
			return [];
		}

		$blockedUserIds = [];
		foreach ( $userIds as $userId ) {
			$globalBlockRow = $this->getGlobalBlockRow( $userId );
			if ( $globalBlockRow === null ) {
				continue;
			}

			if ( $this->isIndefiniteBlock( $globalBlockRow ) ) {
				$blockedUserIds[] = $userId;
			}
		}

		return $blockedUserIds;
	}

	/** @inheritDoc */
	public function getBlockedUserIds( array $userIds ): array {
		if ( !$this->applyGlobalBlocksEnabled ) {
			return [];
		}

		$blockedUserIds = [];
		foreach ( $userIds as $userId ) {
			$globalBlockRow = $this->getGlobalBlockRow( $userId );
			if ( $globalBlockRow !== null ) {
				$blockedUserIds[] = $userId;
			}
		}

		return $blockedUserIds;
	}

	private function getGlobalBlockRow( int $userId ): ?stdClass {
		$userIdentity = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
		if ( $userIdentity === null ) {
			return null;
		}

		$centralId = $this->centralIdLookup->centralIdFromLocalUser(
			$userIdentity, CentralIdLookup::AUDIENCE_RAW
		);
		if ( $centralId === 0 ) {
			return null;
		}

		// this will also check if the GlobalBlock is locally whitelisted
		return $this->globalBlockLookup->getGlobalBlockingBlock( null, $centralId );
	}

	private function isIndefiniteBlock( object $globalBlock ): bool {
		return isset( $globalBlock->gb_expiry ) && wfIsInfinity( $globalBlock->gb_expiry );
	}

}
