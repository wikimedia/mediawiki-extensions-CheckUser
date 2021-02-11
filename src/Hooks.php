<?php

namespace MediaWiki\CheckUser;

use AutoCommitUpdate;
use DatabaseUpdater;
use DeferredUpdates;
use LogFormatter;
use MailAddress;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\Specials\SpecialInvestigate;
use MediaWiki\CheckUser\Specials\SpecialInvestigateBlock;
use MediaWiki\MediaWikiServices;
use PopulateCheckUserTable;
use RecentChange;
use RenameuserSQL;
use RequestContext;
use SpecialPage;
use Title;
use User;
use WebRequest;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\ScopedCallback;

class Hooks {

	/**
	 * The maximum number of bytes that fit in CheckUser's text fields
	 * (cuc_agent,cuc_actiontext,cuc_comment,cuc_xff)
	 */
	private const TEXT_FIELD_LENGTH = 255;

	/**
	 * @param array &$list
	 * @return bool
	 */
	public static function onSpecialPage_initList( &$list ) {
		global $wgCheckUserEnableSpecialInvestigate;

		if ( $wgCheckUserEnableSpecialInvestigate ) {
			$list['Investigate'] = [
				'class' => SpecialInvestigate::class,
				'services' => [
					'LinkRenderer',
					'ContentLanguage',
					'UserOptionsManager',
					'CheckUserPreliminaryCheckPagerFactory',
					'CheckUserComparePagerFactory',
					'CheckUserTimelinePagerFactory',
					'CheckUserTokenQueryManager',
					'CheckUserDurationManager',
					'CheckUserEventLogger',
					'CheckUserGuidedTourLauncher',
					'CheckUserHookRunner',
				],
			];

			$list['InvestigateBlock'] = [
				'class' => SpecialInvestigateBlock::class,
				'services' => [
					'BlockUserFactory',
					'BlockPermissionCheckerFactory',
					'PermissionManager',
					'TitleFormatter',
					'UserFactory',
					'CheckUserEventLogger',
				]
			];
		}

		return true;
	}

	/**
	 * Hook function for RecentChange_save
	 * Saves user data into the cu_changes table
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry
	 * @param RecentChange $rc
	 * @return bool
	 */
	public static function updateCheckUserData( RecentChange $rc ) {
		global $wgRequest, $wgCheckUserLogAdditionalRights;

		/**
		 * RC_CATEGORIZE recent changes are generally triggered by other edits.
		 * Thus there is no reason to store checkuser data about them.
		 * @see https://phabricator.wikimedia.org/T125209
		 */
		if ( $rc->getAttribute( 'rc_type' ) == RC_CATEGORIZE ) {
			return true;
		}
		/**
		 * RC_EXTERNAL recent changes are not triggered by actions on the local wiki.
		 * Thus there is no reason to store checkuser data about them.
		 * @see https://phabricator.wikimedia.org/T125664
		 */
		if ( $rc->getAttribute( 'rc_type' ) == RC_EXTERNAL ) {
			return true;
		}

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
			$pm = MediaWikiServices::getInstance()->getPermissionManager();
			$target = Title::makeTitle( $attribs['rc_namespace'], $attribs['rc_title'] );
			$context = RequestContext::newExtraneousContext( $target );

			$scope = $pm->addTemporaryUserRights( $context->getUser(), $wgCheckUserLogAdditionalRights );

			$formatter = LogFormatter::newFromRow( $rc->getAttributes() );
			$formatter->setContext( $context );
			$actionText = $formatter->getPlainActionText();

			ScopedCallback::consume( $scope );
		} else {
			$actionText = '';
		}

		$comment = $rc->getAttribute( 'rc_comment' );

		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$actionText = $contLang->truncateForDatabase( $actionText, self::TEXT_FIELD_LENGTH );
		$agent = $contLang->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
		$xff = $contLang->truncateForDatabase( $xff, self::TEXT_FIELD_LENGTH );
		$comment = $contLang->truncateForDatabase( $comment, self::TEXT_FIELD_LENGTH );

