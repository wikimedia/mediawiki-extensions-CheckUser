<?php

# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo "CheckUser extension";
	exit(1);
}

# Internationalisation file
require_once( 'CheckUser.i18n.php' );

$wgAvailableRights[] = 'checkuser';
$wgGroupPermissions['checkuser']['checkuser'] = true;

$wgCheckUserLog = '/home/wikipedia/logs/checkuser.log';

# How long to keep CU data?
$wgCUDMaxAge = 3 * 30 * 24 * 3600;

#Recent changes data hook
$wgHooks['RecentChange_save'][] = 'efUpdateCheckUserData';

/**
 * Hook function for RecentChange_save
 * Saves user data into the cu_changes table
 */
function efUpdateCheckUserData( $rc ) {
	$dbw = wfGetDB( DB_MASTER );
	extract( $rc->mAttribs );

	// Convert all IPs to IPv6 if needed
	$ip = wfGetIP();

	$xff = wfGetForwardedFor();
	$xff_ip = wfGetClientIPfromXFF( $xff );

	$agent = wfGetAgent();

	$rc_actiontext='';
	if ( $rc_type==RC_LOG ) {
		$title = Title::newFromText( $rc_title, $rc_namespace );
		$rc_actiontext = LogPage::actionText( $rc_log_type, $rc_log_action, $title, NULL, LogPage::extractParams($rc_params), false, false, true );
	}

	$dbw->insert( 'cu_changes',
		array(
			'cuc_namespace' => $rc_namespace,
			'cuc_title' => $rc_title,
			'cuc_minor' => $rc_minor,
			'cuc_user' => $rc_user,
			'cuc_user_text' => $rc_user_text,
			'cuc_actiontext' => $rc_actiontext,
			'cuc_comment' => $rc_comment,
			'cuc_page_id' => $rc_cur_id,
			'cuc_this_oldid' => $rc_this_oldid,
			'cuc_last_oldid' => $rc_last_oldid,
			'cuc_type' => $rc_type,
			'cuc_timestamp' => $rc_timestamp,
			'cuc_ip' => $ip,
			'cuc_ip_hex' => ($ip) ? IP::toHex( $ip ) : null,
			'cuc_xff' => $xff,
			'cuc_xff_hex' => ($xff_ip) ? IP::toHex( $xff_ip ) : null,
			'cuc_agent' => $agent,
		), __METHOD__
	);

	# Every 1000th edit, prune the checkuser changes table.
	wfSeedRandom();
	if ( 0 == mt_rand( 0, 999 ) ) {
		# Periodically flush old entries from the recentchanges table.
		global $wgCUDMaxAge;

		$dbw =& wfGetDB( DB_MASTER );
		$cutoff = $dbw->timestamp( time() - $wgCUDMaxAge );
		$recentchanges = $dbw->tableName( 'cu_changes' );
		$sql = "DELETE FROM $recentchanges WHERE cuc_timestamp < '{$cutoff}'";
		$dbw->query( $sql );
	}
}

if ( !function_exists( 'extAddSpecialPage' ) ) {
	require( dirname(__FILE__) . '/../ExtensionFunctions.php' );
}
extAddSpecialPage( dirname(__FILE__) . '/CheckUser_body.php', 'CheckUser', 'CheckUser' );

?>
