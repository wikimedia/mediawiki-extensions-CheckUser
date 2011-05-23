<?php

/*
 * Created on Jan 14, 2009
 *
 * Copyright (C) 2009 Soxred93 soxred93 [-at-] gee mail [-dot-] com,
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	// Eclipse helper - will be ignored in production
	require_once ( 'ApiBase.php' );
}

class CheckUserApi extends ApiBase {

	public function __construct( $main, $action ) {
		parent :: __construct( $main, $action );
		ApiBase::$messageMap['cantcheckuser'] = array( 'code' => 'cantcheckuser', 'info' => "You dont have permission to run a checkuser" );
		ApiBase::$messageMap['checkuserlogfail'] = array( 'code' => 'checkuserlogfail', 'info' => "Inserting a log entry failed" );
		ApiBase::$messageMap['nomatch'] = array( 'code' => 'nomatch', 'info' => "No matches found" );
		ApiBase::$messageMap['nomatchedit'] = array( 'code' => 'nomatch', 'info' => "No matches found. Last edit was on $1 at $2" );
	}

	public function execute() {
		global $wgUser;
		$this->getMain()->requestWriteMode();
		$params = $this->extractRequestParams();

		if ( !isset( $params['reason'] ) ) {
			$reason = '';
		} else {
			$reason = $params['reason'];
		}
		if ( !$wgUser->isAllowed( 'checkuser' ) ) {
			$this->dieUsageMsg( array( 'cantcheckuser' ) );
		}

		$user = $params['user'];
		$checktype = $params['type'];
		$period = $params['duration'];

		# An IPv4?
		if ( preg_match( '#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(/\d{1,2}|)$#', $user ) ) {
			$ip = $user;
			$name = '';
			$xff = '';
		# An IPv6?
		} else if ( preg_match( '#^[0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4})+(/\d{1,3}|)$#', $user ) ) {
			$ip = IP::sanitizeIP( $user );
			$name = '';
			$xff = '';
		# An IPv4 XFF string?
		} else if ( preg_match( '#^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(/\d{1,2}|)/xff$#', $user, $matches ) ) {
			list( $junk, $xffip, $xffbit ) = $matches;
			$ip = '';
			$name = '';
			$xff = $xffip . $xffbit;
		# An IPv6 XFF string?
		} else if ( preg_match( '#^([0-9A-Fa-f]{1,4}(:[0-9A-Fa-f]{1,4})+)(/\d{1,3}|)/xff$#', $user, $matches ) ) {
			list( $junk, $xffip, $xffbit ) = $matches;
			$ip = '';
			$name = '';
			$xff = IP::sanitizeIP( $xffip ) . $xffbit;
		# A user?
		} else {
			$ip = '';
			$name = $user;
			$xff = '';
		}

		if ( $checktype == 'subuserips' ) {
			$res = $this->doUserIPsRequest( $name, $reason, $period );
		} else if ( $xff && $checktype == 'subipedits' ) {
			$res = $this->doIPEditsRequest( $xff, true, $reason, $period );
		} else if ( $checktype == 'subipedits' ) {
			$res = $this->doIPEditsRequest( $ip, false, $reason, $period );
		} else if ( $xff && $checktype == 'subipusers' ) {
			$res = $this->doIPUsersRequest( $xff, true, $reason, $period );
		} else if ( $checktype == 'subipusers' ) {
			$res = $this->doIPUsersRequest( $ip, false, $reason, $period );
		} else if ( $checktype == 'subuseredits' ) {
			$res = $this->doUserEditsRequest( $user, $reason, $period );
		}

		if ( !is_null( $res ) ) {
			$this->getResult()->setIndexedTagName( $res, 'cu' );
			$this->getResult()->addValue( null, $this->getModuleName(), $res );
		}
	}

	function doUserIPsRequest( $user , $reason = '', $period = 0 ) {
		global $wgTitle, $wgLang, $wgUser;

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		# IPs are passed in as a blank string
		if ( !$user ) {
			$this->dieUsageMsg( array( 'nosuchusershort' ) );
		}
		# Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		# If user is not IP or nonexistent
		if ( !$user_id ) {
			$this->dieUsageMsg( array( 'nosuchusershort' ) );
		}

		if ( !$this->addLogEntry( 'userips', 'user', $user, $reason, $user_id ) ) {
			$this->dieUsageMsg( array( 'checkuserlogfail' ) );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$time_conds = $this->getTimeConds( $period );

		# Ordering by the latest timestamp makes a small filesort on the IP list

		$ret = $dbr->select(
			'cu_changes',
			array( 'cuc_ip', 'cuc_ip_hex', 'MIN(cuc_timestamp) AS first', 'MAX(cuc_timestamp) AS last' ),
			array( 'cuc_user' => $user_id, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'last DESC',
				'GROUP BY' => 'cuc_ip,cuc_ip_hex',
				'LIMIT' => 5001,
				'USE INDEX' => 'cuc_user_ip_time',
			)
		);

		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $user ) . "\n";
		} else {
			$blockip = SpecialPage::getTitleFor( 'blockip' );
			$ips_edits = array();
			$counter = 0;
			foreach ( $ret as $row ) {
				if ( $counter >= 5000 ) {
					break;
				}
				$ips_edits[$row->cuc_ip] = $row->count;
				$ips_first[$row->cuc_ip] = $row->first;
				$ips_last[$row->cuc_ip] = $row->last;
				$ips_hex[$row->cuc_ip] = $row->cuc_ip_hex;
				++$counter;
			}
			// Count pinging might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 60 );
			wfRestoreWarnings();

			$s = array();
			foreach ( $ips_edits as $ip => $edits ) {
				$block = new Block();
				$block->fromMaster( false ); // use slaves
				$timestart = wfTimestamp( TS_MW, $ips_first[$ip] );
				$timeend = wfTimestamp( TS_MW, $ips_last[$ip] );
				$item = array(
					'ip' => $ip,
					'edits' => $edits,
					'timestart' => $timestart,
					'timeend' => $timeend
				);
				if ( $block->load( $ip, 0 ) ) {
					$item['blocked'] = '';
				}
				$s[] = $item;
			}
		}

		return $s;
	}

	function doIPUsersRequest( $ip, $xfor = false, $reason = '', $period = 0 ) {
		global $wgUser, $wgLang, $wgTitle;

		# Invalid IPs are passed in as a blank string
		if ( !$ip ) {
			$this->dieUsageMsg( array( 'badipaddress' ) );
		}

		$logType = 'ipusers';
		if ( $xfor ) {
			$logType .= '-xff';
		}
		# Log the check...
		if ( !$this->addLogEntry( $logType, 'ip', $ip, $reason ) ) {
			$this->dieUsageMsg( array( 'checkuserlogfail' ) );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$ip_conds = $dbr->makeList( $this->getIpConds( $dbr, $ip, $xfor ), LIST_AND );
		$time_conds = $this->getTimeConds( $period );
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		# Ordered in descent by timestamp. Can cause large filesorts on range scans.
		# Check how many rows will need sorting ahead of time to see if this is too big.
		if ( strpos( $ip, '/' ) !== false ) {
			# Quick index check only OK if no time constraint
			if ( $period ) {
				$rangecount = $dbr->selectField( 'cu_changes', 'COUNT(*)',
					array( $ip_conds, $time_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			} else {
				$rangecount = $dbr->estimateRowCount( 'cu_changes', '*',
					array( $ip_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			}
			// Sorting might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 120 );
			wfRestoreWarnings();
		}
		$counter = 0;
		$over_users = array();
		# See what is best to do after testing the waters...
		if ( isset( $rangecount ) && $rangecount > 10000 ) {
			$ret = $dbr->select( 'cu_changes',
				array( 'cuc_ip_hex', 'COUNT(*) AS count', 'MIN(cuc_timestamp) AS first', 'MAX(cuc_timestamp) AS last' ),
				array( $ip_conds, $time_conds ),
				__METHOD___,
				array(
					'GROUP BY' => 'cuc_ip_hex',
					'ORDER BY' => 'cuc_ip_hex',
					'LIMIT' => 5001,
					'USE INDEX' => $index,
				)
			);
			foreach( $ret as $row ) {
				if ( $counter >= 10000 ) {
					break;
				}
				# Convert the IP hexes into normal form
				if ( strpos( $row->cuc_ip_hex, 'v6-' ) !== false ) {
					$ip = substr( $row->cuc_ip_hex, 3 );
					$ip = IP::HextoOctet( $ip );
				} else {
					$ip = long2ip( wfBaseConvert( $row->cuc_ip_hex, 16, 10, 8 ) );
				}

				$over_users[] = array(
					'ip' => $ip,
					'edits' => $row->count,
					'timestart' => wfTimestamp( TS_MW, $row->first ),
					'timeend' => wfTimestamp( TS_MW, $row->last )
				);
				++$counter;
			}

			$this->getResult()->setIndexedTagName( $res, 'ou' );
			$this->getResult()->addValue( null, 'overips', $over_users );

			return;
		} else if ( isset( $rangecount ) && !$rangecount ) {
			$s = $this->noMatchesMessage( $ip );
			if ( !$s ) {
				$this->dieUsageMsg( array( 'nomatch' ) );
			} else {
				$this->dieUsageMsg( array( 'nomatchedit', $s[0], $s[1] ) );
			}
			return;
		}

		global $wgMemc;
		# OK, do the real query...

		$ret = $dbr->select(
			'cu_changes',
			array(
				'cuc_user_text', 'cuc_timestamp', 'cuc_user', 'cuc_ip', 'cuc_agent', 'cuc_xff'
			),
			array( $ip_conds, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => 10000,
				'USE INDEX' => $index,
			)
		);

		$users_first = $users_last = $users_edits = $users_ids = array();
		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $ip ) . "\n";
		} else {
			global $wgAuth;
			foreach ( $ret as $row ) {
				if ( !array_key_exists( $row->cuc_user_text, $users_edits ) ) {
					$users_last[$row->cuc_user_text] = $row->cuc_timestamp;
					$users_edits[$row->cuc_user_text] = 0;
					$users_ids[$row->cuc_user_text] = $row->cuc_user;
					$users_infosets[$row->cuc_user_text] = array();
					$users_agentsets[$row->cuc_user_text] = array();
				}
				$users_edits[$row->cuc_user_text] += 1;
				$users_first[$row->cuc_user_text] = $row->cuc_timestamp;
				# Treat blank or NULL xffs as empty strings
				$xff = empty( $row->cuc_xff ) ? null : $row->cuc_xff;
				$xff_ip_combo = array( $row->cuc_ip, $xff );
				# Add this IP/XFF combo for this username if it's not already there

				if ( !in_array( $xff_ip_combo, $users_infosets[$row->cuc_user_text] ) ) {
					$users_infosets[$row->cuc_user_text][] = $xff_ip_combo;
				}
				# Add this agent string if it's not already there; 10 max.
				if ( count( $users_agentsets[$row->cuc_user_text] ) < 10 ) {
					if ( !in_array( $row->cuc_agent, $users_agentsets[$row->cuc_user_text] ) ) {
						$users_agentsets[$row->cuc_user_text][] = $row->cuc_agent;
					}
				}
			}

			$s = array();
			foreach ( $users_edits as $name => $count ) {
				# Load user object
				$user = User::newFromName( $name, false );
				$s[$name] = array(
					'user' => $name,
					'edits' => $count,
					'timestart' => wfTimestamp( TS_MW, $users_first[$name] ),
					'timeend' => wfTimestamp( TS_MW, $users_last[$name] )
				);

				# Check if this user or IP is blocked. If so, give a link to the block log...
				$block = new Block();
				$block->fromMaster( false ); // use slaves
				$ip = IP::isIPAddress( $name ) ? $name : '';
				$flags = array();
				if ( $block->load( $ip, $users_ids[$name] ) ) {
					// Range blocked?
					if ( IP::isIPAddress( $block->mAddress ) && strpos( $block->mAddress, '/' ) ) {
						$s[$name]['rangeblock'] = '';
					// Auto blocked?
					} else if ( $block->mAuto ) {
						$s[$name]['autoblock'] = '';
					} else {
						$s[$name]['blocked'] = '';
					}
				// IP that is blocked on all wikis?
				} else if ( $ip === $name && $user->isBlockedGlobally( $ip ) ) {
					$s[$name]['globalblock'] = '';
				} else if ( self::userWasBlocked( $name ) ) {
					$s[$name]['pastblocked'] = '';
				}
				# Show if account is local only
				$authUser = $wgAuth->getUserInstance( $user );
				if ( $user->getId() && $authUser->getId() === 0 ) {
					$s[$name]['local'] = 'yes';
				} else {
					$s[$name]['local'] = 'no';
				}
				# Check for extra user rights...
				if ( $users_ids[$name] ) {
					$user = User::newFromId( $users_ids[$name] );
					if ( $user->isLocked() ) {
						$s[$name]['locked'] = '';
					}
					if ( $user->getGroups() ) {
						$s[$name]['groups'] = $user->getGroups();
					}
				}
				# Check how many accounts the user made recently?
				if ( $ip ) {
					$key = wfMemcKey( 'acctcreate', 'ip', $ip );
					$count = intval( $wgMemc->get( $key ) );
					if ( $count ) {
						$s[$name]['acctcreate'] = wfMsgExt( 'checkuser-accounts', array( 'parsemag' ), $count );
					}
				}
				# List out each IP/XFF combo for this username
				for ( $i = ( count( $users_infosets[$name] ) - 1 ); $i >= 0; $i-- ) {
					$set = $users_infosets[$name][$i];

					$s[$name][$i]['ip'] = $set[0];

					# XFF string, link to /xff search
					if ( $set[1] ) {
						$s[$name][$i]['xff'] = '';
						# Flag our trusted proxies
						list( $client, $trusted ) = efGetClientIPfromXFF( $set[1], $set[0] );

						if ( $trusted ) {
							$s[$name][$i]['trusted'] = yes;
						}
						else {
							$s[$name][$i]['trusted'] = no;
						}
						$s[$name][$i]['client'] = $client;
					}
				}
				# List out each agent for this username
				for ( $i = ( count( $users_agentsets[$name] ) - 1 ); $i >= 0; $i-- ) {
					$agent = $users_agentsets[$name][$i];
					$s[$name][$i]['agent'] = $agent;
				}
			}
		}

		return $s;
	}

	function doIPEditsRequest( $ip, $xfor = false, $reason = '', $period = 0 ) {
		global $wgUser, $wgLang, $wgTitle;

		# Invalid IPs are passed in as a blank string
		if ( !$ip ) {
			$this->dieUsageMsg( array( 'badipaddress' ) );
			return;
		}

		$logType = 'ipedits';
		if ( $xfor ) {
			$logType .= '-xff';
		}
		# Record check...
		if ( !$this->addLogEntry( $logType, 'ip', $ip, $reason ) ) {
			$this->dieUsageMsg( array( 'checkuserlogfail' ) );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$ip_conds = $dbr->makeList( $this->getIpConds( $dbr, $ip, $xfor ), LIST_AND );
		$time_conds = $this->getTimeConds( $period );
		$cu_changes = $dbr->tableName( 'cu_changes' );
		# Ordered in descent by timestamp. Can cause large filesorts on range scans.
		# Check how many rows will need sorting ahead of time to see if this is too big.
		# Also, if we only show 5000, too many will be ignored as well.
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		if ( strpos( $ip, '/' ) !== false ) {
			# Quick index check only OK if no time constraint
			if ( $period ) {
				$rangecount = $dbr->selectField( 'cu_changes', 'COUNT(*)',
					array( $ip_conds, $time_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			} else {
				$rangecount = $dbr->estimateRowCount( 'cu_changes', '*',
					array( $ip_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			}
			// Sorting might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 60 );
			wfRestoreWarnings();
		}
		$counter = 0;
		$over_ips = array();
		# See what is best to do after testing the waters...
		if ( isset( $rangecount ) && $rangecount > 5000 ) {
			$ret = $dbr->select( 'cu_changes',
				array( 'cuc_ip_hex', 'COUNT(*) AS count', 'MIN(cuc_timestamp) AS first', 'MAX(cuc_timestamp) AS last' ),
				array( $ip_conds, $time_conds ),
				__METHOD___,
				array(
					'GROUP BY' => 'cuc_ip_hex',
					'ORDER BY' => 'cuc_ip_hex',
					'LIMIT' => 5001,
					'USE INDEX' => $index,
				)
			);
			foreach( $ret as $row ) {
				if ( $counter >= 5000 ) {
					break;
				}
				# Convert the IP hexes into normal form
				if ( strpos( $row->cuc_ip_hex, 'v6-' ) !== false ) {
					$ip = substr( $row->cuc_ip_hex, 3 );
					$ip = IP::HextoOctet( $ip );
				} else {
					$ip = long2ip( wfBaseConvert( $row->cuc_ip_hex, 16, 10, 8 ) );
				}

				$over_ips[] = array(
					'ip' => $ip,
					'edits' => $row->count,
					'timestart' => wfTimestamp( TS_MW, $row->first ),
					'timeend' => wfTimestamp( TS_MW, $row->last )
				);
				++$counter;
			}

			$this->getResult()->setIndexedTagName( $res, 'oi' );
			$this->getResult()->addValue( null, 'overips', $over_ips );

			return;
		} else if ( isset( $rangecount ) && !$rangecount ) {
			$s = $this->noMatchesMessage( $ip );
			if ( !$s ) {
				$this->dieUsageMsg( array( 'nomatch' ) );
			} else {
				$this->dieUsageMsg( array( 'nomatchedit', $s[0], $s[1] ) );
			}
			return;
		}
		# OK, do the real query...
		$ret = $dbr->select(
			'cu_changes',
			array(
				'cuc_namespace','cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment', 'cuc_actiontext',
				'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type', 'cuc_this_oldid',
				'cuc_last_oldid', 'cuc_ip', 'cuc_xff','cuc_agent'
			),
			array( $ip_conds, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => 5001,
				'USE INDEX' => $index,
			)
		);

		if ( !$dbr->numRows( $ret ) ) {
			$s = null;
		} else {
			$s = array();
			foreach( $ret as $row ) {
				if ( $counter >= 5000 ) {
					break;
				}
				$s[] = array(
					'ip' => $row->cuc_ip,
					'edits' => $row->count,
					'timestart' => wfTimestamp( TS_MW, $row->first ),
					'timeend' => wfTimestamp( TS_MW, $row->last )
				);

				++$counter;
			}
		}
		return $s;
	}

	function doUserEditsRequest( $user, $reason = '', $period = 0 ) {
		global $wgUser, $wgLang, $wgTitle;

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		# IPs are passed in as a blank string
		if ( !$user ) {
			$this->dieUsageMsg( array( 'nosuchusershort' ) );
		}
		# Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		# If user is not IP or nonexistent
		if ( !$user_id ) {
			$this->dieUsageMsg( array( 'nosuchusershort' ) );
		}

		# Record check...
		if ( !$this->addLogEntry( 'useredits', 'user', $user, $reason, $user_id ) ) {
			$this->dieUsageMsg( array( 'checkuserlogfail' ) );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$user_cond = "cuc_user = '$user_id'";
		$time_conds = $this->getTimeConds( $period );
		$cu_changes = $dbr->tableName( 'cu_changes' );
		# Ordered in descent by timestamp. Causes large filesorts if there are many edits.
		# Check how many rows will need sorting ahead of time to see if this is too big.
		# If it is, sort by IP,time to avoid the filesort.
		if ( $period ) {
			$count = $dbr->selectField( 'cu_changes', 'COUNT(*)',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array( 'USE INDEX' => 'cuc_user_ip_time' ) );
		} else {
			$count = $dbr->estimateRowCount( 'cu_changes', '*',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array( 'USE INDEX' => 'cuc_user_ip_time' ) );
		}
		# See what is best to do after testing the waters...
		if ( $count > 5000 ) {
			$ret = $dbr->select(
				'cu_changes',
				'*',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array(
					'ORDER BY' => 'cuc_ip ASC, cuc_timestamp DESC',
					'LIMIT' => 5000,
					'USE INDEX' => 'cuc_user_ip_time'
				)
			);

			foreach( $ret as $row ) {
				$over_ips[] = array(
					'ip' => $ip,
					'edits' => $row->count,
					'timestart' => wfTimestamp( TS_MW, $row->first ),
					'timeend' => wfTimestamp( TS_MW, $row->last )
				);
				++$counter;
			}

			$this->getResult()->setIndexedTagName( $res, 'oi' );
			$this->getResult()->addValue( null, 'overips', $over_ips );

			return;
		}
		// Sorting might take some time...make sure it is there
		wfSuppressWarnings();
		set_time_limit( 60 );
		wfRestoreWarnings();
		# OK, do the real query...
		$counter = 0;

		$ret = $dbr->select(
			'cu_changes',
			'*',
			array( $user_cond, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_ip ASC, cuc_timestamp DESC',
				'LIMIT' => 5000,
				'USE INDEX' => 'cuc_user_ip_time'
			)
		);
		if ( !$dbr->numRows( $ret ) ) {
			$s = null;
		} else {
			$s = array();
			foreach( $ret as $row ) {
				if ( $counter >= 5000 ) {
					break;
				}
				$s[] = array(
					'ip' => $row->cuc_ip,
					'edits' => $row->count,
					'timestart' => wfTimestamp( TS_MW, $row->first ),
					'timeend' => wfTimestamp( TS_MW, $row->last )
				);

				++$counter;
			}
		}
		return $s;
	}

	function getTimeConds( $period ) {
		if ( !$period ) {
			return "1 = 1";
		}
		$dbr = wfGetDB( DB_SLAVE );
		$cutoff_unixtime = time() - ( $period * 24 * 3600 );
		$cutoff_unixtime = $cutoff_unixtime - ( $cutoff_unixtime % 86400 );
		$cutoff = $dbr->addQuotes( $dbr->timestamp( $cutoff_unixtime ) );
		return "cuc_timestamp > $cutoff";
	}

	function addLogEntry( $logType, $targetType, $target, $reason, $targetID = 0 ) {
		global $wgUser;

		if ( $targetType == 'ip' ) {
			list( $rangeStart, $rangeEnd ) = IP::parseRange( $target );
			$targetHex = $rangeStart;
			if ( $rangeStart == $rangeEnd ) {
				$rangeStart = $rangeEnd = '';
			}
		} else {
			$targetHex = $rangeStart = $rangeEnd = '';
		}

		$dbw = wfGetDB( DB_MASTER );
		$cul_id = $dbw->nextSequenceValue( 'cu_log_cul_id_seq' );
		$dbw->insert( 'cu_log',
			array(
				'cul_id' => $cul_id,
				'cul_timestamp' => $dbw->timestamp(),
				'cul_user' => $wgUser->getID(),
				'cul_user_text' => $wgUser->getName(),
				'cul_reason' => $reason,
				'cul_type' => $logType,
				'cul_target_id' => $targetID,
				'cul_target_text' => $target,
				'cul_target_hex' => $targetHex,
				'cul_range_start' => $rangeStart,
				'cul_range_end' => $rangeEnd,
			), __METHOD__ );
		return true;
	}

	protected function getIpConds( $db, $ip, $xfor = false ) {
		$type = ( $xfor ) ? 'xff' : 'ip';
		// IPv4 CIDR, 16-32 bits
		if ( preg_match( '#^(\d+\.\d+\.\d+\.\d+)/(\d+)$#', $ip, $matches ) ) {
			if ( $matches[2] < 16 || $matches[2] > 32 ) {
				return array( 'cuc_' . $type . '_hex' => -1 );
			}
			list( $start, $end ) = IP::parseRange( $ip );
			return array( 'cuc_' . $type . '_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} else if ( preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}/(\d+)$#', $ip, $matches ) ) {
			// IPv6 CIDR, 64-128 bits
			if ( $matches[1] < 64 || $matches[1] > 128 ) {
				return array( 'cuc_' . $type . '_hex' => -1 );
			}

			list( $start, $end ) = IP::parseRange( $ip );
			return array( 'cuc_' . $type . '_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} else if ( preg_match( '#^(\d+)\.(\d+)\.(\d+)\.(\d+)$#', $ip ) ) {
			// 32 bit IPv4
			$ip_hex = IP::toHex( $ip );
			return array( 'cuc_' . $type . '_hex' => $ip_hex );
		} else if ( preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}$#', $ip ) ) {
			// 128 bit IPv6
			$ip_hex = IP::toHex( $ip );
			return array( 'cuc_' . $type . '_hex' => $ip_hex );
		} else {
			// throw away this query, incomplete IP, these don't get through the entry point anyway
			return array( 'cuc_' . $type . '_hex' => -1 );
		}
	}

	function noMatchesMessage( $userName ) {
		global $wgLang;
		$dbr = wfGetDB( DB_SLAVE );
		$user_id = User::idFromName( $userName );
		if ( $user_id ) {
			$revEdit = $dbr->selectField( 'revision',
				'rev_timestamp',
				array( 'rev_user' => $user_id ),
				__METHOD__,
				array( 'ORDER BY' => 'rev_timestamp DESC' )
			);
		} else {
			$revEdit = $dbr->selectField( 'revision',
				'rev_timestamp',
				array( 'rev_user_text' => $userName ),
				__METHOD__,
				array( 'ORDER BY' => 'rev_timestamp DESC' )
			);
		}
		$logEdit = 0;
		if ( $user_id ) {
			$logEdit = $dbr->selectField( 'logging',
				'log_timestamp',
				array( 'log_user' => $user_id ),
				__METHOD__,
				array( 'ORDER BY' => 'log_timestamp DESC' )
			);
		}
		$lastEdit = max( $revEdit, $logEdit );
		if ( $lastEdit ) {
			$lastEditDate = wfTimestamp( TS_MW, $lastEdit );
			$lastEditTime = wfTimestamp( TS_MW, $lastEdit );
			return array( $lastEditDate, $lastEditTime );
		}
		return null;
	}

	static function userWasBlocked( $name ) {
		$userpage = Title::makeTitle( NS_USER, $name );
		return wfGetDB( DB_SLAVE )->selectField( 'logging', '1',
			array( 'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => $userpage->getNamespace(),
				'log_title' => $userpage->getDBKey() ),
			__METHOD__,
			array( 'USE INDEX' => 'page_time' ) );
	}

	public function mustBePosted() {
		return true;
	}

	public function getAllowedParams() {
		return array (
			'user' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'type' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'duration' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'reason' => null,
		);
	}

	public function getParamDescription() {
		return array (
			'user' => 'The user (or IP) you want to check',
			'type' => 'The type of check you want to make (subuserips, subipedits, subipusers, or subuseredits)',
			'duration' => 'How far back you want to check',
			'reason' => 'The reason for checking',
		);
	}

	public function getDescription() {
		return array (
			'Run a CheckUser on a username or IP address'
		);
	}

	protected function getExamples() {
		return array(
			'api.php?action=checkuser&user=127.0.0.1/xff&type=subipedits&duration=all',
			'api.php?action=checkuser&user=Example&type=subuserips&duration=2_weeks',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
