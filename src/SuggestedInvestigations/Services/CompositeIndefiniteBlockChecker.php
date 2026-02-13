<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\IndefiniteBlockCheckInterface;

class CompositeIndefiniteBlockChecker {

	/** @param IndefiniteBlockCheckInterface[] $blockChecks */
	public function __construct( private readonly array $blockChecks ) {
	}

	/**
	 * Return user IDs that are not indefinitely blocked by any of the
	 * registered block checks.
	 *
	 * @param int[] $localUserIds
	 * @return int[] User IDs that remain unblocked
	 */
	public function getUnblockedUserIds( array $localUserIds ): array {
		$unblockedUserIds = $localUserIds;
		foreach ( $this->blockChecks as $check ) {
			$blockedUserIds = $check->getIndefinitelyBlockedUserIds( $unblockedUserIds );
			$unblockedUserIds = array_values( array_diff( $unblockedUserIds, $blockedUserIds ) );
			if ( $unblockedUserIds === [] ) {
				return [];
			}
		}

		return $unblockedUserIds;
	}
}
