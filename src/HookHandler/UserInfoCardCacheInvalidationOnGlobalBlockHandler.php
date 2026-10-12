<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalBlockAuditHook;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalBlockLocalStatusChangeAuditHook;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalUnblockAuditHook;

readonly class UserInfoCardCacheInvalidationOnGlobalBlockHandler implements
	GlobalBlockingGlobalBlockAuditHook,
	GlobalBlockingGlobalBlockLocalStatusChangeAuditHook,
	GlobalBlockingGlobalUnblockAuditHook
{

	public function __construct(
		private UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalBlockAudit( GlobalBlock $globalBlock ): void {
		$this->invalidate( $globalBlock );
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalUnblockAudit( GlobalBlock $globalBlock ): void {
		$this->invalidate( $globalBlock );
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalBlockLocalStatusChangeAudit(
		GlobalBlock $globalBlock,
		bool $isLocallyDisabled,
		string|false $wikiId
	): void {
		// Invalidation applies to all wikis, thus the wiki ID is not used.
		$this->invalidate( $globalBlock );
	}

	private function invalidate( GlobalBlock $globalBlock ): void {
		$targetUser = $globalBlock->getTargetUserIdentity();
		if ( $targetUser === null || !$targetUser->isRegistered() ) {
			return;
		}
		$this->blockStatusCache->invalidateGlobalBlock( $targetUser->getName() );
	}
}
