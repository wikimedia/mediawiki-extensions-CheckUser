<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use MediaWiki\Page\PageIdentity;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Describes, for a single Suggested Investigations case, the pages that were edited by at least
 * two distinct accounts in the case ("shared pages") and the total number of edits made by the
 * case's accounts on those pages.
 *
 * This object can be expanded in the future, so that it can store e.g. the revision ids of edits on
 * shared pages or any other related data that we might want to show.
 */
class SuggestedInvestigationsSharedPagesSummary extends SuggestedInvestigationsCaseMetadata {

	/**
	 * @param int $editCount The number of edits made by the case's accounts on shared pages.
	 * @param PageIdentity[] $sharedPages The pages edited by at least two distinct accounts in the
	 *   case. Pages known only from archived (deleted) revisions have a page ID of 0.
	 * @param ?string $firstEditTimestamp The time when the first edit on the shared pages was performed by
	 *   one of the users of interest.
	 * @param ?string $lastEditTimestamp The time when the last edit on the shared pages was performed by
	 *   one of the users of interest.
	 */
	public function __construct(
		private readonly int $editCount,
		private readonly array $sharedPages = [],
		private readonly ?string $firstEditTimestamp = null,
		private readonly ?string $lastEditTimestamp = null,
	) {
	}

	/** @internal For use in tests */
	public function getEditCount(): int {
		return $this->editCount;
	}

	/** @internal For use in tests */
	public function getPageCount(): int {
		return count( $this->sharedPages );
	}

	/**
	 * Returns the timestamp corresponding to the first of the shared edits
	 * @return ?string Timestamp in the MediaWiki format (or null if no edits)
	 */
	public function getFirstEditTimestamp(): ?string {
		return $this->firstEditTimestamp;
	}

	/**
	 * Returns the timestamp corresponding to the last of the shared edits
	 * @return ?string Timestamp in the MediaWiki format (or null if no edits)
	 */
	public function getLastEditTimestamp(): ?string {
		return $this->lastEditTimestamp;
	}

	/** @inheritDoc */
	public function getMessage(): ?MessageSpecifier {
		if ( !$this->sharedPages ) {
			return null;
		}
		$msg = new MessageValue( 'checkuser-suggestedinvestigations-shared-pages-summary' );
		$msg->numParams( $this->editCount, count( $this->sharedPages ) );
		return $msg;
	}
}
