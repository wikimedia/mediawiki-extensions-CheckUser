<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "CheckUser extension\n";
	exit( 1 );
}

# Add messages
global $wgMessageCache, $wgCheckUserMessages;
foreach( $wgCheckUserMessages as $language => $messages ) {
	$wgMessageCache->addMessages( $messages, $language );
}

class CheckUser extends SpecialPage
{
	function CheckUser() {
		SpecialPage::SpecialPage('CheckUser', 'checkuser');
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgTitle, $wgUser;

		if( !$wgUser->isAllowed( 'checkuser' ) ) {
			$wgOut->permissionRequired( 'checkuser' );
			return;
		}

		$this->setHeaders();

		$user = $wgRequest->getText( 'user' ) ? $wgRequest->getText( 'user' ) : $wgRequest->getText( 'ip' );
		$reason = $wgRequest->getText( 'reason' );
		$checktype = $wgRequest->getVal( 'checktype' );

		# An IPv4?
		if( preg_match( '#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(/\d{1,2}|)$#', $user ) ) {
			$ip = $user; 
			$name = ''; 
			$xff = '';
		} else if( preg_match( '#^[0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4})+(/\d{1,3}|)$#', $user ) ) {
			# An IPv6?
			$ip = IP::sanitizeIP($user); 
			$name = ''; 
			$xff = '';
		} else if( preg_match( '#^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(/\d{1,2}|)/xff$#', $user, $matches ) ) {
			# An IPv4 XFF string?
			list( $junk, $xffip, $xffbit) = $matches;
			$ip = ''; 
			$name = ''; 
			$xff = $xffip . $xffbit;
		} else if( preg_match( '#^([0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4})+)(/\d{1,3}|)/xff$#', $user, $matches ) ) {
			# An IPv6 XFF string?
			list( $junk, $xffip, $xffbit ) = $matches;
			$ip = ''; 
			$name = ''; 
			$xff = IP::sanitizeIP( $xffip ) . $xffbit;
		} else {
			# A user?
			$ip = ''; 
			$name = $user; 
			$xff = '';
		}

		$this->doForm( $user, $reason, $checktype, $ip, $xff, $name );
		$this->showLog( $user );
		
		if( !$wgRequest->wasPosted() )
			return;

		if( $checktype=='subuserips' ) {
			$this->doUserIPsRequest( $name, $reason );
		} else if( $xff && $checktype=='subipedits' ) {
			$this->doIPEditsRequest( $xff, true, $reason);
		} else if( $checktype=='subipedits' ) {
			$this->doIPEditsRequest( $ip, false, $reason);
		} else if( $xff && $checktype=='subipusers' ) {
			$this->doIPUsersRequest( $xff, true, $reason );
		} else if( $checktype=='subipusers' ) {
			$this->doIPUsersRequest( $ip, false, $reason );
		}
	}

