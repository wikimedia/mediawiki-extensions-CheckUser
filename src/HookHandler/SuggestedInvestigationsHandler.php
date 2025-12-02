<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserSetEmailHook;
use MediaWiki\User\UserIdentity;
use Profiler;
use Wikimedia\ScopedCallback;

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
		// Don't process null edits, as the revision ID will not be performed by the user
		// who just attempted to edit
		if ( $editResult->isNullEdit() ) {
			return;
		}

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
		// We skip warnings about accessing the primary DB here because that is what Special:ConfirmEmail does
		$this->matchSignalsAgainstUserOnDeferredUpdate(
			$user, SuggestedInvestigationsSignalMatchService::EVENT_CONFIRM_EMAIL,
			skipReplicasExpectations: true
		);
	}

	/**
	 * Matches signals against the provided event in a deferred update (to be run postsend)
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $eventType One of the `SuggestedInvestigationsSignalMatchService::EVENT_*` constants
	 * @param array $extraData
	 * @param bool $skipReplicasExpectations Whether to suppress TransactionProfiler warnings related to using
	 *   primary DBs. This is needed in the case where the event is made on a GET request.
	 */
	private function matchSignalsAgainstUserOnDeferredUpdate(
		UserIdentity $userIdentity, string $eventType, array $extraData = [], bool $skipReplicasExpectations = false
	): void {
		DeferredUpdates::addCallableUpdate( function () use (
			$userIdentity, $eventType, $extraData, $skipReplicasExpectations
		) {
			// We may need to skip replica expectations being failed for some events. Specifically at the moment
			// this is Special:ConfirmEmail, which makes writes on a GET request intentionally.
			$trxProfiler = Profiler::instance()->getTransactionProfiler();
			$scope = $skipReplicasExpectations ?
				$trxProfiler->silenceForScope( $trxProfiler::EXPECTATION_REPLICAS_ONLY ) : null;

			$this->suggestedInvestigationsSignalMatchService->matchSignalsAgainstUser(
				$userIdentity, $eventType, $extraData
			);

			ScopedCallback::consume( $scope );
		} );
	}
}
