<?php

# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo "CheckUser extension";
	exit(1);
}

# RC_MOVE not used in this table, so no overlap
define( 'CU_ALT_LOGIN', 2);

# Internationalisation file
require_once( 'CheckUser.i18n.php' );

$wgExtensionCredits['specialpage'][] = array(
	'author' => 'Tim Starling, Aaron Schulz',
	'name' => 'CheckUser',
	'url' => 'http://www.mediawiki.org/wiki/Extension:CheckUser',
	'description' => 'Grants users with the appropriate permission the ability to check user\'s IP addresses and other information'
);

$wgAvailableRights[] = 'checkuser';
$wgGroupPermissions['checkuser']['checkuser'] = true;

$wgCheckUserLog = '/home/wikipedia/logs/checkuser.log';

# How long to keep CU data?
$wgCUDMaxAge = 3 * 30 * 24 * 3600;
# If set to true, usernames from leftover sessions for IP edits will be stored.
# It will also be stored during login if the old session data for the usre is 
# for a different account. (Note that user renames can cause this).
# If you have an older version of checkuser without the cuc_cookie_user column,
# run patch-cuc_cookie_user.sql before enabling this
$wgCURecordCookieData = false;

#Recent changes data hook
global $wgHooks;
$wgHooks['RecentChange_save'][] = 'efUpdateCheckUserData';
$wgHooks['ParserTestTables'][] = 'efCheckUserParserTestTables';
$wgHooks['LoginAuthenticateAudit'][] = 'efCheckUserRecordLogin';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efCheckUserSchemaUpdates';

/**
 * Hook function for RecentChange_save
 * Saves user data into the cu_changes table
 */
function efUpdateCheckUserData( $rc ) {
	global $wgUser, $wgCURecordCookieData;
	// Extract params
	extract( $rc->mAttribs );
	// Get IP
	$ip = wfGetIP();
	// Get XFF header
	$xff = wfGetForwardedFor();
	list($xff_ip,$trusted) = efGetClientIPfromXFF( $xff );
	// Our squid XFFs can flood this up sometimes
	$isSquidOnly = efXFFChainIsSquid( $xff );
	// Get agent
	$agent = wfGetAgent();
	// Store the log action text for log events
	// $rc_comment should just be the log_comment
	// BC: check if log_type and log_action exists
	// If not, then $rc_comment is the actiontext and comment
	if( isset($rc_log_type) && $rc_type==RC_LOG ) {
		$target = Title::makeTitle( $rc_namespace, $rc_title );
		$actionText = LogPage::actionText( $rc_log_type, $rc_log_action, $target, NULL, explode('\n',$rc_params) );
	} else {
		$actionText = '';
	}
	
	$rcRow = array(
		'cuc_namespace' => $rc_namespace,
		'cuc_title' => $rc_title,
		'cuc_minor' => $rc_minor,
		'cuc_user' => $rc_user,
		'cuc_user_text' => $rc_user_text,
		'cuc_actiontext' => $actionText,
		'cuc_comment' => $rc_comment,
		'cuc_page_id' => $rc_cur_id,
		'cuc_this_oldid' => $rc_this_oldid,
		'cuc_last_oldid' => $rc_last_oldid,
		'cuc_type' => $rc_type,
		'cuc_timestamp' => $rc_timestamp,
		'cuc_ip' => $ip,
		'cuc_ip_hex' => $ip ? IP::toHex( $ip ) : null,
		'cuc_xff' => !$isSquidOnly ? $xff : '',
		'cuc_xff_hex' => ($xff_ip && !$isSquidOnly) ? IP::toHex( $xff_ip ) : null,
		'cuc_agent' => $agent
	);
	// Fetch and add user cookie data
	if( $wgCURecordCookieData && $wgUser->isAnon() ) {
		$rcRow['cuc_cookie_user'] = efGetUsernameFromCookie();
	}

	$dbw = wfGetDB( DB_MASTER );
	$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

	# Every 100th edit, prune the checkuser changes table.
	wfSeedRandom();
	if ( 0 == mt_rand( 0, 99 ) ) {
		# Periodically flush old entries from the recentchanges table.
		global $wgCUDMaxAge;

		$dbw =& wfGetDB( DB_MASTER );
		$cutoff = $dbw->timestamp( time() - $wgCUDMaxAge );
		$recentchanges = $dbw->tableName( 'cu_changes' );
		$sql = "DELETE FROM $recentchanges WHERE cuc_timestamp < '{$cutoff}'";
		$dbw->query( $sql );
	}
	
	return true;
}

function efCheckUserRecordLogin( &$user, &$mPassword, &$retval ) {
	global $wgCURecordCookieData, $wgUser;
	// $wgCURecordCookieData must be enabled
	// Also, we only care for valid login attempts
	if( !$wgCURecordCookieData || $retval != LoginForm::SUCCESS )
		return true;
	// Do not record re-logins
	if( $wgUser->getName() != $user->getName() )
		return true;
	// Get IP
	$ip = wfGetIP();
	// Get XFF header
	$xff = wfGetForwardedFor();
	list($xff_ip,$trusted) = efGetClientIPfromXFF( $xff );
	// Our squid XFFs can flood this up sometimes
	$isSquidOnly = efXFFChainIsSquid( $xff );
	// Get agent
	$agent = wfGetAgent();
	// Get cookie data
	$cuc_cookie_name = efGetUsernameFromCookie();
	if( $cuc_cookie_name == $user->getName() )
		return true; // Nothing special...

	$rcRow = array(
		'cuc_namespace' => NS_USER,
		'cuc_title' => $user->getName(),
		'cuc_minor' => 0,
		'cuc_user' => $user->getId(),
		'cuc_user_text' => $user->getName(),
		'cuc_actiontext' => '',
		'cuc_comment' => '',
		'cuc_page_id' => 0,
		'cuc_this_oldid' => 0,
		'cuc_last_oldid' => 0,
		'cuc_type' => CU_ALT_LOGIN,
		'cuc_timestamp' => wfTimestampNow(),
		'cuc_ip' => $ip,
		'cuc_ip_hex' => $ip ? IP::toHex( $ip ) : null,
		'cuc_xff' => !$isSquidOnly ? $xff : '',
		'cuc_xff_hex' => ($xff_ip && !$isSquidOnly) ? IP::toHex( $xff_ip ) : null,
		'cuc_agent' => $agent,
		'cuc_cookie_user' => efGetUsernameFromCookie()
	);

	$dbw = wfGetDB( DB_MASTER );
	$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );
	
	return true;
}

