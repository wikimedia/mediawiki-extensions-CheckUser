<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\User\UserIdentity;

interface CheckUserInsertChangesRow {
	/**
	 * Use this hook to modify the IP, XFF or other values
	 * in the row to be inserted into cu_changes.
	 *
	 * It is recommended that if the request IP or XFF is
	 * being changed it is done through the $ip and $xff
	 * parameters and not the $row as CheckUser will
	 * calculate a hex value of both to insert into
	 * cu_changes.
	 *
	 * Set the $xff to false to represent no defined XFF.
	 *
	 * The $user parameter is the user identity for the
	 * performer of the action associated with this
	 * cu_changes row insert.
	 *
	 * @since 1.40
	 *
	 * @param string &$ip The users IP
	 * @param string|false &$xff The XFF for the request
	 * @param array &$row The row to be inserted (before defaults are applied)
	 * @param UserIdentity $user The user who performed the action associated with this row
	 */
	public function onCheckUserInsertChangesRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user
	);
}
