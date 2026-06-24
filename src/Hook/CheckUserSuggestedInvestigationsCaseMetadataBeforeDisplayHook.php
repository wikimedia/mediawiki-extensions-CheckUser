<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Hook;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseMetadata;

interface CheckUserSuggestedInvestigationsCaseMetadataBeforeDisplayHook {

	/**
	 * This hook is run before metadata line for an SI case is displayed.
	 * Code can handle this hook to alter presentation of the metadata items.
	 *
	 * Handlers influence the output by mutating the metadata objects in $metadata: call
	 * {@see SuggestedInvestigationsCaseMetadata::overrideMessage()} on an item to replace the
	 * message shown for it (or to hide it, by passing null). Handlers may also add or remove items.
	 * The structured data on each object (e.g. the common editors of a shared-pages summary) is
	 * available so handlers can decide whether and how to alter the displayed message.
	 *
	 * @since 1.47
	 *
	 * @param int $caseId Identifier of the SI case for which the hook is called
	 * @param array<int,SuggestedInvestigationsCaseMetadata> &$metadata Metadata objects for the case
	 * @return void
	 */
	public function onCheckUserSuggestedInvestigationsCaseMetadataBeforeDisplay( int $caseId, array &$metadata ): void;
}
