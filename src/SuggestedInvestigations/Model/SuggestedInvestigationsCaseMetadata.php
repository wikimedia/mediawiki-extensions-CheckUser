<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use Wikimedia\Message\MessageSpecifier;

/**
 * Represents a case metadata. A single object is supposed to refer to a piece of metadata related to a case,
 * such as "edits on the same pages", "number of accounts blocked" etc. The objects may store additional information
 * that might be useful for rendering the case details view.
 */
abstract class SuggestedInvestigationsCaseMetadata {

	/**
	 * Return a message to show at the case level. This will be a short text, suitable for displaying together
	 * with metadata from other sources.
	 *
	 * @return MessageSpecifier|null The message to display or null if nothing should be displayed for this object.
	 */
	abstract public function getMessage(): ?MessageSpecifier;
}
