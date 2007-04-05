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
	$db->sourceFile( dirname( __FILE__ ) . '/cu_changes.sql' );
	
	$res = $db->select( 'recentchanges', '*', false, __FUNCTION__ );
	do {
		$batch = array();
		$row = false;
		for ( $i = 0; $i < BATCH_SIZE; $i++ ) {
			$row = $db->fetchObject( $res );
			if ( !$row ) {
				break;
			}
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
		$db->insert( 'cu_changes', $batch, __FUNCTION__ );
		wfWaitForSlaves( 5 );
	} while ( $row );
}

?>
