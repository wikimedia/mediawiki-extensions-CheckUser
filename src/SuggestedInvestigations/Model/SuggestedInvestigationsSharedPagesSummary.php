<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use MediaWiki\Page\PageIdentity;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;

/**
 * Describes, for a single Suggested Investigations case, the pages that were edited by at least
 * two distinct accounts in the case ("shared pages"), the revision IDs of the edits made by the
 * case's accounts on those pages, and the total number of those edits.
 */
class SuggestedInvestigationsSharedPagesSummary extends SuggestedInvestigationsCaseMetadata {

	/**
	 * @param int[] $revisionIds The IDs of the edits made by the case's accounts on shared pages.
	 *   This is a merged list of `rev_id`s (for edits to existing pages) and `ar_rev_id`s (for edits
	 *   that have since been deleted); the two share a single ID space, so the merged list is unique.
	 * @param PageIdentity[] $sharedPages The pages edited by at least two distinct accounts in the
	 *   case. Pages known only from archived (deleted) revisions have a page ID of 0.
	 * @param ?string $firstEditTimestamp The time when the first edit on the shared pages was performed by
	 *   one of the users of interest.
	 * @param ?string $lastEditTimestamp The time when the last edit on the shared pages was performed by
	 *   one of the users of interest.
	 * @param UserIdentity[] $commonEditors The users who edited the shared pages.
	 */
	public function __construct(
		private readonly array $revisionIds = [],
		private readonly array $sharedPages = [],
		private readonly ?string $firstEditTimestamp = null,
		private readonly ?string $lastEditTimestamp = null,
		private readonly array $commonEditors = []
	) {
	}

	/**
	 * The IDs of the edits made by the case's accounts on shared pages. Includes both ids of live and deleted edits.
	 * @return int[]
	 */
	public function getRevisionIds(): array {
		return $this->revisionIds;
	}

	/**
	 * The shared pages on which the edits happened.
	 * @return PageIdentity[]
	 */
	public function getSharedPages(): array {
		return $this->sharedPages;
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

	/**
	 * Returns the list of users that have been editing the shared pages
	 * @return UserIdentity[]
	 */
	public function getCommonEditors(): array {
		return $this->commonEditors;
	}

	/** @inheritDoc */
	public function getMessage(): ?MessageSpecifier {
		if ( !$this->sharedPages ) {
			return null;
		}
		$msg = new MessageValue( 'checkuser-suggestedinvestigations-shared-pages-summary' );
		$msg->numParams( count( $this->revisionIds ), count( $this->sharedPages ) );
		return $msg;
	}
}