	function doForm( $user, $reason, $checktype, $ip, $xff, $name ) {
		global $wgOut, $wgTitle;

		$action = $wgTitle->escapeLocalUrl();
		$encUser = htmlspecialchars( $user );
		$encReason = htmlspecialchars( $reason );
		# Fill in requested type if it makes sense
		$encipusers=0; $encipedits=0; $encuserips=0;
		if( $checktype=='subipusers' && ( $ip || $xff ) )
			$encipusers = 1;
		else if( $checktype=='subipedits' && ( $ip || $xff ) )
			$encipedits = 1;
		else if( $checktype=='subuserips' && $name )
			$encuserips = 1;
		# Defaults otherwise
		else if( $ip || $xff )
			$encipedits = 1;
		else
			$encuserips = 1;
		# Compile our nice form
		# User box length should fit things like "2001:0db8:85a3:08d3:1319:8a2e:0370:7344/100/xff"
		$wgOut->addWikiText( wfMsgHtml( 'checkuser-summary' ) );
		$form = "<form name='checkuser' action='$action' method='post'>";
		$form .= "<fieldset><legend>".wfMsgHtml( "checkuser-query" )."</legend>";
		$form .= "<table border='0' cellpadding='2'><tr>";
		$form .= "<td>".wfMsgHtml( "checkuser-target" ).":</td>";
		$form .= "<td>".Xml::input( 'user', 46, $encUser, array( 'id' => 'checktarget' ) )."</td>";
		$form .= "</tr><tr>";
		$form .= "<td></td><td class='checkuserradios'><table border='0' cellpadding='3'><tr>";
		$form .= "<td>".Xml::radio( 'checktype', 'subuserips', $encuserips, array('id' => 'subuserips') );
		$form .= Xml::label( wfMsgHtml("checkuser-ips"), 'subuserips' )."</td>";
		$form .= "<td>".Xml::radio( 'checktype', 'subipedits', $encipedits, array('id' => 'subipedits') );
		$form .= Xml::label( wfMsgHtml("checkuser-edits"), 'subipedits' )."</td>";
		$form .= "<td>".Xml::radio( 'checktype', 'subipusers', $encipusers, array('id' => 'subipusers') );
		$form .= Xml::label( wfMsgHtml("checkuser-users"), 'subipusers' )."</td>";
		$form .= "</tr></table></td>";
		$form .= "</tr><tr>";
		$form .= "<td>".wfMsgHtml( "checkuser-reason" ).":</td>";
		$form .= "<td>".Xml::input( 'reason', 46, $encReason, array( 'maxlength' => '150', 'id' => 'checkreason' ) );
		$form .= "&nbsp; &nbsp;".Xml::submitButton( wfMsgHtml( 'checkuser-check' ) )."</td>";
		$form .= "</tr></table></fieldset></form>";
		# Output form
		$wgOut->addHTML( $form );
	}

