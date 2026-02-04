<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Jobs\SuggestedInvestigationsAutoCloseJob;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Specials\Hook\BlockIpCompleteHook;
use Psr\Log\LoggerInterface;

class SuggestedInvestigationsAutoCloseOnUsersBlockedHandler implements BlockIpCompleteHook {

	public function __construct(
		private readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly LoggerInterface $logger
	) {
	}

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $user, $priorBlock ): void {
		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$targetUser = $block->getTargetUserIdentity();
		if ( $targetUser === null || !$block->isSitewide() || !$block->isIndefinite() ) {
			return;
		}

		$openCaseIds = $this->caseLookupService->getOpenCaseIdsForUser( $targetUser->getId() );
		if ( $openCaseIds === [] ) {
			return;
		}

		foreach ( $openCaseIds as $caseId ) {
			$job = SuggestedInvestigationsAutoCloseJob::newSpec( $caseId, $this->isDelayedJobsEnabled() );
			$this->jobQueueGroup->lazyPush( $job );
		}
	}

	private function isDelayedJobsEnabled(): bool {
		if ( $this->jobQueueGroup->get( SuggestedInvestigationsAutoCloseJob::TYPE )->delayedJobsEnabled() ) {
			return true;
		}

		$this->logger->warning( SuggestedInvestigationsAutoCloseJob::class . ' delayed jobs are not supported' );
		return false;
	}

}
