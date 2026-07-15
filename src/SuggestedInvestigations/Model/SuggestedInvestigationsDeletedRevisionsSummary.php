<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Describes, for a single Suggested Investigations case, the sum total of deleted revisions across all accounts.
 */
class SuggestedInvestigationsDeletedRevisionsSummary extends SuggestedInvestigationsCaseMetadata {

	/**
	 * @param int $totalDeletedRevisionsCount The total number of deleted revisions found,
	 * summed across all accounts in the case
	 */
	public function __construct(
		private readonly int $totalDeletedRevisionsCount = 0,
	) {
	}

	/**
	 * The total number of deleted revisions found for all accounts
	 * @return int
	 */
	public function getTotalDeletedRevisionsCount(): int {
		return $this->totalDeletedRevisionsCount;
	}

	/** @inheritDoc */
	public function getMessage(): ?MessageSpecifier {
		// Don't show if there are no deleted revisions
		if ( !$this->totalDeletedRevisionsCount ) {
			return null;
		}
		$msg = new MessageValue( 'checkuser-suggestedinvestigations-deleted-revisions-summary' );
		$msg->numParams( $this->totalDeletedRevisionsCount );
		return $msg;
	}
}