		$rcRow = [
			'cuc_namespace'  => $attribs['rc_namespace'],
			'cuc_title'      => $attribs['rc_title'],
			'cuc_minor'      => $attribs['rc_minor'],
			'cuc_user'       => $attribs['rc_user'],
			'cuc_user_text'  => $attribs['rc_user_text'],
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => $comment,
			'cuc_this_oldid' => $attribs['rc_this_oldid'],
			'cuc_last_oldid' => $attribs['rc_last_oldid'],
			'cuc_type'       => $attribs['rc_type'],
			'cuc_timestamp'  => $attribs['rc_timestamp'],
			'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		];
		# On PG, MW unsets cur_id due to schema incompatibilites. So it may not be set!
		if ( isset( $attribs['rc_cur_id'] ) ) {
			$rcRow['cuc_page_id'] = $attribs['rc_cur_id'];
		}

		\Hooks::run( 'CheckUserInsertForRecentChange', [ $rc, &$rcRow ] );

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_MASTER );
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to store password reset
	 * Saves user data into the cu_changes table
	 *
	 * @param User $user Sender
	 * @param string $ip
	 * @param User $account Receiver
	 * @return bool
	 */
	public static function updateCUPasswordResetData( User $user, $ip, $account ) {
		global $wgRequest;

		// Get XFF header
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		// Get agent
		$agent = $wgRequest->getHeader( 'User-Agent' );

		$actionText = wfMessage( 'checkuser-reset-action', $account->getName() )
			->inContentLanguage()->text();

		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();

		// (T199323) Truncate comment fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$actionText = $contLang->truncateForDatabase( $actionText, self::TEXT_FIELD_LENGTH );
		$agent = $contLang->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
		$xff = $contLang->truncateForDatabase( $xff, self::TEXT_FIELD_LENGTH );

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_MASTER );
		$rcRow = [
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $user->getId(),
			'cuc_user_text'  => $user->getName(),
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		];
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * Hook function to store email data.
	 *
	 * Saves user data into the cu_changes table.
	 * Uses a deferred update to save the data, because emails can be sent from code paths
	 * that don't open master connections.
	 *
	 * @param MailAddress $to
	 * @param MailAddress $from
	 * @param string $subject
	 * @param string $text
	 * @return bool
	 */
	public static function updateCUEmailData( $to, $from, $subject, $text ) {
		global $wgSecretKey, $wgRequest, $wgCUPublicKey;

		if ( !$wgSecretKey || $from->name == $to->name ) {
			return true;
		} elseif ( wfReadOnly() ) {
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

		$actionText = wfMessage( 'checkuser-email-action', $hash )->inContentLanguage()->text();

		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$actionText = $contLang->truncateForDatabase( $actionText, self::TEXT_FIELD_LENGTH );
		$agent = $contLang->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
		$xff = $contLang->truncateForDatabase( $xff, self::TEXT_FIELD_LENGTH );

		$lb = $services->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );

		$rcRow = [
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $userFrom->getId(),
			'cuc_user_text'  => $userFrom->getName(),
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbr->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		];
		if ( trim( $wgCUPublicKey ) != '' ) {
			$privateData = $userTo->getEmail() . ":" . $userTo->getId();
			$encryptedData = new EncryptedData( $privateData, $wgCUPublicKey );
			$rcRow = array_merge( $rcRow, [ 'cuc_private' => serialize( $encryptedData ) ] );
		}

		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate( function () use ( $lb, $rcRow, $fname ) {
			$dbw = $lb->getConnectionRef( DB_MASTER );
			$dbw->insert( 'cu_changes', $rcRow, $fname );
		} );

		return true;
	}

	/**
	 * Hook function to store registration and autocreation data
	 * Saves user data into the cu_changes table
	 *
	 * @param User $user
	 * @param bool $autocreated
	 * @return true
	 */
	public static function onLocalUserCreated( User $user, $autocreated ) {
		return self::logUserAccountCreation(
			$user,
			$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
		);
	}

