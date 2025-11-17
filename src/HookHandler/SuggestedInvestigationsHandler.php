<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserSetEmailHook;
use MediaWiki\User\UserIdentity;

/**
 * Listens for events that trigger suggested investigation signals to be matched against a user.
 */
class SuggestedInvestigationsHandler implements
	LocalUserCreatedHook,
	PageSaveCompleteHook,
	UserSetEmailHook,
	UserSetEmailAuthenticationTimestampHook
{

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

	/** @inheritDoc */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		$this->matchSignalsAgainstUserOnDeferredUpdate(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revisionRecord->getId() ]
		);
	}

	/** @inheritDoc */
	public function onUserSetEmail( $user, &$email ): void {
		$this->matchSignalsAgainstUserOnDeferredUpdate(
			$user, SuggestedInvestigationsSignalMatchService::EVENT_SET_EMAIL
		);
	}

	/** @inheritDoc */
	public function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ): void {
		$this->matchSignalsAgainstUserOnDeferredUpdate(
			$user, SuggestedInvestigationsSignalMatchService::EVENT_CONFIRM_EMAIL
		);
	}

	private function matchSignalsAgainstUserOnDeferredUpdate(
		UserIdentity $userIdentity, string $eventType, array $extraData = []
	): void {
		DeferredUpdates::addCallableUpdate( function () use ( $userIdentity, $eventType, $extraData ) {
			$this->suggestedInvestigationsSignalMatchService->matchSignalsAgainstUser(
				$userIdentity, $eventType, $extraData
			);
		} );
	}
}
