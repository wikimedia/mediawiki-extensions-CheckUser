<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;

/**
 * Service that matches signals against users when events occur.
 */
class SuggestedInvestigationsSignalMatchService {

	public const CONSTRUCTOR_OPTIONS = [
		'CheckUserSuggestedInvestigationsEnabled',
	];

	public const EVENT_CREATE_ACCOUNT = 'createaccount';
	public const EVENT_AUTOCREATE_ACCOUNT = 'autocreateaccount';
	public const EVENT_SET_EMAIL = 'setemail';
	public const EVENT_CONFIRM_EMAIL = 'confirmemail';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HookRunner $hookRunner,
		private readonly SuggestedInvestigationsCaseLookupService $caseLookup,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Matches signals against a provided user when a given event occurs.
	 *
	 * This method will create or modify any suggested investigation cases based on the results of matching against
	 * the signals. The caller just needs to call this method to initiate the process.
	 *
	 * NOTE: Private code handles may handle this hook, so updating its signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param UserIdentity $userIdentity
	 * @param string $eventType The type of event that has occurred to trigger signals being matched.
	 *   One of the EVENT_* constants defined in this class, though custom event types may be triggered
	 *   by private code.
	 */
	public function matchSignalsAgainstUser( UserIdentity $userIdentity, string $eventType ): void {
		// Don't attempt to evaluate signals unless the feature is enabled, as we may not have database tables
		// to save suggested investigation cases to.
		if ( !$this->options->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			return;
		}

		if ( !$userIdentity->isRegistered() ) {
			// Make sure we only process registered users
			return;
		}

		$signalMatchResults = [];
		$this->hookRunner->onCheckUserSuggestedInvestigationsSignalMatch(
			$userIdentity, $eventType, $signalMatchResults
		);

		foreach ( $signalMatchResults as $signalMatchResult ) {
			if ( !$signalMatchResult->isMatch() ) {
				continue;
			}

			$this->addUserToCaseOrCreateNew( $userIdentity, $signalMatchResult );
		}
	}

	/**
	 * Attaches a user to all existing open SI cases with the same signal (if it allows for merging).
	 * Otherwise, creates a new case for the user and signal.
	 */
	private function addUserToCaseOrCreateNew(
		UserIdentity $user,
		SuggestedInvestigationsSignalMatchResult $signal
	): void {
		$mergeableCases = [];
		if ( $signal->valueMatchAllowsMerging() ) {
			$mergeableCases = $this->caseLookup->getCasesForSignal( $signal, [ CaseStatus::Open ] );
		}

		$signals = [ $signal ];
		$users = [ $user ];
		if ( count( $mergeableCases ) === 0 ) {
			$this->hookRunner->onCheckUserSuggestedInvestigationsBeforeCaseCreated(
				$signals, $users
			);
			$this->caseManager->createCase( $users, $signals );
		} else {
			foreach ( $mergeableCases as $case ) {
				$this->caseManager->addUsersToCase( $case->getId(), $users );
			}
		}
	}
}
