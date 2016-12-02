<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CheckUser' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CheckUser'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CheckUserAliases'] = __DIR__ . '/CheckUser.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for CheckUser extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the CheckUser extension requires MediaWiki 1.25+' );
}

// Global declarations and documentation kept for IDEs and PHP documentors.
// This code is never executed.

/** How long to keep CU data (in seconds)? */
$wgCUDMaxAge = 3 * 30 * 24 * 3600; // 3 months

/** Mass block limits */
$wgCheckUserMaxBlocks = 200;

/**
 * Set this to true if you want to force checkusers into giving a reason for
 * each check they do through Special:CheckUser.
 */
$wgCheckUserForceSummary = false;

/** Shortest CIDR limits that can be checked in any individual range check */
$wgCheckUserCIDRLimit = [
	'IPv4' => 16,
	'IPv6' => 32,
];

/**
 * Public key to encrypt private data that may need to be read later
 * Generate a public key with something like:
 * `openssl genrsa -out cu.key 2048; openssl rsa -in cu.key -pubout > cu.pub`
 * and paste the contents of cu.pub here
 */
$wgCUPublicKey = '';

/**
 * This can be used to add a link to Special:MultiLock by CentralAuth
 * to the Special:CheckUser's mass block form. This requires CentralAuth
 * extension to be installed on the wiki.
 * To enable this, set this to an array with a central wiki's database name
 * and an array with the name(s) of the global group(s) to add the link for.
 * Example:
 *  $wgCheckUserCAMultiLock = array(
 *  	'centralDB' => 'metawiki',
 *  	'groups' => array( 'steward' )
 *  );
 */
$wgCheckUserCAMultiLock = false;
