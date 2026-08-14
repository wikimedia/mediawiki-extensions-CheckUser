<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService;
use MediaWiki\Page\Hook\RevisionFromEditCompleteHook;

/**
 * Hooks needed to manage invalidating and updating suggested investigations case properties
 */
class SuggestedInvestigationsCasePropertiesHandler implements RevisionFromEditCompleteHook {
	public function __construct(
		private readonly SuggestedInvestigationsCaseLookupService $caseLookupService,
		private readonly SuggestedInvestigationsCasePropertyManagerService $casePropertyManagerService,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onRevisionFromEditComplete( $wikiPage, $rev, $originalRevId, $user, &$tags ): void {
		$userId = $user->getId();
		if ( !$userId ) {
			return;
		}

		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$casePropertyManagerService = $this->casePropertyManagerService;
		DeferredUpdates::addCallableUpdate(
			static function () use ( $casePropertyManagerService, $userId ) {
				$casePropertyManagerService->updateEditRelatedPropertiesForCasesWithUsers( [ $userId ] );
			},
			DeferredUpdates::POSTSEND
		);
	}
}
