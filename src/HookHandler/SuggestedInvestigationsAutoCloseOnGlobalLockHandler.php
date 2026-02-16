<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserLockStatusChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;

class SuggestedInvestigationsAutoCloseOnGlobalLockHandler
	extends AbstractSuggestedInvestigationsAutoCloseHandler
	implements CentralAuthGlobalUserLockStatusChangedHook
{

	public function __construct(
		SuggestedInvestigationsCaseLookupService $caseLookupService,
		JobQueueGroup $jobQueueGroup,
		LoggerInterface $logger,
		private readonly UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( $caseLookupService, $jobQueueGroup, $logger );
	}

	/** @inheritDoc */
	public function onCentralAuthGlobalUserLockStatusChanged(
		CentralAuthUser $centralAuthUser,
		bool $isLocked
	): void {
		if ( !$isLocked ) {
			return;
		}

		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$localUser = $this->userIdentityLookup->getUserIdentityByName( $centralAuthUser->getName() );
		if ( $localUser === null || !$localUser->isRegistered() ) {
			return;
		}

		$this->enqueueAutoCloseJobsForUser( $localUser->getId() );
	}

}
