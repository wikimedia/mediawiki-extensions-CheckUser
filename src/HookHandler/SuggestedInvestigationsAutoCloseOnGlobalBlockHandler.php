<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalBlockAuditHook;

class SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
	extends AbstractSuggestedInvestigationsAutoCloseHandler
	implements GlobalBlockingGlobalBlockAuditHook
{

	/** @inheritDoc */
	public function onGlobalBlockingGlobalBlockAudit( GlobalBlock $globalBlock ): void {
		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$targetUser = $globalBlock->getTargetUserIdentity();
		if ( $targetUser === null || !$globalBlock->isIndefinite() ) {
			return;
		}

		$this->enqueueAutoCloseJobsForUser( $targetUser->getId() );
	}

}
