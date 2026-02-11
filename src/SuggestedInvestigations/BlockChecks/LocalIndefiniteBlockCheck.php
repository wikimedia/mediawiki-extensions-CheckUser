<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks;

use MediaWiki\Block\DatabaseBlockStore;

class LocalIndefiniteBlockCheck implements IndefiniteBlockCheckInterface {

	public function __construct(
		private readonly DatabaseBlockStore $blockStore
	) {
	}

	/** @inheritDoc */
	public function getIndefinitelyBlockedUserIds( array $userIds ): array {
		$blocks = $this->blockStore->newListFromConds( [ 'bt_user' => $userIds ] );
		$blockedUserIds = [];
		foreach ( $blocks as $block ) {
			$target = $block->getTargetUserIdentity();
			if ( $target !== null && $block->isSitewide() && $block->isIndefinite() ) {
				$blockedUserIds[] = $target->getId();
			}
		}

		return $blockedUserIds;
	}
}