	/**
	 * @param User $user
	 * @param string $actiontext
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
		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();

		$actiontext = wfMessage( $actiontext )->inContentLanguage()->text();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$actionText = $contLang->truncateForDatabase( $actiontext, self::TEXT_FIELD_LENGTH );
		$agent = $contLang->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
		$xff = $contLang->truncateForDatabase( $xff, self::TEXT_FIELD_LENGTH );

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_MASTER );

		$rcRow = [
			'cuc_page_id'    => 0,
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $user->getId(),
			'cuc_user_text'  => $user->getName(),
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		];
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );

		return true;
	}

	/**
	 * @param AuthenticationResponse $ret
	 * @param User $user
	 * @param string $username
	 */
	public static function onAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $ret, $user, $username
	) {
		global $wgRequest, $wgCheckUserLogLogins, $wgCheckUserLogSuccessfulBotLogins;

		if ( !$wgCheckUserLogLogins ) {
			return;
		}

		if ( !$user ) {
			$user = User::newFromName( $username, 'usable' );
		}

		if ( !$user ) {
			return;
		}

		if (
			$wgCheckUserLogSuccessfulBotLogins !== true &&
			$ret->status === AuthenticationResponse::PASS
		) {
			$userGroups = MediaWikiServices::getInstance()
				->getUserGroupManager()
				->getUserGroups( $user );

			if ( in_array( 'bot', $userGroups ) ) {
				return;
			}
		}

		$ip = $wgRequest->getIP();
		$xff = $wgRequest->getHeader( 'X-Forwarded-For' );
		list( $xff_ip, $isSquidOnly ) = self::getClientIPfromXFF( $xff );
		$agent = $wgRequest->getHeader( 'User-Agent' );
		$userName = $user->getName();

		if ( $ret->status === AuthenticationResponse::FAIL ) {
			$msg = 'checkuser-login-failure';
			$cuc_user = 0;
			$cuc_user_text = $ip;
		} elseif ( $ret->status === AuthenticationResponse::PASS ) {
			$msg = 'checkuser-login-success';
			$cuc_user = $user->getId();
			$cuc_user_text = $userName;
		} else {
			// Abstain, Redirect, etc.
			return;
		}

		$target = "[[User:$userName|$userName]]";
		$actionText = wfMessage( $msg )->params( $target )->inContentLanguage()->text();

		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$actionText = $contLang->truncateForDatabase( $actionText, self::TEXT_FIELD_LENGTH );
		$agent = $contLang->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
		$xff = $contLang->truncateForDatabase( $xff, self::TEXT_FIELD_LENGTH );

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_MASTER );
		$rcRow = [
			'cuc_page_id'    => 0,
			'cuc_namespace'  => NS_USER,
			'cuc_title'      => '',
			'cuc_minor'      => 0,
			'cuc_user'       => $cuc_user,
			'cuc_user_text'  => $cuc_user_text,
			'cuc_actiontext' => $actionText,
			'cuc_comment'    => '',
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			'cuc_type'       => RC_LOG,
			'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
			'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
			'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
			'cuc_xff'        => !$isSquidOnly ? $xff : '',
			'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cuc_agent'      => $agent
		];
		$dbw->insert( 'cu_changes', $rcRow, __METHOD__ );
	}

	/**
	 * Hook function to prune data from the cu_changes table
	 */
	public static function maybePruneIPData() {
		if ( mt_rand( 0, 9 ) != 0 ) {
			return;
		}

		DeferredUpdates::addUpdate( new AutoCommitUpdate(
			wfGetDB( DB_MASTER ),
			__METHOD__,
			function ( IDatabase $dbw, $fname ) {
				global $wgCUDMaxAge;

				// per-wiki
				$key = "{$dbw->getDomainID()}:PruneCheckUserData";
				$scopedLock = $dbw->getScopedLockAndFlush( $key, $fname, 1 );
				if ( !$scopedLock ) {
					return;
				}

				$encCutoff = $dbw->addQuotes( $dbw->timestamp( time() - $wgCUDMaxAge ) );
				$ids = $dbw->selectFieldValues( 'cu_changes',
					'cuc_id',
					[ "cuc_timestamp < $encCutoff" ],
					$fname,
					[ 'LIMIT' => 500 ]
				);

				if ( $ids ) {
					$dbw->delete( 'cu_changes', [ 'cuc_id' => $ids ], $fname );
				}
			}
		) );
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
	 * @todo move this to a utility class
	 */
	public static function getClientIPfromXFF( $xff ) {
		global $wgUsePrivateIPs;

		if ( !strlen( $xff ) ) {
			return [ null, false ];
		}

		# Get the list in the form of <PROXY N, ... PROXY 1, CLIENT>
		$ipchain = array_map( 'trim', explode( ',', $xff ) );
		$ipchain = array_reverse( $ipchain );

		$proxyLookup = MediaWikiServices::getInstance()->getProxyLookup();

		// best guess of the client IP
		$client = null;

		// all proxy servers where site Squid/Varnish servers?
		$isSquidOnly = false;
		# Step through XFF list and find the last address in the list which is a
		# sensible proxy server. Set $ip to the IP address given by that proxy server,
		# unless the address is not sensible (e.g. private). However, prefer private
		# IP addresses over proxy servers controlled by this site (more sensible).
		foreach ( $ipchain as $i => $curIP ) {
			$curIP = IPUtils::canonicalize(
				WebRequest::canonicalizeIPv6LoopbackAddress( $curIP )
			);
			if ( $curIP === null ) {
				// not a valid IP address
				break;
			}
			$curIsSquid = $proxyLookup->isConfiguredProxy( $curIP );
			if ( $client === null ) {
				$client = $curIP;
				$isSquidOnly = $curIsSquid;
			}
			if (
				isset( $ipchain[$i + 1] ) &&
				IPUtils::isIPAddress( $ipchain[$i + 1] ) &&
				(
					IPUtils::isPublic( $ipchain[$i + 1] ) ||
					$wgUsePrivateIPs ||
					// T50919
					$curIsSquid
				)
			) {
				$client = IPUtils::canonicalize(
					WebRequest::canonicalizeIPv6LoopbackAddress( $ipchain[$i + 1] )
				);
				$isSquidOnly = ( $isSquidOnly && $curIsSquid );
				continue;
			}
			break;
		}

		return [ $client, $isSquidOnly ];
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$base = __DIR__ . '/..';
		$dbType = $updater->getDB()->getType();
		$isCUInstalled = $updater->tableExists( 'cu_changes' );

		$updater->addExtensionTable(
			'cu_changes', self::getTableFileName( $dbType, 'cu_changes' )
		);
		$updater->addExtensionTable(
			'cu_log', self::getTableFileName( $dbType, 'cu_log' )
		);

		if ( $dbType === 'mysql' ) {
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_ip_hex_time',
				"$base/archives/patch-cu_changes_indexes.sql"
			);
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_user_ip_time',
				"$base/archives/patch-cu_changes_indexes2.sql"
			);
			$updater->addExtensionField(
				'cu_changes',
				'cuc_private',
				"$base/archives/patch-cu_changes_privatedata.sql"
			);
		} elseif ( $dbType === 'postgres' ) {
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_changes', 'cuc_private', 'BYTEA' ]
			);
		}

		if ( !$isCUInstalled ) {
			// First time so populate cu_changes with recentchanges data.
			// Note: We cannot completely rely on updatelog here for old entries
			// as populateCheckUserTable.php doesn't check for duplicates
			$updater->addPostDatabaseUpdateMaintenance( PopulateCheckUserTable::class );
		}
	}

	/**
	 * @param string $type DB type
	 * @param string $name Table name
	 * @return string
	 */
	private static function getTableFileName( $type, $name ) {
		$file = __DIR__ . '/../' . $name;
		return $type === 'postgres'
			? $file . '.pg.sql'
			: $file . '.sql';
	}

	/**
	 * Tell the parser test engine to create a stub cu_changes table,
	 * or temporary pages won't save correctly during the test run.
	 * @param array &$tables
	 * @return bool
	 */
	public static function checkUserParserTestTables( &$tables ) {
		$tables[] = 'cu_changes';
		return true;
	}

	/**
	 * Add a link to Special:CheckUser and Special:CheckUserLog
	 * on Special:Contributions/<username> for
	 * privileged users.
	 * @param int $id User ID
	 * @param Title $nt User page title
	 * @param array &$links Tool links
	 * @param SpecialPage $sp Special page
	 */
	public static function checkUserContributionsLinks(
		$id, Title $nt, array &$links, SpecialPage $sp
	) {
		$user = $sp->getUser();
		$linkRenderer = $sp->getLinkRenderer();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( $permissionManager->userHasRight( $user, 'checkuser' ) ) {
			$links['checkuser'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$sp->msg( 'checkuser-contribs' )->text(),
				[],
				[ 'user' => $nt->getText() ]
			);
		}
		if ( $permissionManager->userHasRight( $user, 'checkuser-log' ) ) {
			$links['checkuser-log'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$sp->msg( 'checkuser-contribs-log' )->text(),
				[],
				[
					'cuSearchType' => 'target',
					'cuSearch' => $nt->getText()
				]
			);
		}
	}

	/**
	 * Retroactively autoblocks the last IP used by the user (if it is a user)
	 * blocked by this block.
	 *
	 * @param DatabaseBlock $block
	 * @param array &$blockIds
	 * @return bool
	 */
	public static function doRetroactiveAutoblock( DatabaseBlock $block, array &$blockIds ) {
		$dbr = wfGetDB( DB_REPLICA );

		$user = User::newFromName( (string)$block->getTarget(), false );
		if ( !$user->getId() ) {
			// user in an IP?
			return true;
		}

		$options = [ 'ORDER BY' => 'cuc_timestamp DESC' ];

		// just the last IP used
		$options['LIMIT'] = 1;

		$res = $dbr->select( 'cu_changes',
			[ 'cuc_ip' ],
			[ 'cuc_user' => $user->getId() ],
			__METHOD__,
			$options
		);

		# Iterate through IPs used (this is just one or zero for now)
		foreach ( $res as $row ) {
			if ( $row->cuc_ip ) {
				$id = $block->doAutoblock( $row->cuc_ip );
				if ( $id ) {
					$blockIds[] = $id;
				}
			}
		}

		// autoblock handled
		return false;
	}

	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [ 'cu_changes', 'cuc_user', 'cuc_user_text' ];
		$updateFields[] = [ 'cu_log', 'cul_user', 'cul_user_text' ];
		$updateFields[] = [ 'cu_log', 'cul_target_id' ];

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( RenameuserSQL $renameUserSQL ) {
		$renameUserSQL->tablesJob['cu_changes'] = [
			RenameuserSQL::NAME_COL => 'cuc_user_text',
			RenameuserSQL::UID_COL  => 'cuc_user',
			RenameuserSQL::TIME_COL => 'cuc_timestamp',
			'uniqueKey'    => 'cuc_id'
		];

		$renameUserSQL->tables['cu_log'] = [ 'cul_user_text', 'cul_user' ];

		return true;
	}
}
