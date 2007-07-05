<?php

/*
 * Makes the required database changes for the CheckUser extension
 */

define( 'BATCH_SIZE', 100 );

require_once( dirname( __FILE__ ) . '/../../maintenance/commandLine.inc' );
	
$db =& wfGetDB( DB_MASTER );
if ( $db->tableExists( 'cu_changes' ) && !isset( $options['force'] ) ) {
	echo "...cu_changes already exists.\n";
} else {
	$cutoff = isset( $options['cutoff'] ) ? wfTimestamp( TS_MW, $options['cutoff'] ) : null;
	create_cu_changes( $db, $cutoff );
}

function create_cu_changes( $db, $cutoff = null ) {
	global $wgDBtype;
	if( !$db->tableExists( 'cu_changes' ) ) {
		$sourcefile = $wgDBtype === 'postgres' ? '/cu_changes.pg.sql' : '/cu_changes.sql';
		$db->sourceFile( dirname( __FILE__ ) . $sourcefile );
	}
	
	echo "...cu_changes table added.\n";
	// Check if the table is empty
	$rcRows = $db->selectField( 'recentchanges', 'COUNT(*)', false, __FUNCTION__ );
	if ( !$rcRows ) {
		echo "recent_changes is empty; nothing to add.\n";
		exit( 1 );
	}
	
	if( $cutoff ) {
		// Something leftover... clear old entries to minimize dupes
		$encCutoff = $db->addQuotes( $db->timestamp( $cutoff ) );
		$db->delete( 'cu_changes',
			array( "cuc_timestamp < $encCutoff" ),
			__METHOD__ );
		$cutoffCond = "AND rc_timestamp < $encCutoff";
	} else {
		$cutoffCond = "";
	}
	
	$start = $db->selectField( 'recentchanges', 'MIN(rc_id)', false, __FUNCTION__ );
	$end = $db->selectField( 'recentchanges', 'MAX(rc_id)', false, __FUNCTION__ );
	$blockStart = $start;
	$blockEnd = $start + BATCH_SIZE - 1;
	
	$db->begin();
	while ( $blockStart <= $end ) {
		$cond = "rc_id BETWEEN $blockStart AND $blockEnd $cutoffCond";
		$res = $db->select( 'recentchanges', '*', $cond, __FUNCTION__ );
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
		$blockStart += BATCH_SIZE;
		$blockEnd += BATCH_SIZE;
		wfWaitForSlaves( 5 );
	}
	$db->commit();
	
	echo "...cu_changes table added and populated.\n";
}


