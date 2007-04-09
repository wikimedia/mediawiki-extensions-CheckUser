<?php

/*
 * Makes the required database changes for the CheckUser extension
 */

define( 'BATCH_SIZE', 100 );

require_once( dirname( __FILE__ ) . '/../../maintenance/commandLine.inc' );
	
$db =& wfGetDB( DB_MASTER );
if ( $db->tableExists( 'cu_changes' ) ) {
	echo "cu_changes already exists\n";
} else {
	create_cu_changes( $db );
}

function create_cu_changes( $db ) {
	global $wgDBtype;
	$sourcefile = $wgDBtype === 'postgres' ? '/cu_changes.pg.sql' : '/cu_changes.sql';

	$db->sourceFile( dirname( __FILE__ ) . $sourcefile );

	$start = $db->selectField( 'recentchanges', 'MIN(rc_id)', false, __FUNCTION__ );
	$end = $db->selectField( 'recentchanges', 'MAX(rc_id)', false, __FUNCTION__ );
	$blockStart = $start;
	$blockEnd = $start + BATCH_SIZE - 1;
	while ( $blockEnd <= $end ) {
		$res = $db->select( 'recentchanges', '*', "rc_id BETWEEN $blockStart AND $blockEnd", __FUNCTION__ );
		$batch = array();
		while ( $row = $db->fetchObject( $res ) ) {
			$batch[] = array( 
				'cuc_timestamp' => $row->rc_timestamp,
				'cuc_user' => $row->rc_user,
				'cuc_user_text' => $row->rc_user_text,
				'cuc_namespace' => $row->rc_namespace,
				'cuc_title' => $row->rc_title,
				'cuc_comment' => $row->rc_comment,
				'cuc_minor' => $row->rc_minor,
				'cuc_page_id' => $row->rc_cur_id,
				'cuc_this_oldid' => $row->rc_this_oldid,
				'cuc_last_oldid' => $row->rc_last_oldid,
				'cuc_type' => $row->rc_type,
				'cuc_ip' => $row->rc_ip,
				'cuc_ip_hex' => IP::toHex( $row->rc_ip ),
			);
		}
		if ( count( $batch ) ) {
			$db->insert( 'cu_changes', $batch, __FUNCTION__ );
		}
		$blockStart += BLOCK_SIZE;
		$blockEnd += BLOCK_SIZE;
		if ( function_exists ( 'wgWaitForSlaves' ) )
			wfWaitForSlaves( 5 );
	}
}

?>
