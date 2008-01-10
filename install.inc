<?php

define( 'BATCH_SIZE', 100 );

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

function create_cu_log( $db ) {
	global $wgDBtype, $wgCheckUserLog;
	if( $db->tableExists( 'cu_log' ) ) {
		echo "...cu_log already exists\n";
		return;
	}
	
	$sourcefile = $wgDBtype === 'postgres' ? '/cu_log.pg.sql' : '/cu_log.sql';
	$db->sourceFile( dirname( __FILE__ ) . $sourcefile );

	echo "...cu_log added\n";
	
	if ( empty( $wgCheckUserLog ) ) {
		return;
	}


	$file = fopen( $wgCheckUserLog, 'r' );

	$rxTimestamp = '(?P<timestamp>\d+:\d+, \d+ \w+ \d+)';
	$rxUser = '(?P<user>.*?)';
	$rxTarget = '(?P<target>.*?)';
	$rxWiki = '(?P<wiki>[^)]*?)';
	$rxReason = '(?: \("(?P<reason>.*)"\))?';

	$regexes = array(
		'ipedits-xff' => "!^<li>$rxTimestamp, $rxUser got edits for XFF $rxTarget on $rxWiki$rxReason</li>!",
		'ipedits'     => "!^<li>$rxTimestamp, $rxUser got edits for" ." $rxTarget on $rxWiki$rxReason</li>!",
		'ipusers-xff' => "!^<li>$rxTimestamp, $rxUser got users for XFF $rxTarget on $rxWiki$rxReason</li>!",
		'ipusers'     => "!^<li>$rxTimestamp, $rxUser got users for" ." $rxTarget on $rxWiki$rxReason</li>!",
		'userips'     => "!^<li>$rxTimestamp, $rxUser got IPs for".   " $rxTarget on $rxWiki$rxReason</li>!" );

	$matched = 0;
	$unmatched = 0;

	while ( false !== ( $line = fgets( $file ) ) ) {
		$found = false;
		foreach ( $regexes as $type => $regex ) {
			$m = false;
			if ( preg_match( $regex, $line, $m ) ) {
				if ( $m['wiki'] != wfWikiID() ) {
					continue;
				}

				list( $start, $end ) = IP::parseRange( $m['target'] );
				if ( $start === false ) {
					$targetUser = User::newFromName( $m['target'] );
					$targetID = $targetUser ? $targetUser->getID() : 0;
					$start = $end = $hex = '';
				} else {
					$hex = $start;
					if ( $start == $end ) {
						$start = $end = '';
					}
					$targetID = 0;
				}

				$user = User::newFromName( $m['user'] );
				$fields = array(
					'cul_id' => $db->nextSequenceValue( 'cu_log_cul_id' ),
					'cul_timestamp' => $db->timestamp( strtotime( $m['timestamp'] ) ),
					'cul_user' => $user->getID(),
					'cul_user_text' => $user->getName(),
					'cul_reason' => isset( $m['reason'] ) ? $m['reason'] : '',
					'cul_type' => $type,
					'cul_target_id' => $targetID,
					'cul_target_text' => $m['target'],
					'cul_target_hex' => $hex,
					'cul_range_start' => $start,
					'cul_range_end' => $end );

				$db->insert( 'cu_log', $fields, __METHOD__ );

				$found = true;
				break;
			}
		}
		if ( $found ) {
			$matched ++;
		} else {
			$unmatched ++;
		}
	}
	echo "...cu_log table populated: $matched matched rows, $unmatched discarded rows\n";
}

