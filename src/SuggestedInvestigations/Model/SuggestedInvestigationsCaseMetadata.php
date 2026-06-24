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

	private ?MessageSpecifier $messageOverride = null;
	private bool $isMessageOverridden = false;

	/**
	 * Return a message to show at the case level. This will be a short text, suitable for displaying together
	 * with metadata from other sources.
	 *
	 * @return MessageSpecifier|null The message to display or null if nothing should be displayed for this object.
	 */
	abstract public function getMessage(): ?MessageSpecifier;

	/**
	 * Override the message displayed for this metadata item in the case metadata line.
	 *
	 * Intended for use by CheckUserSuggestedInvestigationsCaseMetadataBeforeDisplay hook handlers, which can
	 * read the structured context from this object and replace what is shown for it (e.g. to add a link).
	 *
	 * @param MessageSpecifier|null $message The message to display, or null to display nothing for this item.
	 */
	public function overrideMessage( ?MessageSpecifier $message ): void {
		$this->messageOverride = $message;
		$this->isMessageOverridden = true;
	}

	/**
	 * Whether a display override has been set via {@see self::overrideMessage()}.
	 *
	 * This is distinct from the override being null: an override of null means "display nothing for this item",
	 * while no override means the message from {@see self::getMessage()} should be used.
	 */
	public function isMessageOverridden(): bool {
		return $this->isMessageOverridden;
	}

	/**
	 * Return the display override set via {@see self::overrideMessage()}, or null if it was set to null.
	 *
	 * Callers should check {@see self::isMessageOverridden()} first to tell an unset override apart
	 * from one explicitly set to null.
	 */
	public function getMessageOverride(): ?MessageSpecifier {
		return $this->messageOverride;
	}
}
