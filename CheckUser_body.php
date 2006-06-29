<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    echo "CheckUser extension\n";
    exit( 1 );
}

global $wgMessageCache;
$wgMessageCache->addMessage( 'checkuser', 'Check user' );

class CheckUser extends UnlistedSpecialPage
{
	function CheckUser() {
		SpecialPage::SpecialPage('CheckUser', 'checkuser');
	}

	function execute( $par ) {
		global $wgRequest, $wgOut, $wgTitle, $wgUser;
		
		if ( !in_array( 'checkuser', $wgUser->getRights() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->errorpage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$this->setHeaders();

		$ip = $wgRequest->getText( 'ip' );
		$user = $wgRequest->getText( 'user' );
		$subip = $wgRequest->getBool( 'subip' );
		$subuser = $wgRequest->getBool( 'subuser' );

		$this->doTop( $ip, $user );
		if ( $subip ) {
			$this->doIPRequest( $ip );
		} else if ( $subuser ) {
			$this->doUserRequest( $user );
		} else {
			$this->showLog();
		}
	}

	function doTop( $ip, $user ) {
		global $wgOut, $wgTitle;

		$action = $wgTitle->escapeLocalUrl();
		$encIp = htmlspecialchars( $ip );
		$encUser = htmlspecialchars( $user );

		$wgOut->addHTML( <<<EOT
<table border=0 cellpadding=5>
<form name="checkuser" action="$action" method=post>
<tr><td>
	IP: 
</td><td>
	<input type="text" name="ip" value="$encIp" width=50 /> <input type="submit" name="subip" value="OK" />
</td></tr>
</form>

<form name="checkuser" action="$action" method=post>
<tr><td>
	User:
</td><td>
	<input type="text" name="user" value="$encUser" width=50 /> <input type="submit" name="subuser" value="OK" />
</td></tr>
</form>
</table>
EOT
		);
	}

	function doIPRequest( $ip ) {
		global $wgUser, $wgOut, $wgLang, $wgDBname;
		$fname = 'CheckUser::doIPRequest';

		if ( !$this->addLogEntry( $wgLang->timeanddate( wfTimestampNow() ) . ' ' .
		  $wgUser->getName() . ' got edits for ' . htmlspecialchars( $ip ) . ' on ' . $wgDBname )) 
		{
			$wgOut->addHTML( '<p>Unable to add log entry</p>' );
		}

		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'recentchanges', array( '*' ), $this->getIpConds( $dbr, $ip ), $fname, 
	   		array( 'ORDER BY' => 'rc_timestamp DESC' ) );
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
			$counter = 1;
			while ( ($row = $dbr->fetchObject( $res ) ) != false ) {
				$rc = RecentChange::newFromRow( $row );
				$rc->counter = $counter++;
				$s .= $list->recentChangesLine( $rc, false );
			}
			$s .= $list->endRecentChangesList();
		}
		$wgOut->addHTML( $s );
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
			} elseif( $bits == 16 ) {
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

	function doUserRequest( $user ) {
		global $wgOut, $wgTitle, $wgLang, $wgUser, $wgDBname;
		$fname = 'CheckUser::doUserRequest';
		
		$userTitle = Title::newFromText( $user, NS_USER );
		if( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}

		if ( !$this->addLogEntry( $wgLang->timeanddate( wfTimestampNow() ) . ' ' .
		  $wgUser->getName() . ' got IPs for ' . htmlspecialchars( $user ) . ' on ' . $wgDBname ) ) 
		{
			$wgOut->addHTML( '<p>Unable to add log entry</p>' );
		}

		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select( 'recentchanges', array( 'DISTINCT rc_ip' ), array( 'rc_user_text' => $user ), $fname );
		if ( !$dbr->numRows( $res ) ) {
			$s =  "No results\n";
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
			# No log
			return;
		} else {
			global $wgRequest, $wgOut;
			
			if( $wgRequest->getVal( 'log' ) == 1 ) {
				# Show the log
				list( $limit, $offset ) = wfCheckLimits();
				$log = $this->tail( $wgCheckUserLog, $limit, $offset );
				if( !!$log ) {

					$scroller = wfViewPrevNext( $offset, $limit,
						Title::makeTitle( NS_SPECIAL, 'CheckUser' ),
						'log=1',
						count( $log ) < $limit );
					
					$output = implode( "\n", $log );
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
	
	function tail( $filename, $limit, $offset ) {
		//wfSuppressWarnings();
		$file = fopen( $filename, "rt" );
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
		$lineCount = 0;
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
			
			if( $num > 0 ) {
				if( $lineCount++ > $offset ) {
					$lines[] = $parts[$num - 1] . $leftover;
					if( $lineCount > $total ) {
						return $lines;
					}
				}
			}
			for( $i = $num - 2; $i > 0; $i-- ) {
				if( $lineCount++ > $offset ) {
					$lines[] = $parts[$i];
					if( $lineCount > $total ) {
						fclose( $file );
						return $lines;
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
		
		if( $lineCount++ > $offset ) {
			$lines[] = $leftover;
		}
		fclose( $file );
		return $lines;
	}

	function addLogEntry( $entry ) {
		global $wgUser, $wgCheckUserLog;
		if ( $wgCheckUserLog === false ) {
			// No log required, this is not an error
			return true;
		}

		$f = fopen( $wgCheckUserLog, 'a' );
		if ( !$f ) {
			return false;
		}
		if ( !fwrite( $f, "<li>$entry</li>\n" ) ) {
			return false;
		}
		fclose( $f );
		return true;
	}
}
?>
