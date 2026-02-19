<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

// phpcs:ignore Generic.Files.LineLength.TooLong
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Hooks\GlobalBlockingGlobalBlockAuditHook;
use MediaWiki\JobQueue\JobQueueGroup;
use Psr\Log\LoggerInterface;

class SuggestedInvestigationsAutoCloseOnGlobalBlockHandler
	extends AbstractSuggestedInvestigationsAutoCloseHandler
	implements GlobalBlockingGlobalBlockAuditHook
{

	public function __construct(
		SuggestedInvestigationsCaseLookupService $caseLookupService,
		JobQueueGroup $jobQueueGroup,
		LoggerInterface $logger,
		private readonly SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher $crossWikiJobDispatcher
	) {
		parent::__construct( $caseLookupService, $jobQueueGroup, $logger );
	}

	/** @inheritDoc */
	public function onGlobalBlockingGlobalBlockAudit( GlobalBlock $globalBlock ): void {
		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$targetUser = $globalBlock->getTargetUserIdentity();
		if ( $targetUser === null || $targetUser->getId() === 0 || !$globalBlock->isIndefinite() ) {
			return;
		}

		$this->enqueueAutoCloseJobsForUser( $targetUser->getId() );
		$this->crossWikiJobDispatcher->dispatch( $targetUser->getName() );
	}

}
