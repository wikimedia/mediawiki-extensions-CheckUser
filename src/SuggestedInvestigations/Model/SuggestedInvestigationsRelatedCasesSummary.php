<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Describes, for a single Suggested Investigations case, the other cases that are "related" to it.
 *
 * Two cases are related when they have the identical set of accounts and a non-empty intersection of
 * signals (compared by name). Relatedness spans all case statuses.
 *
 * This object stores the related case IDs so that they can be linked to in a future task.
 */
class SuggestedInvestigationsRelatedCasesSummary extends SuggestedInvestigationsCaseMetadata {

	/**
	 * @param int[] $relatedCaseIds The IDs of the cases related to this case.
	 */
	public function __construct(
		private readonly array $relatedCaseIds
	) {
	}

	/**
	 * @return int[] The IDs of the cases related to this case.
	 */
	public function getRelatedCaseIds(): array {
		return $this->relatedCaseIds;
	}

	/** @inheritDoc */
	public function getMessage(): ?MessageSpecifier {
		if ( !$this->relatedCaseIds ) {
			return null;
		}
		$msg = new MessageValue( 'checkuser-suggestedinvestigations-related-cases-summary' );
		$msg->numParams( count( $this->relatedCaseIds ) );
		return $msg;
	}
}