	/**
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $reason
	 * Shows all edits in Recent Changes by this IP (or range) and who made them
	 */
	function doIPEditsRequest( $ip, $xfor = false, $reason = '' ) {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgDBname;
		$fname = 'CheckUser::doIPEditsRequest';

		#invalid IPs are passed in as a blank string
		if(!$ip) {
			$s = wfMsgHtml('badipaddress');
			$wgOut->addHTML( $s );
			return;
		}

		$xnote = ($xfor) ? ' XFF' : '';
		if( !$this->addLogEntry( time() , $wgUser->getName() ,
			"got edits for$xnote" , htmlspecialchars( $ip ) , $wgDBname , $reason ) )
		{
			$wgOut->addHTML( '<p>'.wfMsgHtml('checkuser-log-fail').'</p>' );
		}

		$dbr = wfGetDB( DB_SLAVE );

		$ip_conds = $dbr->makeList( $this->getIpConds( $dbr, $ip, $xfor ), LIST_AND );
		$cu_changes = $dbr->tableName( 'cu_changes' );
		# Limit added for browser limitations and to avoid time outs
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		$sql = "SELECT * FROM $cu_changes FORCE INDEX($index) WHERE $ip_conds 
			ORDER BY cuc_timestamp DESC LIMIT 5000";

		$ret = $dbr->query( $sql, __METHOD__ );
		if( !$dbr->numRows( $ret ) ) {
			$s = wfMsgHtml("checkuser-nomatch")."\n";
		} else {
			global $IP;
			$this->skin = $wgUser->getSkin();
			// Cache common messages
			$this->preCacheMessages();
			// Try to optimize this query
			$lb = new LinkBatch;
			while ( $row = $ret->fetchObject() ) {
				$lb->add( $row->cuc_namespace, $row->cuc_title );
				$lb->add( NS_USER, $row->cuc_user_text );
				$lb->add( NS_USER_TALK, $row->cuc_user_text );
			}
			$lb->execute();
			$ret->seek( 0 );

			$s = '';
			while( $row = $ret->fetchObject() ) {
				$s .= $this->CUChangesLine( $row );
			}
			$s .= '</ul>';
		}

		$wgOut->addHTML( $s );
		$dbr->freeResult( $ret );
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	function preCacheMessages() {
		// Precache various messages
		if( !isset( $this->message ) ) {
			foreach( explode(' ', 'diff hist minoreditletter newpageletter blocklink log' ) as $msg ) {
				$this->message[$msg] = wfMsgExt( $msg, array( 'escape') );
			}
		}
	}

	/**
	 * @param $row
	 * @return a streamlined recent changes line with IP data
	 */
	function CUChangesLine( $row ) {
		global $wgLang;

		# Add date headers
		$date = $wgLang->date( $row->cuc_timestamp, true, true );
		if( !isset($this->lastdate) ) {
			$this->lastdate = $date;
			$line = "\n<h4>$date</h4>\n<ul class=\"special\">";
		} else if( $date != $this->lastdate ) {
			$line = "</ul>\n<h4>$date</h4>\n<ul class=\"special\">";
			$this->lastdate = $date;
		} else {
			$line = '';
		}	
		$line .= "<li>";
		# Create diff/hist/page links
		$line .= $this->getLinkFromRow( $row );
		# Show date
		$line .= ' . . ' . $wgLang->time( $row->cuc_timestamp, true, true ) . ' . . ';
		# Userlinks
		$line .= $this->skin->userLink( $row->cuc_user, $row->cuc_user_text );
		$line .= $this->skin->userToolLinks( $row->cuc_user, $row->cuc_user_text );
		# Action text, hackish ...
		if( $row->cuc_actiontext )
			$line .= ' ' . $this->skin->formatComment( $row->cuc_actiontext ) . ' ';
		# Comment
		$line .= $this->skin->commentBlock( $row->cuc_comment );
		
		$cuTitle = SpecialPage::getTitleFor( 'CheckUser' );
		$line .= '<br/>&nbsp; &nbsp; &nbsp; &nbsp; <small>';
		# IP
		$line .= ' <strong>IP</strong>: '.$this->skin->makeKnownLinkObj( $cuTitle,
			htmlspecialchars( $row->cuc_ip ),
			"user=" . urlencode( $row->cuc_ip ) );
		# XFF
		if( $row->cuc_xff !=null ) {
			# Flag our trusted proxies
			list($client,$trusted) = efGetClientIPfromXFF($row->cuc_xff,$row->cuc_ip);
			$c = $trusted ? '#F0FFF0' : '#FFFFCC';
			$line .= '</span>&nbsp;&nbsp;&nbsp;<span style="background-color: '.$c.'"><strong>XFF</strong>: ';
			$line .= $this->skin->makeKnownLinkObj( $cuTitle,
				htmlspecialchars( $row->cuc_xff ),
				"user=" . urlencode( $client ) . "/xff" )."</span>";
		}
		$line .= "</small></li>\n";

		return $line;
	}

	/**
	 * @param $row
	 * @create diff/hist/page link
	 */
	function getLinkFromRow( $row ) {
		if( $row->cuc_type == RC_LOG && $row->cuc_namespace == NS_SPECIAL ) {
			//Log items (old format) and events to logs
			list( $specialName, $logtype ) = SpecialPage::resolveAliasWithSubpage( $row->cuc_title );
			$logname = LogPage::logName( $logtype );
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			$links = '(' . $this->skin->makeKnownLinkObj( $title, $logname ) . ')';
		} elseif( $row->cuc_type == RC_LOG ) {
			//Log items
			$specialTitle = SpecialPage::getTitleFor( 'Log' );
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			$links = '(' . $this->skin->makeKnownLinkObj( $specialTitle, $this->message['log'],
				wfArrayToCGI( array('page' => $title->getPrefixedText() ) ) ) . ')';
		} elseif( !is_null( $row->cuc_this_oldid ) ) {
			//Everything else
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			#new pages
			if( $row->cuc_type == RC_NEW ) {
				$links = '(' . $this->message['diff'] . ') ';
			} else {
				#diff link
				$links = ' (' . $this->skin->makeKnownLinkObj( $title, $this->message['diff'],
					wfArrayToCGI( array(
						'curid' => $row->cuc_page_id,
						'diff' => $row->cuc_this_oldid,
						'oldid' => $row->cuc_last_oldid ) ) ) . ') ';
			}
			#history link
			$links .= ' (' . $this->skin->makeKnownLinkObj( $title, $this->message['hist'],
				wfArrayToCGI( array(
					'curid' => $row->cuc_page_id,
					'action' => 'history' ) ) ) . ') . . ';
			#some basic flags
			if( $row->cuc_type == RC_NEW )
				$links .= '<span class="newpage">' . $this->message['newpageletter'] . '</span>';
			if( $row->cuc_minor )
				$links .= '<span class="minor">' . $this->message['minoreditletter'] . '</span>';
			#page link
			$links .= ' ' . $this->skin->makeLinkObj( $title );
		} else {
			$links = '';
		}
		return $links;
	}

	/**
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $reason
	 * Lists all users in recent changes who used an IP, newest to oldest down
	 * Outputs usernames, latest and earliest found edit date, and count
	 * List unique IPs used for each user in time order, list corresponding user agent
	 */
	function doIPUsersRequest( $ip, $xfor = false, $reason = '' ) {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgDBname;
		$fname = 'CheckUser::doIPUsersRequest';

		#invalid IPs are passed in as a blank string
		if(!$ip) {
			$s = wfMsgHtml('badipaddress');
			$wgOut->addHTML( $s );
			return;
		}

		$xnote = ($xfor) ? ' XFF' : '';
		if( !$this->addLogEntry( time(), $wgUser->getName() ,
			"got users for$xnote" , htmlspecialchars( $ip ) , $wgDBname , $reason))
		{
			$wgOut->addHTML( '<p>'.wfMsgHtml('checkuser-log-fail').'</p>' );
		}

		$sk = $wgUser->getSkin();

		$dbr = wfGetDB( DB_SLAVE );

		$ip_conds = $dbr->makeList( $this->getIpConds( $dbr, $ip, $xfor ), LIST_AND );
		$cu_changes = $dbr->tableName( 'cu_changes' );
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		# Limit cap made to stop nasty timeouts
		$sql = "SELECT cuc_user_text, cuc_timestamp, cuc_user, cuc_ip, cuc_agent, cuc_xff 
			FROM $cu_changes FORCE INDEX($index) WHERE $ip_conds 
			ORDER BY cuc_timestamp DESC LIMIT 5000";

		$ret = $dbr->query( $sql, __METHOD__ );
		
		$users_first = $users_last = $users_edits = $users_ids = array();

		if( !$dbr->numRows( $ret ) ) {
			$s = wfMsgHtml( "checkuser-nomatch" )."\n";
		} else {
			while( ($row = $dbr->fetchObject( $ret ) ) != false ) {
				if( !array_key_exists( $row->cuc_user_text, $users_edits ) ) {
					$users_last[$row->cuc_user_text] = $row->cuc_timestamp;
					$users_edits[$row->cuc_user_text] = 0;
					$users_ids[$row->cuc_user_text] = $row->cuc_user;
					$users_infosets[$row->cuc_user_text] = array();
					$users_agentsets[$row->cuc_user_text] = array();
				}
				$users_edits[$row->cuc_user_text] += 1;
				$users_first[$row->cuc_user_text] = $row->cuc_timestamp;
				# Treat blank or NULL xffs as empty strings
				$ip_xff = $row->cuc_xff ? $row->cuc_xff : '';
				$xff_ip_combo = array( $row->cuc_ip, $ip_xff );
				# Add this IP/XFF combo for this username if it's not already there
				if( !in_array($xff_ip_combo,$users_infosets[$row->cuc_user_text]) ) {
					$users_infosets[$row->cuc_user_text][] = $xff_ip_combo;
					$users_agentsets[$row->cuc_user_text][] = $row->cuc_agent;
				}
			}
			
			# Reverse the order so it's first -> last
			foreach( $users_infosets as $name => $set ) {
				$users_infosets[$name] = array_reverse( $set );
			}
			foreach( $users_agentsets as $name => $set ) {
				$users_agentsets[$name] = array_reverse( $set );
			}
			
			$logs = SpecialPage::getTitleFor( 'Log' );
			$blocklist = SpecialPage::getTitleFor( 'Ipblocklist' );
			$s = '<ul>';
			foreach( $users_edits as $name => $count ) {
				$s .= '<li>';
				$s .= $sk->userLink( -1 , $name ) . $sk->userToolLinks( -1 , $name );
				$s .= ' (<a href="' . $wgTitle->escapeLocalURL( 'user=' . urlencode( $name ) ) . 
					'">' . wfMsgHtml('checkuser-check') . '</a>)';
				$s .= ' (' . $wgLang->timeanddate( $users_first[$name], true ) . ' -- ' . 
					$wgLang->timeanddate( $users_last[$name], true ) . ') ';
				$s .= ' [<strong>' . $count . '</strong>]<br/>';
				# Check if this user or IP is blocked
				# If so, give a link to the block log
				$block = new Block();
				$block->fromMaster( false ); // use slaves
				$ip = IP::isIPAddress( $name ) ? $name : ''; // only check IP blocks if we have an IP 
				if( $block->load( $ip, $users_ids[$name] ) ) {
					if( IP::isIPAddress($block->mAddress) && strpos($block->mAddress,'/') ) {
						$userpage = Title::makeTitle( NS_USER, $block->mAddress );
						$blocklog = $sk->makeKnownLinkObj( $logs, wfMsgHtml('checkuser-blocked'), 'type=block&page=' . urlencode( $userpage->getPrefixedText() ) );
						$s .= ' <strong>(' . $blocklog . ' - ' . $block->mAddress . ')</strong>';
					} else if( $block->mAuto ) {
						$blocklog = $sk->makeKnownLinkObj( $blocklist, wfMsgHtml('checkuser-blocked'), 'ip=' . urlencode( "#$block->mId" ) );
						$s .= ' <strong>(' . $blocklog . ')</strong>';
					} else {
						$userpage = Title::makeTitle( NS_USER, $name );
						$blocklog = $sk->makeKnownLinkObj( $logs, wfMsgHtml('checkuser-blocked'), 'type=block&page=' . urlencode( $userpage->getPrefixedText() ) );
						$s .= '<strong>(' . $blocklog . ')</strong>';
					}
				}
				$s .= '<ol>';
				# List out each IP/XFF combo for this username, and add the user agent for each
				foreach( $users_infosets[$name] as $n => $set ) {
					# IP link
					$s .= '<li>';
					$s .= '<a href="' . $wgTitle->escapeLocalURL( 'user=' . urlencode($set[0]) ) . '">' . htmlspecialchars($set[0]) . '</a>';
					# XFF string, link to /xff search
					if( $set[1] ) {
						# Flag our trusted proxies
						list($client,$trusted) = efGetClientIPfromXFF($set[1],$set[0]);
						$c = $trusted ? '#F0FFF0' : '#FFFFCC';
						$s .= '&nbsp;&nbsp;&nbsp;<span style="background-color: '.$c.'"><strong>XFF</strong>: ';
						$s .= $sk->makeKnownLinkObj( $wgTitle,
							htmlspecialchars( $set[1] ),
							"user=" . urlencode( $client ) . "/xff" )."</span>";
					}
					$s .= '<br/><i>' .  $users_agentsets[$name][$n] . '</i>';
					$s .= '</li>';
				}
				$s .= '</ol>';
				$s .= '</li>';
			}
			$s .= '</ul>';
		}

		$wgOut->addHTML( $s );
		$dbr->freeResult( $ret );
	}

	/**
	 * @param Database $db
	 * @param string $ip
	 * @param string $xfor
	 * @return array conditions
	 */
	function getIpConds( $db, $ip, $xfor = false ) {
		$type = ( $xfor ) ? 'xff' : 'ip';
		// IPv4 CIDR, 16-32 bits
		if( preg_match( '#^(\d+\.\d+\.\d+\.\d+)/(\d+)$#', $ip, $matches ) ) {
			if( $matches[2] < 16 || $matches[2] > 32 )
				return array( 'cuc_'.$type.'_hex' => -1 );
			list( $start, $end ) = IP::parseRange( $ip );
			return array( 'cuc_'.$type.'_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} else if( preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}/(\d+)$#', $ip, $matches ) ) {
			// IPv6 CIDR, 64-128 bits
			if( $matches[1] < 64 || $matches[1] > 128 )
				return array( 'cuc_'.$type.'_hex' => -1 );
			list( $start, $end ) = IP::parseRange6( $ip );
			return array( 'cuc_'.$type.'_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} else if( preg_match( '#^(\d+)\.(\d+)\.(\d+)\.(\d+)$#', $ip ) ) {
			// 32 bit IPv4
			$ip_hex = IP::toHex( $ip );
			return array( 'cuc_'.$type.'_hex' => $ip_hex );
		} else if( preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}$#', $ip ) ) {
			// 128 bit IPv6
			$ip_hex = IP::toHex( $ip );
			return array( 'cuc_'.$type.'_hex' => $ip_hex );
		} else {
			// throw away this query, incomplete IP, these don't get through the entry point anyway
			return array( 'cuc_'.$type.'_hex' => -1 );
		}
	}

	/**
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $reason
	 * Get all IPs used by a user
	 * Shows first and last date and number of edits
	 */
	function doUserIPsRequest( $user , $reason = '') {
		global $wgOut, $wgTitle, $wgLang, $wgUser, $wgDBname;
		$fname = 'CheckUser::doUserIPsRequest';

		$userTitle = Title::newFromText( $user, NS_USER );
		if( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		#IPs are passed in as a blank string
		if( !$user) {
			$s = wfMsgHtml('nouserspecified');
			$wgOut->addHTML( $s );
			return;
		}
		#get ID, works better than text as user may have been renamed
		$user_id = User::idFromName($user);

		#if user is not IP or nonexistant
		if( !$user_id ) {
			$s = wfMsgHtml('nosuchusershort', $user);
			$wgOut->addHTML( $s );
			return;
		}
		
		$sk = $wgUser->getSkin();

		if( !$this->addLogEntry( time() , $wgUser->getName() ,
			"got IPs for" , htmlspecialchars( $user ) , $wgDBname , $reason) ) 
		{
			$wgOut->addHTML( '<p>'.wfMsgHtml('checkuser-log-fail').'</p>' );
		}
		$dbr = wfGetDB( DB_SLAVE );

		$cu_changes = $dbr->tableName( 'cu_changes' );
		# Limit cap made to stop nasty timeouts
		$sql = "SELECT cuc_ip, cuc_timestamp 
			FROM $cu_changes FORCE INDEX(cuc_user_time) WHERE cuc_user = $user_id 
			ORDER BY cuc_timestamp DESC LIMIT 5000";

		$ret = $dbr->query( $sql, __METHOD__ );

		if( !$dbr->numRows( $ret ) ) {
			$s = wfMsgHtml("checkuser-nomatch")."\n";
		} else {
			$blockip = SpecialPage::getTitleFor( 'blockip' );
			$ips_edits=array();
			while( $row = $dbr->fetchObject( $ret ) ) {
				if( !array_key_exists( $row->cuc_ip, $ips_edits ) ) {
					$ips_edits[$row->cuc_ip]=0;
					$ips_last[$row->cuc_ip]=$row->cuc_timestamp;
				}
				$ips_edits[$row->cuc_ip]+=1;
				$ips_first[$row->cuc_ip]=$row->cuc_timestamp;
			}
			
			$logs = SpecialPage::getTitleFor( 'Log' );
			$blocklist = SpecialPage::getTitleFor( 'Ipblocklist' );
			$s = '<ul>';
			foreach( $ips_edits as $ip => $edits ) {
				$s .= '<li>';
				$s .= '<a href="' . $wgTitle->escapeLocalURL( 'user=' . urlencode( $ip ) ) . '">' . $ip . '</a>'; 
				$s .= ' (<a href="' . $blockip->escapeLocalURL( 'ip=' . urlencode( $ip ) ) . '">' . wfMsgHtml('blocklink') . '</a>)';
				$s .= ' (' . $wgLang->timeanddate( $ips_first[$ip], true ) . ' -- ' . $wgLang->timeanddate( $ips_last[$ip], true ) . ') '; 
				$s .= ' <strong>[' . $edits . ']</strong>';
				
				# If this IP is blocked, give a link to the block log
				$block = new Block();
				$block->fromMaster( false ); // use slaves
				if( $block->load( $ip, 0 ) ) {
					if( IP::isIPAddress($block->mAddress) && strpos($block->mAddress,'/') ) {
						$userpage = Title::makeTitle( NS_USER, $block->mAddress );
						$blocklog = $sk->makeKnownLinkObj( $logs, wfMsgHtml('checkuser-blocked'), 'type=block&page=' . urlencode( $userpage->getPrefixedText() ) );
						$s .= ' <strong>(' . $blocklog . ' - ' . $block->mAddress . ')</strong>';
					} else if( $block->mAuto ) {
						$blocklog = $sk->makeKnownLinkObj( $blocklist, wfMsgHtml('checkuser-blocked'), 'ip=' . urlencode( "#$block->mId" ) );
						$s .= ' <strong>(' . $blocklog . ')</strong>';
					} else {
						$userpage = Title::makeTitle( NS_USER, $ip );
						$blocklog = $sk->makeKnownLinkObj( $logs, wfMsgHtml('checkuser-blocked'), 'type=block&page=' . urlencode( $userpage->getPrefixedText() ) );
						$s .= ' <strong>(' . $blocklog . ')</strong>';
					}
				}
				
				$s .= '</li>';
			}
			$s .= '</ul>';
		}
		$wgOut->addHTML( $s );
		$dbr->freeResult( $ret );
	}

	function showLog( $user ) {
		global $wgCheckUserLog, $wgOut;

		if( $wgCheckUserLog === false || !file_exists( $wgCheckUserLog ) ) {
			# No log
			$wgOut->addHTML("<p>".wfMsgHtml("checkuser-nolog")."</p>");
			return;
		} else {
			global $wgRequest, $wgScript;

			if( $wgRequest->getVal( 'log' ) == 1 ) {
				$logsearch = $wgRequest->getText( 'logsearch' );
				# Show the log
				list( $limit, $offset ) = wfCheckLimits();
				$log = $this->tail( $wgCheckUserLog, $limit, $offset , $logsearch );
				#If not empty
				if( $log !==false) {
					$CUtitle = Title::makeTitle( NS_SPECIAL, 'CheckUser' );
					$title = $CUtitle->getNsText() . ':' . 'CheckUser';
					$encLogSearch = htmlspecialchars( $logsearch );
					$encUser = htmlspecialchars( $user );

					$scroller = wfViewPrevNext( $offset, $limit, $CUtitle,
						'log=1&logsearch=' . urlencode($logsearch) . '&user=' . urlencode($user),
						count($log) <= $limit);
					#If not filtered empty
					if( $log ) {
						if(count($log) > $limit) array_pop($log);
						$output = implode( "\n", $log );
					} else {
						$output = "<p>".wfMsgHtml('checkuser-nomatch')."</p>";
					}
					$wgOut->addHTML("<fieldset><legend>".wfMsgHtml('checkuser-log').":</legend>
						<form name='checkuserlog' action='$wgScript' method='get'>
						<input type='hidden' name='title' value='$title' /><input type='hidden' name='log' value='1' />
						<input type='hidden' name='user' value='$encUser' /><table border='0' cellpadding='1'><tr>
						<td>".wfMsgHtml('checkuser-search').":</td>
						<td><input type='text' name='logsearch' size='20' maxlength='50' value='$encLogSearch' /></td>
						<td><input type='submit' value='".wfMsgHtml('go')."' /></td>
						</tr></table><p>".wfMsgHtml('checkuser-logcase')."</p></form>");
					$wgOut->addHTML( "$scroller\n<ul>$output</ul>\n$scroller\n</fieldset>" );
				} else {
					$wgOut->addHTML( "<p>".wfMsgHtml('checkuser-empty')."</p>" );
				}
			} else {
				# Hide the log, show a link
				global $wgTitle, $wgUser;
				$skin = $wgUser->getSkin();
				$link = $skin->makeKnownLinkObj( $wgTitle, wfMsgHtml('checkuser-showlog'), 'log=1&user=' . urlencode($user) );
				$wgOut->addHTML( "<p>(<strong>$link</strong>)</p>" );
			}
		}
	}

	function tail( $filename, $limit, $offset , $logsearch ) {
		//wfSuppressWarnings();
		$file = fopen( $filename, "r" );
		//wfRestoreWarnings();
		if( $file === false ) {
			return false;
		}

		$filePosition = filesize( $filename );
		if( $filePosition == 0 ) {
			return array();
		}

		$lines = array();
		$bufSize = 1024;
		$lineCount = 0; $log = false;
		$total = $offset + $limit;
		$leftover = '';
		do {
			if( $filePosition < $bufSize ) {
				$bufSize = $filePosition;
			}
			$filePosition -= $bufSize;
			fseek( $file, $filePosition );
			$buffer = fread( $file, $bufSize );

			$parts = explode( "\n", $buffer );
			$num = count( $parts );

			#last line from chunk and first line of previous chunk, both fragements until merged
			if( $num > 0 ) {
				$log=true;
				$lmerge = $parts[$num - 1] . $leftover;
				if($logsearch)
					$srchind = strpos($lmerge, $logsearch);
				#dont count <li> and </li> tags, lens 4 and 5 resp.
				if( !$logsearch || (3 < $srchind && $srchind < (strlen($lmerge) - 5)) ) {
					$lineCount++;
					if( $lineCount > $offset ) {
						$lines[] = $lmerge;
						if( $lineCount > $total ) {
							fclose( $file );
							return $lines;
						}
					}
				}
			}
			#full lines, lines 2nd to "2nd to last" of chunk
			for( $i = $num - 2; $i > 0; $i-- ) {
				if($logsearch)
					$srchind = strpos($parts[$i], $logsearch);
				if( !$logsearch || (3 < $srchind && $srchind < (strlen($parts[$i]) - 5)) ) {
					$lineCount++;
					if( $lineCount > $offset ) {
						$lines[] = $parts[$i];
						if( $lineCount > $total ) {
							fclose( $file );
							return $lines;
						}
					}
				}
			}
			if( $num > 1 ) {
				$leftover = $parts[0];
			} else {
				$leftover = '';
				// Don't give up if there's still space in the file.
				// We sometimes get big blocks of 0s in the file,
				// probably due to conflicts via NFS.
			}
		} while( $filePosition > 0 );
		# leftover
		if($logsearch)
			$srchind = strpos($leftover, $logsearch);
		if( !$logsearch || ( 3 < $srchind && $srchind < ( strlen( $parts[$i] ) - 5 ) ) ) {
			$lineCount++;
			if( $lineCount > $offset ) {
				$lines[] = $leftover;
			}
		}
		fclose( $file );
		# was the log empty or nothing just met the search?
		if( $log ) return $lines;
		else return false;
	}

	function addLogEntry( $timestamp, $checker , $autsum, $target, $db, $reason ) {
		global $wgUser, $wgCheckUserLog;
		if( $wgCheckUserLog === false ) {
			// No log required, this is not an error
			return true;
		}

		$f = fopen( $wgCheckUserLog, 'a' );
		if( !$f ) {
			return false;
		}
		if( $reason ) $reason=' ("' . $reason . '")';
		else $reason = "";

		$date=date("H:i, j F Y",$timestamp);
		if( !fwrite( $f, "<li>$date, $checker $autsum $target on $db$reason</li>\n" ) ) {
			return false;
		}
		fclose( $f );
		return true;
	}
}

