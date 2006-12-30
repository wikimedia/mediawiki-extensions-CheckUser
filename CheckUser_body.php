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

		$ip = $wgRequest->getText( 'ip' );
		$user = $wgRequest->getText( 'user' );
		$reason = $wgRequest->getText( 'reason' );
		$subipusers = $wgRequest->getBool( 'subipusers' );
		$subipedits = $wgRequest->getBool( 'subipedits' );
		$subuser = $wgRequest->getBool( 'subuser' );
		#enter fix hack
		$suball = $wgRequest->getBool( 'suball' );

		$this->doTop( $ip, $user, $reason);
		if ( $ip && $subipedits ) {
			$this->doIPEditsRequest( $ip, $reason);
		} else if ( $ip && $subipusers ) {
			$this->doIPUsersRequest( $ip , $reason );
		} else if ( $user && $subuser ) {
			$this->doUserRequest( $user , $reason );
		} else if ( !$user && $ip && $suball ) {
		  	$this->doIPEditsRequest( $ip, $reason );
		} else if ( $user && !$ip && $suball ) {
		  	$this->doUserRequest( $user, $reason );
		} else {
			$this->showLog();
		}
	}

	function doTop( $ip, $user, $reason ) {
		global $wgOut, $wgTitle;

		$action = $wgTitle->escapeLocalUrl();
		$encIp = htmlspecialchars( $ip );
		$encUser = htmlspecialchars( $user );
		$encReason = htmlspecialchars( $reason );

		#$wgOut->addHTML( wfMsg('checkuser-summary') );
		$wgOut->addHTML( <<<EOT
<form name="checkuser" action="$action" method="post">
<table border='0' cellpadding='5'><tr>
	<td>Reason:</td>
	<td><table border='0' cellpadding='0'>
	<input type="text" name="reason" value="$encReason" maxlength='150' size='40' />
	</table></td><td></td>
	<td><input type="submit" name="suball" value="OK" style="visibility: hidden;"/></td>
</tr><tr>
	<td>User:</td>
	<td><table border='0' cellpadding='0'>
		<tr><td><input type="text" name="user" value="$encUser" width="50" /></td></tr>
		<tr><td><input type="submit" name="subuser" value="Get IPs" /></td></tr>
	</table></td><td>IP:</td>
	<td><table border='0' cellpadding='0'>
		<tr><td><input type="text" name="ip" value="$encIp" width="50"/></td></tr>
		<tr><td><input type="submit" name="subipedits" value="Get edits" style="font-weight: bold;"/><input type="submit" name="subipusers" value="Get users" /></td></tr>
	</table></td>
</tr></table></form><hr />
EOT
		);
	}

#shows all edits in Recent Changes by this IP (or range) and who made them
	function doIPEditsRequest( $ip, $reason = '') {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgDBname;
		$fname = 'CheckUser::doIPEditsRequest';
		
		if ( !$this->addLogEntry( time() , $wgUser->getName() ,
		'got edits for' , htmlspecialchars( $ip ) , $wgDBname , $reason))
		{
			$wgOut->addHTML( '<p>Unable to add log entry</p>' );
		}

		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'recentchanges', array( '*' ), $this->getIpConds( $dbr, $ip ), $fname, 
	   		array( 'ORDER BY' => 'rc_timestamp DESC' ) );
	   	
	   	$counter = 1;
		if ( !$dbr->numRows( $res ) ) {
			$s =  "No results\n";
		} else {
			global $IP;
			require_once( $IP.'/includes/RecentChange.php' );
			require_once( $IP.'/includes/ChangesList.php' );
			
			if ( in_array( 'newfromuser', array_map( 'strtolower', get_class_methods( 'ChangesList' ) ) ) ) {
				// MW >= 1.6
				$list = ChangesList::newFromUser( $wgUser );
			} else {
				// MW < 1.6
				$sk =& $wgUser->getSkin();
				$list = new ChangesList( $sk );
			}
			$s = $list->beginRecentChangesList();
			$ip_bloc3 = $this->parse17_23CIDR( $ip );
			while ( ($row = $dbr->fetchObject( $res ) ) != false ) {
			#hackish culling of 16 CIDR results to avoid messier SQL query
				if ( $ip_bloc3==null || $this->isIn17_23CIDR( $row->rc_ip, $ip_bloc3 )) {
				   $rc = RecentChange::newFromRow( $row );
				   $rc->counter = $counter++;
				   $s .= $list->recentChangesLine( $rc, false );
				}
			}
			$s .= $list->endRecentChangesList();
		}
		#check if anything left after culling
		if ( $counter===1 ) $s =  "No results.\n";
		$wgOut->addHTML( $s );
		$dbr->freeResult( $res );
	}