/**
 * Locates the client IP within a given XFF string
 * @param string $xff
 * @param string $address, the ip that sent this header (optional)
 * @return array( string, bool )
 */
function efGetClientIPfromXFF( $xff, $address=NULL ) {
	if ( !$xff ) return array(null, false);
	// Avoid annoyingly long xff hacks
	$xff = trim( substr( $xff, 0, 255 ) );
	$client = null;
	$trusted = true;
	// Check each IP, assuming they are separated by commas
	$ips = explode(',',$xff);
	foreach( $ips as $n => $ip ) {
		$ip = trim($ip);
		// If it is a valid IP, not a hash or such
		if ( IP::isIPAddress($ip) ) {
			# The first IP should be the client
			if ( $n==0 ) {
				$client = $ip;
			# Check that all servers are trusted
			} else if ( !wfIsTrustedProxy($ip) ) {
				$trusted = false;
				break;
			}
		}
	}
	// We still have to test if the IP that sent 
	// this header is trusted to confirm results
	if ( $client != $address && (!$address || !wfIsTrustedProxy($address)) )
		$trusted = false;
	
	return array( $client, $trusted );
}

/**
 * Determines if an XFF string is just local squid IPs
 * @param string $xff
 * @return bool
 */
function efXFFChainIsSquid( $xff ) {
	global $wgSquidServers, $wgSquidServersNoPurge;

	if ( !$xff ) false;
	// Avoid annoyingly long xff hacks
	$xff = trim( substr( $xff, 0, 255 ) );
	$squidOnly = true;
	// Check each IP, assuming they are separated by commas
	$ips = explode(',',$xff);
	foreach( $ips as $n => $ip ) {
		$ip = trim($ip);
		// If it is a valid IP, not a hash or such
		if ( IP::isIPAddress($ip) ) {
			if ( $n==0 ) {
				// The first IP should be the client...
			} else if ( !in_array($ip,$wgSquidServers) && !in_array($ip,$wgSquidServersNoPurge) ) {
				$squidOnly = false;
				break;
			}
		}
	}
	
	return $squidOnly;
}

/**
 * Gets the username from client cookie
 * If the token is invalid for this user, this will return false
 * @return string
 */
function efGetUsernameFromCookie() {
	global $wgCookiePrefix;

	// Try to get name from session
	if( isset( $_SESSION['wsUserName'] ) ) {
		$name = $_SESSION['wsUserName'];
	// Try cookie
	} else if( isset( $_COOKIE["{$wgCookiePrefix}UserName"] ) ) {
		$name = $_COOKIE["{$wgCookiePrefix}UserName"];
	} else {
		return false;
	}
	// Load the supposed user
	$u = User::newFromName( $name );
	$u->load();
	// Validate the token
	if( isset( $_SESSION['wsToken'] ) ) {
		$passwordCorrect = $_SESSION['wsToken'] == $u->mToken;
	} else if( isset( $_COOKIE["{$wgCookiePrefix}Token"] ) ) {
		$passwordCorrect = $u->mToken == $_COOKIE["{$wgCookiePrefix}Token"];
	} else {
		return false;
	}
	// User must have proper credentials
	if( !$passwordCorrect )
		return false;
	
	return $name;
}

function efCheckUserSchemaUpdates() {
	global $wgDBtype, $wgExtNewFields, $wgExtNewIndexes;
	
	# Run install.php
	require( dirname(__FILE__) . '/install.php' );
	
	# FIXME: do postgres index changes!
	if ($wgDBtype == 'mysql') {	
		$wgExtNewFields[] = array('cu_changes', 
			'cuc_cookie_user', dirname(__FILE__) . '/archives/patch-cuc_cookie_user.sql');
		$wgExtNewIndexes[] = array('cu_changes', 
			'cuc_user_time', dirname(__FILE__) . '/archives/patch-cu_changes_indexes.sql');
	} else {
		$wgExtNewFields[] = array('cu_changes', 
			'cuc_cookie_user', dirname(__FILE__) . 'TEXT');
	}
	return true;
}

/**
 * Tell the parser test engine to create a stub cu_changes table,
 * or temporary pages won't save correctly during the test run.
 */
function efCheckUserParserTestTables( &$tables ) {
	$tables[] = 'cu_changes';
	return true;
}

if ( !function_exists( 'extAddSpecialPage' ) ) {
	require( dirname(__FILE__) . '/../ExtensionFunctions.php' );
}
extAddSpecialPage( dirname(__FILE__) . '/CheckUser_body.php', 'CheckUser', 'CheckUser' );
