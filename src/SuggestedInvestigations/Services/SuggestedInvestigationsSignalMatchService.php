<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Services;

use MediaWiki\CheckUser\Hook\HookRunner;
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

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HookRunner $hookRunner
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Matches signals against a provided user when a given event occurs.
	 *
	 * This method will create or modify any suggested investigation cases based on the results of matching against
	 * the signals. The caller just needs to call this method to initiate the process.
	 *
	 * NOTE: Private code handles may handle this hook, so updating it's signature may break code not visible
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

		$signalMatchResults = [];
		$this->hookRunner->onCheckUserSuggestedInvestigationsSignalMatch(
			$userIdentity, $eventType, $signalMatchResults
		);

		// TODO: Use $signalMatchResults to create or modify suggested investigation cases in T403223
	}
}
