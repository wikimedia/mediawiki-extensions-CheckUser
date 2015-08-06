<?php
class CheckUserHooks {
	/**
	 * Hook function for RecentChange_save
	 * Saves user data into the cu_changes table
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry
	 */
	public static function updateCheckUserData( RecentChange $rc ) {
		global $wgRequest;

		$attribs = $rc->getAttributes();
		// Get IP
		$ip = $wgRequest->getIP();
		// Get XFF header
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		// Get agent
		$agent = $wgRequest->getHeader( 'User-Agent' );
		// Store the log action text for log events
		// $rc_comment should just be the log_comment
		// BC: check if log_type and log_action exists
		// If not, then $rc_comment is the actiontext and comment
		if ( isset( $attribs['rc_log_type'] ) && $attribs['rc_type'] == RC_LOG ) {
			$target = Title::makeTitle( $attribs['rc_namespace'], $attribs['rc_title'] );
			$context = RequestContext::newExtraneousContext( $target );

			$formatter = LogFormatter::newFromRow( $rc->getAttributes() );
			$formatter->setContext( $context );
			$actionText = $formatter->getPlainActionText();
		} else {
			$actionText = '';
		}

		$dbw = wfGetDB( DB_MASTER );
		$cuc_id = $dbw->nextSequenceValue( 'cu_changes_cu_id_seq' );
		$rcRow = array(
			'cuc_id'         => $cuc_id,
			'cuc_namespace'  => $attribs['rc_namespace'],
			'cuc_title'      => $attribs['rc_title'],
			'cuc_minor'      => $attribs['rc_minor'],
			'cuc_user'       => $attribs['rc_user'],
			'cuc_user_text'  => $attribs['rc_user_text'],
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => $attribs['rc_comment'],
			'cuc_this_oldid' => $attribs['rc_this_oldid'],
			'cuc_last_oldid' => $attribs['rc_last_oldid'],
			'cuc_type'       => $attribs['rc_type'],
			'cuc_timestamp'  => $attribs['rc_timestamp'],
			'cuc_ip'         => IP::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IP::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IP::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		);
		# On PG, MW unsets cur_id due to schema incompatibilites. So it may not be set!
		if ( isset( $attribs['rc_cur_id'] ) ) {
			$rcRow['cuc_page_id'] = $attribs['rc_cur_id'];
		}

		Hooks::run( 'CheckUserInsertForRecentChange', array( $rc, &$rcRow ) );
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to store password reset
	 * Saves user data into the cu_changes table
	 */
	public static function updateCUPasswordResetData( User $user, $ip, $account ) {
		global $wgRequest;

		// Get XFF header
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		// Get agent
		$agent = $wgRequest->getHeader( 'User-Agent' );
		$dbw = wfGetDB( DB_MASTER );
		$cuc_id = $dbw->nextSequenceValue( 'cu_changes_cu_id_seq' );
		$rcRow = array(
			'cuc_id'         => $cuc_id,
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $user->getId(),
			'cuc_user_text'  => $user->getName(),
			'cuc_actiontext' => wfMessage( 'checkuser-reset-action', $account->getName() )->inContentLanguage()->text(),
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IP::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IP::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IP::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		);
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to store email data
	 * Saves user data into the cu_changes table
	 */
	public static function updateCUEmailData( $to, $from, $subject, $text ) {
		global $wgSecretKey, $wgRequest, $wgCUPublicKey;
		if ( !$wgSecretKey || $from->name == $to->name ) {
			return true;
		}
		$userFrom = User::newFromName( $from->name );
		$userTo = User::newFromName( $to->name );
		$hash = md5( $userTo->getEmail() . $userTo->getId() . $wgSecretKey );
		// Get IP
		$ip = $wgRequest->getIP();
		// Get XFF header
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		// Get agent
		$agent = $wgRequest->getHeader( 'User-Agent' );
		$dbw = wfGetDB( DB_MASTER );
		$cuc_id = $dbw->nextSequenceValue( 'cu_changes_cu_id_seq' );
		$rcRow = array(
			'cuc_id'         => $cuc_id,
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $userFrom->getId(),
			'cuc_user_text'  => $userFrom->getName(),
			'cuc_actiontext' => wfMessage( 'checkuser-email-action', $hash )->inContentLanguage()->text(),
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IP::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IP::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IP::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		);
		if ( trim( $wgCUPublicKey ) != '' ) {
			$privateData = $userTo->getEmail() . ":" . $userTo->getId();
			$encryptedData = new CheckUserEncryptedData( $privateData, $wgCUPublicKey );
			$rcRow = array_merge($rcRow, array( 'cuc_private' => serialize( $encryptedData ) ) );
		}

		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to store autocreation data from the auth plugin
	 * Saves user data into the cu_changes table
	 *
	 * @param $user User
	 *
	 * @return true
	 */
	public static function onAuthPluginAutoCreate( User $user ) {
		return self::logUserAccountCreation( $user, 'checkuser-autocreate-action' );
	}

	/**
	 * Hook function to store registration data
	 * Saves user data into the cu_changes table
	 *
	 * @param $user User
	 * @param $byEmail bool
	 * @return bool
	 */
	public static function onAddNewAccount( User $user, $byEmail ) {
		return self::logUserAccountCreation( $user, 'checkuser-create-action' );
	}

	/**
	 * @param $user User
	 * @param $actiontext string
	 * @return bool
	 */
	protected static function logUserAccountCreation( User $user, $actiontext ) {
		global $wgRequest;

		// Get IP
		$ip = $wgRequest->getIP();
		// Get XFF header
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		// Get agent
		$agent = $wgRequest->getHeader( 'User-Agent' );
		$dbw = wfGetDB( DB_MASTER );
		$cuc_id = $dbw->nextSequenceValue( 'cu_changes_cu_id_seq' );
		$rcRow = array(
			'cuc_id'         => $cuc_id,
			'cuc_page_id'    => 0,
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $user->getId(),
			'cuc_user_text'  => $user->getName(),
			'cuc_actiontext' => wfMessage( $actiontext )->inContentLanguage()->text(),
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IP::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IP::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IP::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		);
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to prune data from the cu_changes table
	 */
	public static function maybePruneIPData() {
		# Every 50th edit, prune the checkuser changes table.
		if ( 0 == mt_rand( 0, 49 ) ) {
			$fname = __METHOD__;
			DeferredUpdates::addCallableUpdate( function() use ( $fname ) {
				global $wgCUDMaxAge;

				$dbw = wfGetDB( DB_MASTER );
				$encCutoff = $dbw->addQuotes( $dbw->timestamp( time() - $wgCUDMaxAge ) );
				$ids = $dbw->selectFieldValues( 'cu_changes',
					'cuc_id',
					array( "cuc_timestamp < $encCutoff" ),
					$fname,
					array( 'LIMIT' => 500 )
				);

				if ( $ids ) {
					$dbw->delete( 'cu_changes', array( 'cuc_id' => $ids ), $fname );
					$dbw->commit( 'flush' );
				}
			} );
		}

		return true;
	}

	/**
	 * Locates the client IP within a given XFF string.
	 * Unlike the XFF checking to determine a user IP in WebRequest,
	 * this simply follows the chain and does not account for server trust.
	 *
	 * This returns an array containing:
	 *   - The best guess of the client IP
	 *   - Whether all the proxies are just squid/varnish
	 *
	 * @param string $xff XFF header value
	 * @return array (string|null, bool)
	 * @TODO: move this to a utility class
	 */
	public static function getClientIPfromXFF( $xff ) {
		global $wgUsePrivateIPs;

		if ( !strlen( $xff ) ) {
			return array( null, false );
		}

		# Get the list in the form of <PROXY N, ... PROXY 1, CLIENT>
		$ipchain = array_map( 'trim', explode( ',', $xff ) );
		$ipchain = array_reverse( $ipchain );

		$client = null; // best guess of the client IP
		$isSquidOnly = false; // all proxy servers where site Squid/Varnish servers?
		# Step through XFF list and find the last address in the list which is a
		# sensible proxy server. Set $ip to the IP address given by that proxy server,
		# unless the address is not sensible (e.g. private). However, prefer private
		# IP addresses over proxy servers controlled by this site (more sensible).
		foreach ( $ipchain as $i => $curIP ) {
			$curIP = IP::canonicalize( $curIP );
			if ( $curIP === null ) {
				break; // not a valid IP address
			}
			$curIsSquid = IP::isConfiguredProxy( $curIP );
			if ( $client === null ) {
				$client = $curIP;
				$isSquidOnly = $curIsSquid;
			}
			if (
				isset( $ipchain[$i + 1] ) &&
				IP::isIPAddress( $ipchain[$i + 1] ) &&
				(
					IP::isPublic( $ipchain[$i + 1] ) ||
					$wgUsePrivateIPs ||
					$curIsSquid // bug 48919
				)
			) {
				$client = IP::canonicalize( $ipchain[$i + 1] );
				$isSquidOnly = ( $isSquidOnly && $curIsSquid );
				continue;
			}
			break;
		}

		return array( $client, $isSquidOnly );
	}

	public static function checkUserSchemaUpdates( DatabaseUpdater $updater ) {
		$base = dirname( __FILE__ );

		$updater->addExtensionUpdate( array( 'CheckUserHooks::checkUserCreateTables' ) );
		if ( $updater->getDB()->getType() == 'mysql' ) {
			$updater->addExtensionUpdate( array( 'addIndex', 'cu_changes',
				'cuc_ip_hex_time', "$base/archives/patch-cu_changes_indexes.sql", true ) );
			$updater->addExtensionUpdate( array( 'addIndex', 'cu_changes',
				'cuc_user_ip_time', "$base/archives/patch-cu_changes_indexes2.sql", true ) );
			$updater->addExtensionField( 'cu_changes', 'cuc_private', "$base/archives/patch-cu_changes_privatedata.sql" );
		} elseif ( $updater->getDB()->getType() == 'postgres' ) {
			$updater->addExtensionUpdate( array( 'addPgField', 'cu_changes', 'cuc_private', 'BYTEA' ) );
		}

		return true;
	}

	public static function checkUserCreateTables( $updater ) {
		$base = dirname( __FILE__ );

        $db = $updater->getDB();
		if ( $db->tableExists( 'cu_changes' ) ) {
			$updater->output( "...cu_changes table already exists.\n" );
		} else {
			require_once "$base/install.inc";
			create_cu_changes( $db );
		}

		if ( $db->tableExists( 'cu_log' ) ) {
			$updater->output( "...cu_log table already exists.\n" );
		} else {
			require_once "$base/install.inc";
			create_cu_log( $db );
		}
	}

	/**
	 * Tell the parser test engine to create a stub cu_changes table,
	 * or temporary pages won't save correctly during the test run.
	 */
	public static function checkUserParserTestTables( &$tables ) {
		$tables[] = 'cu_changes';
		return true;
	}

	/**
	 * Add a link to Special:CheckUser and Special:CheckUserLog
	 * on Special:Contributions/<username> for
	 * privileged users.
	 * @param $id Integer: user ID
	 * @param $nt Title: user page title
	 * @param $links Array: tool links
	 * @return true
	 */
	public static function checkUserContributionsLinks( $id, $nt, &$links ) {
		global $wgUser;
		if ( $wgUser->isAllowed( 'checkuser' ) ) {
			$links[] = Linker::linkKnown(
				SpecialPage::getTitleFor( 'CheckUser' ),
				wfMessage( 'checkuser-contribs' )->escaped(),
				array(),
				array( 'user' => $nt->getText() )
			);
		}
		if ( $wgUser->isAllowed( 'checkuser-log' ) ) {
			$links[] = Linker::linkKnown(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				wfMessage( 'checkuser-contribs-log' )->escaped(),
				array(),
				array(
					'cuSearchType' => 'target',
					'cuSearch' => $nt->getText()
				)
			);
		}
		return true;
	}

	/**
	 * Retroactively autoblocks the last IP used by the user (if it is a user)
	 * blocked by this Block.
	 *
	 * @param Block $block
	 * @param Array &$blockIds
	 */
	public static function doRetroactiveAutoblock( Block $block, array &$blockIds ) {
		$dbr = wfGetDB( DB_SLAVE );

		$user = User::newFromName( (string)$block->getTarget(), false );
		if ( !$user->getId() ) {
			return array(); // user in an IP?
		}

		$options = array( 'ORDER BY' => 'cuc_timestamp DESC' );
		$options['LIMIT'] = 1; // just the last IP used

		$res = $dbr->select( 'cu_changes',
			array( 'cuc_ip' ),
			array( 'cuc_user' => $user->getId() ),
			__METHOD__ ,
			$options
		);

		# Iterate through IPs used (this is just one or zero for now)
		foreach ( $res as $row ) {
			if ( $row->cuc_ip ) {
				$id = $block->doAutoblock( $row->cuc_ip );
				if ( $id ) $blockIds[] = $id;
			}
		}

		return false; // autoblock handled
	}

	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = array( 'cu_changes', 'cuc_user', 'cuc_user_text' );
		$updateFields[] = array( 'cu_log', 'cul_user', 'cul_user_text' );
		$updateFields[] = array( 'cu_log', 'cul_target_id' );

		return true;
	}
}
