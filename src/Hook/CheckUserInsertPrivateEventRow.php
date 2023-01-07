<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\User\UserIdentity;
use RecentChange;

interface CheckUserInsertPrivateEventRow {
	/**
	 * Use this hook to modify the IP, XFF or other values
	 * in the row to be inserted into cu_private_event.
	 *
	 * If changing the request IP or XFF stored you are
	 * required to modify $ip and $xff (instead of
	 * modifying $row) as CheckUser will calculate other
	 * values based on those parameters and not the values
	 * in $row.
	 *
	 * Set the $xff to false to represent no defined XFF.
	 *
	 * @since 1.40
	 *
	 * @param string &$ip The users IP
	 * @param string|false &$xff The XFF for the request
	 * @param array &$row The row to be inserted (before defaults are applied)
	 * @param UserIdentity $user The user who performed the action associated with this row
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @codeCoverageIgnore Cannot be annotated as covered.
	 */
	public function onCheckUserInsertPrivateEventRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user,
		?RecentChange $rc
	);
}
