<?php

namespace MediaWiki\CheckUser\Hook;

use RecentChange;

interface CheckUserInsertForRecentChangeHook {
	/**
	 * Allows extensions to intercept and modify the
	 * data inserted to cu_changes table when triggered
	 * by a RecentChange insert.
	 *
	 * @param RecentChange $rc The database row triggering the insert
	 * @param array &$rcRow The database row to be inserted to cu_changes table
	 * @since 1.40
	 *
	 * @deprecated Use CheckUserInsertLogEventRowHook, CheckUserInsertPrivateEventRowHook and/or
	 *  CheckUserInsertChangesRowHook. Current usages will not have events from cu_private_event
	 *  and cu_log_event trigger this hook due to the $rcRow having different columns.
	 */
	public function onCheckUserInsertForRecentChange(
		RecentChange $rc,
		array &$rcRow
	);
}
