<?php

namespace MediaWiki\CheckUser;

use DatabaseLogEntry;
use DeferredUpdates;
use ExtensionRegistry;
use JobSpecification;
use LogEntryBase;
use LogFormatter;
use MailAddress;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\Hook\AuthManagerLoginAuthenticateAuditHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\Hook\PerformRetroactiveAutoblockHook;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\EmailUserHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\Hook\User__mailPasswordInternalHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use MessageSpecifier;
use RecentChange;
use RequestContext;
use Status;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\ScopedCallback;

class Hooks implements
	AuthManagerLoginAuthenticateAuditHook,
	EmailUserHook,
	LocalUserCreatedHook,
	PerformRetroactiveAutoblockHook,
	RecentChange_saveHook,
	UserLogoutCompleteHook,
	User__mailPasswordInternalHook
{

	/**
	 * The maximum number of bytes that fit in CheckUser's text fields
	 * (cuc_agent,cuc_actiontext,cuc_xff)
	 */
	public const TEXT_FIELD_LENGTH = 255;

	/**
	 * Get user agent for the current request
	 *
	 * @return string
	 */
	private static function getAgent(): string {
		$agent = RequestContext::getMain()->getRequest()->getHeader( 'User-Agent' );
		if ( $agent === false ) {
			// no agent was present, store as an empty string (otherwise, it would
			// end up stored as a zero due to boolean casting done by the DB layer).
			$agent = '';
		}
		return MediaWikiServices::getInstance()->getContentLanguage()
			->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
	}

	/**
	 * Hook function for RecentChange_save
	 * Saves user data into the cu_changes table
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry
	 * @param RecentChange $rc
	 */
	public static function updateCheckUserData( RecentChange $rc ) {
		global $wgCheckUserLogAdditionalRights;

		/**
		 * RC_CATEGORIZE recent changes are generally triggered by other edits.
		 * Thus there is no reason to store checkuser data about them.
		 * @see https://phabricator.wikimedia.org/T125209
		 */
		if ( $rc->getAttribute( 'rc_type' ) == RC_CATEGORIZE ) {
			return;
		}
		/**
		 * RC_EXTERNAL recent changes are not triggered by actions on the local wiki.
		 * Thus there is no reason to store checkuser data about them.
		 * @see https://phabricator.wikimedia.org/T125664
		 */
		if ( $rc->getAttribute( 'rc_type' ) == RC_EXTERNAL ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$attribs = $rc->getAttributes();
		$dbw = $services->getDBLoadBalancerFactory()->getPrimaryDatabase();
		$eventTablesMigrationStage = $services->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );

		if (
			$rc->getAttribute( 'rc_type' ) == RC_LOG &&
			( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW )
		) {
			// Write to either cu_log_event or cu_private_event if both:
			// * This is a log event
			// * Event table migration stage is set to write new
			$logId = $rc->getAttribute( 'rc_logid' );
			$logEntry = null;
			if ( $logId != 0 ) {
				$logEntry = DatabaseLogEntry::newFromId( $logId, $dbw );
				if ( $logEntry === null ) {
					LoggerFactory::getInstance( 'CheckUser' )->warning(
						'RecentChange with id {rc_id} has non-existing rc_logid {rc_logid}',
						[
							'rc_id' => $rc->getAttribute( 'rc_id' ),
							'rc_logid' => $rc->getAttribute( 'rc_logid' ),
							'exception' => new \RuntimeException()
						]
					);
				}
			}
			// In some rare cases the LogEntry for this rc_logid may not exist even if
			// rc_logid is not zero (T343983). If this occurs, consider rc_logid to be zero
			// and therefore save the entry in cu_private_event
			if ( $logEntry === null ) {
				$rcRow = [
					'cupe_namespace'  => $attribs['rc_namespace'],
					'cupe_title'      => $attribs['rc_title'],
					'cupe_log_type'   => $attribs['rc_log_type'],
					'cupe_log_action' => $attribs['rc_log_action'],
					'cupe_params'     => $attribs['rc_params'],
					'cupe_timestamp'  => $dbw->timestamp( $attribs['rc_timestamp'] ),
				];

				# If rc_comment_id is set, then use it. Instead, get the comment id by a lookup
				if ( isset( $attribs['rc_comment_id'] ) ) {
					$rcRow['cupe_comment_id'] = $attribs['rc_comment_id'];
				} else {
					$rcRow['cupe_comment_id'] = $services->getCommentStore()
						->createComment( $dbw, $attribs['rc_comment'], $attribs['rc_comment_data'] )->id;
				}

				# On PG, MW unsets cur_id due to schema incompatibilities. So it may not be set!
				if ( isset( $attribs['rc_cur_id'] ) ) {
					$rcRow['cupe_page'] = $attribs['rc_cur_id'];
				}

				self::insertIntoCuPrivateEventTable(
					$rcRow,
					__METHOD__,
					$rc->getPerformerIdentity(),
					$rc
				);
			} else {
				self::insertIntoCuLogEventTable(
					$logEntry,
					__METHOD__,
					$rc->getPerformerIdentity(),
					$rc
				);
			}
		}

		if (
			$rc->getAttribute( 'rc_type' ) != RC_LOG ||
			( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD )
		) {
			// Log to cu_changes if this isn't a log entry or if event table
			//  migration stage is set to write old.
			//
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

			$services = MediaWikiServices::getInstance();

			$dbw = $services->getDBLoadBalancerFactory()->getPrimaryDatabase();

			$rcRow = [
				'cuc_namespace'  => $attribs['rc_namespace'],
				'cuc_title'      => $attribs['rc_title'],
				'cuc_minor'      => $attribs['rc_minor'],
				'cuc_actiontext' => $actionText,
				'cuc_comment'    => $rc->getAttribute( 'rc_comment' ),
				'cuc_this_oldid' => $attribs['rc_this_oldid'],
				'cuc_last_oldid' => $attribs['rc_last_oldid'],
				'cuc_type'       => $attribs['rc_type'],
				'cuc_timestamp'  => $dbw->timestamp( $attribs['rc_timestamp'] ),
			];

			if (
				$rc->getAttribute( 'rc_type' ) == RC_LOG &&
				$eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW
			) {
				// 1 means true in this case.
				$rcRow['cuc_only_for_read_old'] = 1;
			}

			# On PG, MW unsets cur_id due to schema incompatibilities. So it may not be set!
			if ( isset( $attribs['rc_cur_id'] ) ) {
				$rcRow['cuc_page_id'] = $attribs['rc_cur_id'];
			}

			( new HookRunner( $services->getHookContainer() ) )->onCheckUserInsertForRecentChange( $rc, $rcRow );

			self::insertIntoCuChangesTable(
				$rcRow,
				__METHOD__,
				new UserIdentityValue( $attribs['rc_user'], $attribs['rc_user_text'] ),
				$rc
			);
		}
	}

	/**
	 * Inserts a row into cu_log_event based on provided log ID and performer.
	 *
	 * The $user parameter is used to fill the column values about the performer of the log action.
	 * The log ID is stored in the table and used to get information to show the CheckUser when
	 * running a check.
	 *
	 * @param DatabaseLogEntry $logEntry the log entry to add to cu_log_event
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user who made the request.
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return void
	 */
	private static function insertIntoCuLogEventTable(
		DatabaseLogEntry $logEntry,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	) {
		$services = MediaWikiServices::getInstance();
		$request = RequestContext::getMain()->getRequest();
		$dbw = $services->getDBLoadBalancerFactory()->getPrimaryDatabase();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		$row = [
			'cule_log_id' => $logEntry->getId()
		];

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		( new HookRunner( $services->getHookContainer() ) )
			->onCheckUserInsertLogEventRow( $ip, $xff, $row, $user, $logEntry->getId(), $rc );
		/** @var CheckUserUtilityService $checkUserUtilityService */
		$checkUserUtilityService = $services->get( 'CheckUserUtilityService' );
		list( $xff_ip, $isSquidOnly, $xff ) = $checkUserUtilityService->getClientIPfromXFF( $xff );

		$row = array_merge( [
			'cule_actor'     => $services->getActorStore()->acquireActorId( $user, $dbw ),
			'cule_timestamp' => $dbw->timestamp( $logEntry->getTimestamp() ),
			'cule_ip'        => IPUtils::sanitizeIP( $ip ),
			'cule_ip_hex'    => $ip ? IPUtils::toHex( $ip ) : null,
			'cule_xff'       => !$isSquidOnly ? $xff : '',
			'cule_xff_hex'   => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cule_agent'     => self::getAgent(),
		], $row );

		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cule_xff'] = $contLang->truncateForDatabase( $row['cule_xff'], self::TEXT_FIELD_LENGTH );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->row( $row )
			->caller( $method )
			->execute();
	}

	/**
	 * Inserts a row to cu_private_event based on a provided row and performer of the action.
	 *
	 * The $row has defaults applied, truncation performed and comment table insertion performed.
	 * The $user parameter is used to fill the default for the actor ID column.
	 *
	 * Provide cupe_comment_id if you have generated a comment table ID for this action, or provide
	 * cupe_comment if you want this method to deal with the comment table.
	 *
	 * @param array $row an array of cu_private_event table column names to their values. Changeable by a hook
	 *  and for any needed truncation.
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user associated with the event
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return void
	 */
	private static function insertIntoCuPrivateEventTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	) {
		$services = MediaWikiServices::getInstance();

		$dbw = $services->getDBLoadBalancerFactory()->getPrimaryDatabase();

		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		( new HookRunner( $services->getHookContainer() ) )
			->onCheckUserInsertPrivateEventRow( $ip, $xff, $row, $user, $rc );
		/** @var CheckUserUtilityService $checkUserUtilityService */
		$checkUserUtilityService = $services->get( 'CheckUserUtilityService' );
		list( $xff_ip, $isSquidOnly, $xff ) = $checkUserUtilityService->getClientIPfromXFF( $xff );

		$row = array_merge(
			[
				'cupe_namespace'  => 0,
				'cupe_title'      => '',
				'cupe_log_type'   => 'checkuser-private-event',
				'cupe_log_action' => '',
				'cupe_params'     => LogEntryBase::makeParamBlob( [] ),
				'cupe_page'       => 0,
				'cupe_actor'      => $services->getActorStore()->acquireActorId( $user, $dbw ),
				'cupe_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
				'cupe_ip'         => IPUtils::sanitizeIP( $ip ),
				'cupe_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
				'cupe_xff'        => !$isSquidOnly ? $xff : '',
				'cupe_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
				'cupe_agent'      => self::getAgent(),
			],
			$row
		);

		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cupe_xff'] = $contLang->truncateForDatabase( $row['cupe_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cupe_comment_id'] ) ) {
			$row += $services->getCommentStore()->insert(
				$dbw,
				'cupe_comment',
				$row['cupe_comment'] ?? ''
			);
		}

		// Remove any defined cupe_comment as this is not a valid column name.
		unset( $row['cupe_comment'] );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->row( $row )
			->caller( $method )
			->execute();
	}

	/**
	 * Inserts a row in cu_changes based on the provided $row.
	 *
	 * The $user parameter is used to generate the default value for cuc_actor.
	 *
	 * @param array $row an array of cu_change table column names to their values. Overridable by a hook
	 *  and for any necessary truncation.
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user who made the change
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return void
	 */
	private static function insertIntoCuChangesTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	) {
		$services = MediaWikiServices::getInstance();

		$dbw = $services->getDBLoadBalancerFactory()->getPrimaryDatabase();

		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );
		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		( new HookRunner( $services->getHookContainer() ) )
			->onCheckUserInsertChangesRow( $ip, $xff, $row, $user, $rc );
		/** @var CheckUserUtilityService $checkUserUtilityService */
		$checkUserUtilityService = $services->get( 'CheckUserUtilityService' );
		list( $xff_ip, $isSquidOnly, $xff ) = $checkUserUtilityService->getClientIPfromXFF( $xff );

		$row = array_merge(
			[
				'cuc_page_id'    => 0,
				'cuc_namespace'  => 0,
				'cuc_minor'      => 0,
				'cuc_title'      => '',
				'cuc_actiontext' => '',
				'cuc_comment'    => '',
				'cuc_this_oldid' => 0,
				'cuc_last_oldid' => 0,
				'cuc_type'       => RC_LOG,
				'cuc_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
				'cuc_ip'         => IPUtils::sanitizeIP( $ip ),
				'cuc_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
				'cuc_xff'        => !$isSquidOnly ? $xff : '',
				'cuc_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
				'cuc_agent'      => self::getAgent(),
			],
			$row
		);

		$contLang = $services->getContentLanguage();

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cuc_actiontext'] = $contLang->truncateForDatabase(
			$row['cuc_actiontext'],
			self::TEXT_FIELD_LENGTH
		);
		$row['cuc_xff'] = $contLang->truncateForDatabase( $row['cuc_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cuc_actor'] ) ) {
			$row['cuc_actor'] = $services->getActorStore()->acquireActorId(
				$user,
				$dbw
			);
		}

		if ( !isset( $row['cuc_comment_id'] ) ) {
			$row += $services->getCommentStore()->insert(
				$dbw,
				'cuc_comment',
				$row['cuc_comment']
			);
		}
		unset( $row['cuc_comment'] );

		$dbw->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->row( $row )
			->caller( $method )
			->execute();
	}

	/**
	 * Hook function to store password reset
	 * Saves user data into the cu_changes table
	 *
	 * @param User $user Sender
	 * @param string $ip
	 * @param User $account Receiver
	 */
	public function onUser__mailPasswordInternal( $user, $ip, $account ) {
		$accountName = $account->getName();
		$eventTablesMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			self::insertIntoCuPrivateEventTable(
				[
					'cupe_namespace'  => NS_USER,
					'cupe_log_action' => 'password-reset-email-sent',
					'cupe_params'     => LogEntryBase::makeParamBlob( [ '4::receiver' => $accountName ] )
				],
				__METHOD__,
				$user
			);
		}
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$row = [
				'cuc_namespace'  => NS_USER,
				'cuc_actiontext' => wfMessage(
					'checkuser-reset-action',
					$accountName
				)->inContentLanguage()->text(),
			];
			if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
				$row['cuc_only_for_read_old'] = 1;
			}
			self::insertIntoCuChangesTable(
				$row,
				__METHOD__,
				$user
			);
		}
	}

	/**
	 * Hook function to store email data.
	 *
	 * Saves user data into the cu_changes table.
	 * Uses a deferred update to save the data, because emails can be sent from code paths
	 * that don't open master connections.
	 *
	 * @param MailAddress &$to
	 * @param MailAddress &$from
	 * @param string &$subject
	 * @param string &$text
	 * @param bool|Status|MessageSpecifier|array &$error
	 */
	public function onEmailUser( &$to, &$from, &$subject, &$text, &$error ) {
		global $wgSecretKey, $wgCUPublicKey;

		$services = MediaWikiServices::getInstance();

		if ( !$wgSecretKey || $from->name == $to->name ) {
			return;
		}

		if ( $services->getReadOnlyMode()->isReadOnly() ) {
			return;
		}

		$userFrom = $services->getUserFactory()->newFromName( $from->name );
		'@phan-var User $userFrom';
		$userTo = $services->getUserFactory()->newFromName( $to->name );
		$hash = md5( $userTo->getEmail() . $userTo->getId() . $wgSecretKey );

		$cuChangesRow = [];
		$cuPrivateRow = [];
		$eventTablesMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		$cuPrivateRow['cupe_namespace'] = $cuChangesRow['cuc_namespace'] = NS_USER;
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$cuPrivateRow['cupe_log_action'] = 'email-sent';
			$cuPrivateRow['cupe_params'] = LogEntryBase::makeParamBlob( [ '4::hash' => $hash ] );
		}
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
				$cuChangesRow['cuc_only_for_read_old'] = 1;
			}
			$cuChangesRow['cuc_actiontext'] = wfMessage( 'checkuser-email-action', $hash )
				->inContentLanguage()->text();
		}
		if ( trim( $wgCUPublicKey ) != '' ) {
			$privateData = $userTo->getEmail() . ":" . $userTo->getId();
			$encryptedData = new EncryptedData( $privateData, $wgCUPublicKey );
			$cuPrivateRow['cupe_private'] = $cuChangesRow['cuc_private'] = serialize( $encryptedData );
		}
		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate(
			static function () use (
				$cuPrivateRow, $cuChangesRow, $userFrom, $fname, $eventTablesMigrationStage
			) {
				if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
					self::insertIntoCuPrivateEventTable(
						$cuPrivateRow,
						$fname,
						$userFrom
					);
				}
				if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
					self::insertIntoCuChangesTable(
						$cuChangesRow,
						$fname,
						$userFrom
					);
				}
			}
		);
	}

	/**
	 * Hook function to store registration and autocreation data
	 * Saves user data into the cu_changes table
	 *
	 * @param User $user
	 * @param bool $autocreated
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		$eventTablesMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			self::insertIntoCuPrivateEventTable(
				[
					'cupe_namespace'  => NS_USER,
					'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account'
				],
				__METHOD__,
				$user
			);
		}
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$row = [
				'cuc_namespace'  => NS_USER,
				'cuc_actiontext' => wfMessage(
					$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
				)->inContentLanguage()->text(),
			];
			if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
				$row['cuc_only_for_read_old'] = 1;
			}
			self::insertIntoCuChangesTable(
				$row,
				__METHOD__,
				$user
			);
		}
	}

	/**
	 * @param AuthenticationResponse $ret
	 * @param User|null $user
	 * @param string|null $username
	 * @param string[] $extraData
	 */
	public function onAuthManagerLoginAuthenticateAudit( $ret, $user, $username, $extraData ) {
		global $wgCheckUserLogLogins, $wgCheckUserLogSuccessfulBotLogins;

		if ( !$wgCheckUserLogLogins ) {
			return;
		}

		$services = MediaWikiServices::getInstance();

		if ( !$user && $username !== null ) {
			$user = $services->getUserFactory()->newFromName( $username, UserRigorOptions::RIGOR_USABLE );
		}

		if ( !$user ) {
			return;
		}

		if (
			$wgCheckUserLogSuccessfulBotLogins !== true &&
			$ret->status === AuthenticationResponse::PASS
		) {
			$userGroups = $services
				->getUserGroupManager()
				->getUserGroups( $user );

			if ( in_array( 'bot', $userGroups ) ) {
				return;
			}
		}

		$userName = $user->getName();

		if ( $ret->status === AuthenticationResponse::FAIL ) {
			// The login attempt failed so use the IP as the performer
			//  and checkuser-login-failure as the message.
			$msg = 'checkuser-login-failure';
			$performer = UserIdentityValue::newAnonymous(
				RequestContext::getMain()->getRequest()->getIP()
			);

			if (
				$ret->failReasons &&
				ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) &&
				in_array( CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, $ret->failReasons )
			) {
				// If the password was correct, then say so in the shown message.
				$msg = 'checkuser-login-failure-with-good-password';

				if (
					in_array( CentralAuthUser::AUTHENTICATE_LOCKED, $ret->failReasons ) &&
					array_diff(
						$ret->failReasons,
						[ CentralAuthUser::AUTHENTICATE_LOCKED, CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD ]
					) === [] &&
					$user->isRegistered()
				) {
					// If
					//  * The user is locked
					//  * The password is correct
					//  * The user exists locally on this wiki
					//  * Nothing else caused the request to fail
					// then we can assume that if the account was not locked this login attempt
					// would have been successful. Therefore, mark the user as the performer
					// to indicate this information to the CheckUser and so it shows up when
					// checking the locked account.
					$performer = $user;
				}
			}
		} elseif ( $ret->status === AuthenticationResponse::PASS ) {
			$msg = 'checkuser-login-success';
			$performer = $user;
		} else {
			// Abstain, Redirect, etc.
			return;
		}

		$target = "[[User:$userName|$userName]]";

		$eventTablesMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			self::insertIntoCuPrivateEventTable(
				[
					'cupe_namespace'  => NS_USER,
					'cupe_title'      => $userName,
					'cupe_log_action' => substr( $msg, strlen( 'checkuser-' ) ),
					'cupe_params'     => LogEntryBase::makeParamBlob( [ '4::target' => $userName ] ),
				],
				__METHOD__,
				$performer
			);
		}
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$row = [
				'cuc_namespace'  => NS_USER,
				'cuc_title'      => $userName,
				'cuc_actiontext' => wfMessage( $msg )->params( $target )->inContentLanguage()->text(),
			];
			if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
				$row['cuc_only_for_read_old'] = 1;
			}
			self::insertIntoCuChangesTable(
				$row,
				__METHOD__,
				$performer
			);
		}
	}

	/** @inheritDoc */
	public function onUserLogoutComplete( $user, &$inject_html, $oldName ) {
		$services = MediaWikiServices::getInstance();
		if ( !$services->getMainConfig()->get( 'CheckUserLogLogins' ) ) {
			# Treat the log logins config as also applying to logging logouts.
			return;
		}

		$performer = $services->getUserIdentityLookup()->getUserIdentityByName( $oldName );
		if ( $performer === null ) {
			return;
		}

		$eventTablesMigrationStage = $services->getMainConfig()->get( 'CheckUserEventTablesMigrationStage' );
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			self::insertIntoCuPrivateEventTable(
				[
					'cupe_namespace'  => NS_USER,
					'cupe_title'      => $oldName,
					'cupe_log_action' => 'user-logout',
				],
				__METHOD__,
				$performer
			);
		}
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$row = [
				'cuc_namespace'  => NS_USER,
				'cuc_title'      => $oldName,
				'cuc_actiontext' => wfMessage( 'checkuser-logout', $oldName )->inContentLanguage()->text(),
			];
			if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
				$row['cuc_only_for_read_old'] = 1;
			}
			self::insertIntoCuChangesTable(
				$row,
				__METHOD__,
				$performer
			);
		}
	}

	/**
	 * Hook function to prune data from the cu_changes table
	 *
	 * The chance of actually pruning data is 1/10.
	 */
	private function maybePruneIPData() {
		if ( mt_rand( 0, 9 ) == 0 ) {
			$this->pruneIPData();
		}
	}

	/**
	 * Prunes at most 500 entries from the cu_changes,
	 * cu_private_event, and cu_log_event tables separately
	 * that have exceeded the maximum time that they can
	 * be stored.
	 */
	private function pruneIPData() {
		$services = MediaWikiServices::getInstance();
		$services->getJobQueueGroup()->push(
			new JobSpecification(
				'checkuserPruneCheckUserDataJob',
				[
					'domainID' => $services
						->getDBLoadBalancer()
						->getConnection( DB_PRIMARY )
						->getDomainID()
				],
				[],
				null
			)
		);
	}

	/**
	 * Retroactively autoblocks the last IP used by the user (if it is a user)
	 * blocked by this block.
	 *
	 * @param DatabaseBlock $block
	 * @param int[] &$blockIds
	 * @return bool
	 */
	public function onPerformRetroactiveAutoblock( $block, &$blockIds ) {
		$services = MediaWikiServices::getInstance();

		$dbr = $services->getDBLoadBalancerFactory()->getReplicaDatabase( $block->getWikiId() );

		$userIdentityLookup = $services
			->getActorStoreFactory()
			->getUserIdentityLookup( $block->getWikiId() );
		$user = $userIdentityLookup->getUserIdentityByName( $block->getTargetName() );
		if ( !$user->isRegistered() ) {
			// user in an IP?
			return true;
		}

		$res = $dbr->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->useIndex( 'cuc_actor_ip_time' )
			->table( 'actor' )
			->field( 'cuc_ip' )
			->conds( [ 'actor_user' => $user->getId( $block->getWikiId() ) ] )
			->joinConds( [ 'actor' => [ 'JOIN', 'actor_id=cuc_actor' ] ] )
			// just the last IP used
			->limit( 1 )
			->orderBy( 'cuc_timestamp', SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchResultSet();

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

	/**
	 * @param RecentChange $recentChange
	 */
	public function onRecentChange_save( $recentChange ) {
		self::updateCheckUserData( $recentChange );
		$this->maybePruneIPData();
	}
}
