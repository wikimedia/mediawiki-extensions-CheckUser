<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\Hook\UserSetEmailAuthenticationTimestampHook;
use MediaWiki\User\Hook\UserSetEmailHook;

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
		private readonly SuggestedInvestigationsTrigger $suggestedInvestigationsTrigger,
	) {
	}

	/** @inheritDoc */
	public function onLocalUserCreated( $user, $autocreated ): void {
		$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
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

		$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revisionRecord->getId() ]
		);
	}

	/** @inheritDoc */
	public function onUserSetEmail( $user, &$email ): void {
		$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_SET_EMAIL
		);
	}

	/** @inheritDoc */
	public function onUserSetEmailAuthenticationTimestamp( $user, &$timestamp ): void {
		$this->suggestedInvestigationsTrigger->matchSignalsAgainstUserInJob(
			$user,
			SuggestedInvestigationsSignalMatchService::EVENT_CONFIRM_EMAIL
		);
	}
}
