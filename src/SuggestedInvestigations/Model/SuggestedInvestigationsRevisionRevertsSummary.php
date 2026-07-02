<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Describes, for a single Suggested Investigations case, the sum total of reverted revisions and
 * total revisions across all accounts.
 */
class SuggestedInvestigationsRevisionRevertsSummary extends SuggestedInvestigationsCaseMetadata {

	/**
	 * @param int $revertedRevisionsCount The total number of reverted revisions found, summed across
	 * all accounts in the case
	 * @param int $totalRevisionsCount The total number of revsiions found, summed across all accounts
	 * in the case
	 */
	public function __construct(
		private readonly int $revertedRevisionsCount = 0,
		private readonly int $totalRevisionsCount = 0,
	) {
	}

	/**
	 * The total number of revisions found for all accounts
	 * @return int
	 */
	public function getTotalRevisionsCount(): int {
		return $this->totalRevisionsCount;
	}

	/**
	 * The total number of reverted revisions found for all accounts
	 * @return int
	 */
	public function getRevertedRevisionsCount(): int {
		return $this->revertedRevisionsCount;
	}

	/** @inheritDoc */
	public function getMessage(): ?MessageSpecifier {
		// Don't show if there are no revisions at all
		if ( !$this->totalRevisionsCount ) {
			return null;
		}
		$msg = new MessageValue( 'checkuser-suggestedinvestigations-revision-reverts-summary' );
		$msg->numParams( $this->revertedRevisionsCount, $this->totalRevisionsCount );
		return $msg;
	}
}