#Lists all users in Recent Changes who used an IP
#Outputs usernames, latest and earliest found edit date, and count
#Ordered by most recent edit, from newest to oldest down
	function doIPUsersRequest( $ip, $reason = '' ) {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgDBname;
		$fname = 'CheckUser::doIPUsersRequest';
		
		if ( !$this->addLogEntry( time(), $wgUser->getName() ,
		'got users for' , htmlspecialchars( $ip ) , $wgDBname , $reason))
		{
			$wgOut->addHTML( '<p>Unable to add log entry</p>' );
		}
		
		$users_id=Array(); $users_first=Array(); $users_last=Array(); $users_edits=Array();

		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'recentchanges', array( 'rc_user_text' , 'rc_user' , 'rc_timestamp', 'rc_ip'), 
			 $this->getIpConds( $dbr, $ip ), $fname, array( 'ORDER BY' => 'rc_timestamp DESC' ) );

		if ( !$dbr->numRows( $res ) ) {
			$s =  "No results.\n";
		} else {
		  	$s = '';
			$ip_bloc3 = $this->parse17_23CIDR( $ip );
  			while ( ($row = $dbr->fetchObject( $res ) ) != false ) {
  			#hackish culling of 16 CIDR results to avoid messier SQL query
  				if ( $ip_bloc3==null || $this->isIn17_23CIDR( $row->rc_ip, $ip_bloc3 )) {
				   	if ( !array_key_exists( $row->rc_user_text, $users_edits ) ) {
				  	$users_first[$row->rc_user_text]=$row->rc_timestamp;
				  	$users_edits[$row->rc_user_text]=0;
				  	$users_id[$row->rc_user_text]=$row->rc_user;
				  	}
				  	$users_edits[$row->rc_user_text]+=1;
				  	$users_last[$row->rc_user_text]=$row->rc_timestamp;
				}
			}
			$links = new Linker();
			#check if anything left after culling
			if ( count( $users_edits ) ==0 ) $s = "No results.\n";
			foreach ( $users_edits as $name=>$count ) {
		    $links->skin = $wgUser->getSkin();
		    #use checkip for IPs
		    if ( $users_id[$name]==0 ) $checktype='ip=';
		    else $checktype='user=';
		    #hack, ALWAYS show contribs links
			$toollinks = $links->skin->userToolLinks( -1 , $name );
			$s .= '<li><a href="' . $wgTitle->escapeLocalURL( $checktype . urlencode( $name ) ) . '">' . htmlspecialchars( $name ) . '</a> ' .$toollinks .
			' (' . wfmsg('histlast') . ': ' . $wgLang->timeanddate( $users_first[$name] ) . ') ' . ' (' . wfmsg('histfirst') . ': ' . $wgLang->timeanddate( $users_last[$name] ) . ') ' .
			' [<strong>' . $count . '</strong>]' . '</li>';
			}
		}
		
		$wgOut->addHTML( '<ul>' );
		$wgOut->addHTML( $s );
		$wgOut->addHTML( '</ul>' );
		$dbr->freeResult( $res );
	}
	
	/**
	 * Since we have stuff stored in text format, this only works easily
	 * for some simple cases, such as /16 and /24.
	 * @param Database $db
	 * @param string $ip
	 * @return array conditions
	 */
	function getIpConds( $db, $ip ) {
		// haaaack
		if( preg_match( '#^(\d+)\.(\d+)\.(\d+)\.(\d+)/(\d+)$#', $ip, $matches ) ) {
			list( $junk, $a, $b, $c, $d, $bits ) = $matches;
			if( $bits == 32 ) {
				$match = "$a.$b.$c.$d";
			} elseif( $bits == 24 ) {
				$match = "$a.$b.$c.%";
			} elseif( $bits >= 16 && $bits < 24) {
			//results culled after query
				$match = "$a.$b.%";
			} else {
				// Other sizes not supported. /8 is too big
				$match = $ip;
			}
			return array( 'rc_ip LIKE ' . $db->addQuotes( $match ) );
		} else {
			return array( 'rc_ip' => $ip );
		}
	}
	
	/**
	 * Function to convert the third bloc of a 17-23 CIDR range into binary
	 * Returns null if the range is not 17-23
	 */
	function parse17_23CIDR( $ip ) {
		// haaaack
		if( preg_match( '#^\d+\.\d+\.(\d+)\.\d+/(\d+)$#', $ip, $matches ) ) {
			list( $junk, $a, $bits ) = $matches;
			#for 17-23 queries, return blocs 3-4 in binary
			if( $bits > 16 && $bits < 24 ) {
				#Invalid IPs can return wrong results
				if ( $a > 256 ) $a = 256;
				$a_bin = base_convert( $a, 10, 2 );
				#convert has no starting zeros
				for ($i=0; $i<8-strlen($a_bin); $i++)
					$a_bin = "0$a_bin";
				$r_bin = substr($a_bin, 0 , $bits - 16);
				return $r_bin;
			} else {
				return null;
			}
		} else {
		return null;
		}
	}
	
	/**
	 * Function to see if a given IP is in a 17-23 CIDR range
	 * Assumes that IP is already in /16 range!!!
	 * @param given IP $ip
	 * @param third bloc in binary $bloc3_bin
	 */
	function isIn17_23CIDR( $ip, $bloc3_bin ) {
		// haaaack
		if( preg_match( '#^\d+\.\d+\.(\d+)#', $ip, $matches ) ) {
			list( $junk, $a ) = $matches;
			$a_bin = base_convert($a, 10, 2);
			#convert has no starting zeros
			for ($i=0; $i<8-strlen($a_bin); $i++)
				$a_bin = "0$a_bin";
			if( strpos( $a_bin , $bloc3_bin )===0 ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function doUserRequest( $user , $reason = '') {
		global $wgOut, $wgTitle, $wgLang, $wgUser, $wgDBname;
		$fname = 'CheckUser::doUserRequest';
		
		$userTitle = Title::newFromText( $user, NS_USER );
		if( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}

		if ( !$this->addLogEntry( time() , $wgUser->getName() ,
		'got IPs for' , htmlspecialchars( $user ) , $wgDBname , $reason) ) 
		{
			$wgOut->addHTML( '<p>Unable to add log entry</p>' );
		}

		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'recentchanges', array( 'DISTINCT rc_ip' ), array( 'rc_user_text' => $user ), $fname );
		if ( !$dbr->numRows( $res ) ) {
			$s =  "No results.\n";
		} else {
			$s = '<ul>';
			while ( ($row = $dbr->fetchObject( $res ) ) != false ) {
				$s .= '<li><a href="' . $wgTitle->escapeLocalURL( 'ip=' . urlencode( $row->rc_ip ) ) . '">' .
					htmlspecialchars( $row->rc_ip ) . '</a></li>';
			}
			$s .= '</ul>';
		}
		$wgOut->addHTML( $s );
	}

	function showLog() {
		global $wgCheckUserLog;
		
		if( $wgCheckUserLog === false || !file_exists( $wgCheckUserLog ) ) {
			global $wgOut;
			# No log
			$wgOut->addHTML("<p>No log file.</p>");
			return;
		} else {
			global $wgRequest, $wgOut, $wgScript;
			
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
					
					$scroller = wfViewPrevNext( $offset, $limit, $CUtitle,
						'log=1&logsearch=' . urlencode($logsearch),
						count($log) <= $limit);
					#If not filtered empty
					if ( $log ) {
					   if (count($log) > $limit) array_pop($log);
					   $output = implode( "\n", $log );
					}
					else
						$output = "<p>No matches found.</p>";
					$wgOut->addHTML("<br></br>
					<form name='checkuserlog' action='$wgScript' method='get'>
					<input type='hidden' name='title' value='$title' /><input type='hidden' name='log' value='1' />
					<table border='0' cellpadding='1'><tr>
					<td>Search:</td>
					<td><input type='text' name='logsearch' size='15' maxlength='50' value='$encLogSearch' /></td>
					<td><input type='submit' value='Go' /></td>
					</tr></table></form><br></br>");
					$wgOut->addHTML( "$scroller\n<ul>$output</ul>\n$scroller\n" );
				} else {
					$wgOut->addHTML( "<p>The log contains no items.</p>" );
				}
			} else {
				# Hide the log, show a link
				global $wgTitle, $wgUser;
				$skin = $wgUser->getSkin();
				$link = $skin->makeKnownLinkObj( $wgTitle, 'Show log', 'log=1' );
				$wgOut->addHTML( "<p>$link</p>" );
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
				if ($logsearch)
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
				if ($logsearch)
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
				break;
			}
		} while( $filePosition > 0 );
		
		if ($logsearch)
		   $srchind = strpos($leftover, $logsearch);
		if ( !$logsearch || (3 < $srchind && $srchind < (strlen($parts[$i]) - 5)) ) {
		   $lineCount++;
		   if( $lineCount > $offset ) {
			   $lines[] = $leftover;
			}
		}
		fclose( $file );
		#was the log empty or nothing just met the search?
		if ( $log ) return $lines;
		else return false;
	}

	function addLogEntry( $timestamp, $checker , $autsum, $target, $db, $reason ) {
		global $wgUser, $wgCheckUserLog;
		if ( $wgCheckUserLog === false ) {
			// No log required, this is not an error
			return true;
		}

		$f = fopen( $wgCheckUserLog, 'a' );
		if ( !$f ) {
			return false;
		}
		if ( $reason ) $reason=' ("' . $reason . '")';
		else $reason = "";
		
		$date=date("H:m, j F Y",$timestamp);
		if ( !fwrite( $f, "<li>$date, $checker $autsum $target on $db$reason</li>\n" ) ) {
			return false;
		}
		fclose( $f );
		return true;
	}
}
?>