<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\User\UserIdentity;

/**
 * Listens for events that trigger suggested investigation signals to be matched against a user.
 */
class SuggestedInvestigationsHandler implements LocalUserCreatedHook {

	public function __construct(
		private readonly SuggestedInvestigationsSignalMatchService $suggestedInvestigationsSignalMatchService
	) {
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): void {
		$this->matchSignalsAgainstUserOnDeferredUpdate(
			$user,
			$autocreated ?
				SuggestedInvestigationsSignalMatchService::EVENT_AUTOCREATE_ACCOUNT :
				SuggestedInvestigationsSignalMatchService::EVENT_CREATE_ACCOUNT
		);
	}

	private function matchSignalsAgainstUserOnDeferredUpdate( UserIdentity $userIdentity, string $eventType ): void {
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity, $eventType ) {
			$this->suggestedInvestigationsSignalMatchService->matchSignalsAgainstUser( $userIdentity, $eventType );
		} );
	}
}
