<?php

namespace MediaWiki\CheckUser\Hook;

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
	 * @since 1.40
	 *
	 * @param string &$ip The users IP
	 * @param string|false &$xff The XFF for the request
	 * @param array &$row The row to be inserted (before defaults are applied)
	 */
	public function onCheckUserInsertChangesRow(
		string &$ip,
		&$xff,
		array &$row
	);
}
