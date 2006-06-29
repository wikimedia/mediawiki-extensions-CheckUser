<?php


# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
	echo "CheckUser extension";
	exit(1);
}

$wgAvailableRights[] = 'checkuser';
$wgGroupPermissions['checkuser']['checkuser'] = true;

$wgCheckUserLog = '/home/wikipedia/logs/checkuser.log';

if ( !function_exists( 'extAddSpecialPage' ) ) {
	require( dirname(__FILE__) . '/../ExtensionFunctions.php' );
}
extAddSpecialPage( dirname(__FILE__) . '/CheckUser_body.php', 'CheckUser', 'CheckUser' );

?>
