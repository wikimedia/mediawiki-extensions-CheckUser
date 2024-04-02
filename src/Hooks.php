<?php

namespace MediaWiki\CheckUser;

use AutoCommitUpdate;
use DatabaseLogEntry;
use DatabaseUpdater;
use DeferredUpdates;
use ExtensionRegistry;
use LogEntryBase;
use LogFormatter;
use MailAddress;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\Hook\AuthManagerLoginAuthenticateAuditHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\Hook\PerformRetroactiveAutoblockHook;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\Investigate\SpecialInvestigateBlock;
use MediaWiki\CheckUser\Maintenance\PopulateCucActor;
use MediaWiki\CheckUser\Maintenance\PopulateCucComment;
use MediaWiki\CheckUser\Maintenance\PopulateCulActor;
use MediaWiki\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\ContributionsToolLinksHook;
use MediaWiki\Hook\EmailUserHook;
use MediaWiki\Hook\RecentChange_saveHook;
use MediaWiki\Hook\UserLogoutCompleteHook;
use MediaWiki\Hook\UserToolLinksEditHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Renameuser\RenameuserSQL;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\Hook\User__mailPasswordInternalHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRigorOptions;
use MessageSpecifier;
use PopulateCheckUserTable;
use RecentChange;
use RequestContext;
use SpecialPage;
use Status;
use Title;
use User;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\ScopedCallback;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class Hooks implements
	AuthManagerLoginAuthenticateAuditHook,
	ContributionsToolLinksHook,
	EmailUserHook,
	LoadExtensionSchemaUpdatesHook,
	LocalUserCreatedHook,
	PerformRetroactiveAutoblockHook,
	RecentChange_saveHook,
	SpecialPage_initListHook,
	UserLogoutCompleteHook,
	UserToolLinksEditHook,
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
	 * @param array &$list
	 * @return bool
	 */
	public function onSpecialPage_initList( &$list ) {
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
					'PermissionManager',
					'CheckUserLogService',
					'UserIdentityLookup',
					'UserFactory',
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
		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );
		$eventTablesMigrationStage = $services->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );

		if (
			$rc->getAttribute( 'rc_type' ) == RC_LOG &&
			( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW )
		) {
			if ( $rc->getAttribute( 'rc_logid' ) == 0 ) {
				$rcRow = [
					'cupe_namespace'  => $attribs['rc_namespace'],
					'cupe_title'      => $attribs['rc_title'],
					'cupe_log_type'   => $attribs['rc_log_type'],
					'cupe_log_action' => $attribs['rc_log_action'],
					'cupe_params'     => $attribs['rc_params'],
					'cupe_timestamp'  => $dbw->timestamp( $attribs['rc_timestamp'] ),
				];

				# If rc_comment_id is set, then use it. Instead get the comment id by a lookup
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
					$rc->getAttribute( 'rc_logid' ),
					__METHOD__,
					$rc->getPerformerIdentity(),
					$rc
				);
			}
			// We have stored the row in either cu_log_event or cu_private_event so no need
			//  to store it in cu_changes.
			return;
		}

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

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );

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

	/**
	 * Inserts a row into cu_log_event based on provided log ID and performer.
	 *
	 * The $user parameter is used to fill the column values about the performer of the log action.
	 * The log ID is stored in the table and used to get information to show the CheckUser when
	 * running a check.
	 *
	 * @param int $id the log ID associated with the entry to add to cu_log_event
	 * @param string $method the method name that called this, used for the insertion into the DB.
	 * @param UserIdentity $user the user who made the request.
	 * @param ?RecentChange $rc If triggered by a RecentChange, then this is the associated
	 *  RecentChange object. Null if not triggered by a RecentChange.
	 * @return void
	 */
	private static function insertIntoCuLogEventTable(
		int $id,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	) {
		$services = MediaWikiServices::getInstance();

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );

		/** @var DatabaseLogEntry $logEntry Should not be null as a valid ID must be provided */
		$logEntry = DatabaseLogEntry::newFromId( $id, $dbw );

		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		$row = [
			'cule_log_id' => $id
		];

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		( new HookRunner( $services->getHookContainer() ) )
			->onCheckUserInsertLogEventRow( $ip, $xff, $row, $user, $id, $rc );
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

		$dbw->insert( 'cu_log_event', $row, $method );
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

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );

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

		$dbw->insert( 'cu_private_event', $row, $method );
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

		$dbw = $services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );

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

		$dbw->insert( 'cu_changes', $row, $method );
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
		} else {
			self::insertIntoCuChangesTable(
				[
					'cuc_namespace'  => NS_USER,
					'cuc_actiontext' => wfMessage(
						'checkuser-reset-action',
						$accountName
					)->inContentLanguage()->text(),
				],
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

		$row = [];
		$prefix = '';
		$eventTablesMigrationStage = MediaWikiServices::getInstance()->getMainConfig()
			->get( 'CheckUserEventTablesMigrationStage' );
		$row['namespace'] = NS_USER;
		if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$prefix = 'cupe_';
			$row['log_action'] = 'email-sent';
			$row['params'] = LogEntryBase::makeParamBlob( [ '4::hash' => $hash ] );
		} else {
			$prefix = 'cuc_';
			$row['actiontext'] = wfMessage( 'checkuser-email-action', $hash )->inContentLanguage()->text();
		}
		if ( trim( $wgCUPublicKey ) != '' ) {
			$privateData = $userTo->getEmail() . ":" . $userTo->getId();
			$encryptedData = new EncryptedData( $privateData, $wgCUPublicKey );
			$row['private'] = serialize( $encryptedData );
		}
		// Prefix with the correct string depending on which table
		//  is being written to.
		foreach ( $row as $key => $value ) {
			$row[$prefix . $key] = $value;
			unset( $row[$key] );
		}
		$fname = __METHOD__;
		DeferredUpdates::addCallableUpdate(
			static function () use ( $row, $userFrom, $fname, $eventTablesMigrationStage ) {
				if ( $eventTablesMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
					self::insertIntoCuPrivateEventTable(
						$row,
						$fname,
						$userFrom
					);
				} else {
					self::insertIntoCuChangesTable(
						$row,
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
					'cupe_namespace' => NS_USER,
					'cupe_log_action'    => $autocreated ? 'autocreate-account' : 'create-account'
				],
				__METHOD__,
				$user
			);
		} else {
			self::insertIntoCuChangesTable(
				[
					'cuc_namespace'  => NS_USER,
					'cuc_actiontext' => wfMessage(
						$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
					)->inContentLanguage()->text(),
				],
				__METHOD__,
				$user
			);
		}
	}

	/**
	 * @param AuthenticationResponse $ret
	 * @param User|null $user
	 * @param string $username
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
					'cupe_log_action'     => substr( $msg, strlen( 'checkuser-' ) ),
					'cupe_params'     => LogEntryBase::makeParamBlob( [ '4::target' => $userName ] ),
				],
				__METHOD__,
				$performer
			);
		} else {
			self::insertIntoCuChangesTable(
				[
					'cuc_namespace'  => NS_USER,
					'cuc_title'      => $userName,
					'cuc_actiontext' => wfMessage( $msg )->params( $target )->inContentLanguage()->text(),
				],
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
		} else {
			self::insertIntoCuChangesTable(
				[
					'cuc_namespace'  => NS_USER,
					'cuc_title'      => $oldName,
					'cuc_actiontext' => wfMessage( 'checkuser-logout' )->inContentLanguage()->text(),
				],
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
		DeferredUpdates::addUpdate( new AutoCommitUpdate(
			MediaWikiServices::getInstance()
				->getDBLoadBalancer()
				->getMaintenanceConnectionRef( DB_PRIMARY ),
			__METHOD__,
			static function ( IDatabase $dbw, $fname ) {
				// per-wiki
				$key = "{$dbw->getDomainID()}:PruneCheckUserData";
				$scopedLock = $dbw->getScopedLockAndFlush( $key, $fname, 1 );
				if ( !$scopedLock ) {
					return;
				}

				$encCutoff = $dbw->addQuotes( $dbw->timestamp(
					ConvertibleTimestamp::time() - MediaWikiServices::getInstance()->getMainConfig()->get( 'CUDMaxAge' )
				) );

				$deleteOperation = static function (
					$table, $idField, $timestampField
				) use ( $dbw, $encCutoff, $fname ) {
					$ids = $dbw->newSelectQueryBuilder()
						->table( $table )
						->field( $idField )
						->conds( [ "$timestampField < $encCutoff" ] )
						->limit( 500 )
						->caller( $fname )
						->fetchFieldValues();
					if ( $ids ) {
						$dbw->delete( $table, [ $idField => $ids ], $fname );
					}
				};

				$deleteOperation( 'cu_changes', 'cuc_id', 'cuc_timestamp' );

				$deleteOperation( 'cu_private_event', 'cupe_id', 'cupe_timestamp' );

				$deleteOperation( 'cu_log_event', 'cule_id', 'cule_timestamp' );
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
	 *   - The XFF value, converted to a empty string if false
	 *
	 * @param string|bool $xff XFF header value
	 * @return array (string|null, bool, string)
	 * @deprecated since 1.40 - Use CheckUserUtilityService::getClientIPfromXFF
	 */
	public static function getClientIPfromXFF( $xff ) {
		wfDeprecated( 'getClientIPfromXFF', '1.40', 'CheckUser' );
		/** @var CheckUserUtilityService $checkUserUtilityService */
		$checkUserUtilityService = MediaWikiServices::getInstance()->get( 'CheckUserUtilityService' );
		return $checkUserUtilityService->getClientIPfromXFF( $xff );
	}

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . '/../schema';
		$maintenanceDb = $updater->getDB();
		$dbType = $maintenanceDb->getType();
		$isCUInstalled = $updater->tableExists( 'cu_changes' );

		$updater->addExtensionTable( 'cu_changes', "$base/$dbType/tables-generated.sql" );

		if ( $dbType === 'mysql' ) {
			// 1.35
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_id',
				"$base/$dbType/patch-cu_changes-cuc_id-unsigned.sql"
			);

			// 1.38
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_actor_ip_time',
				"$base/$dbType/patch-cu_changes-actor-comment.sql"
			);

			// 1.39
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_timestamp',
				"$base/$dbType/patch-cu_changes-cuc_timestamp.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_reason_id',
				"$base/$dbType/patch-cu_log-comment_table_for_reason.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-actor.sql"
			);
		} elseif ( $dbType === 'sqlite' ) {
			// 1.39
			$updater->addExtensionIndex(
				'cu_changes',
				'cuc_actor_ip_time',
				"$base/$dbType/patch-cu_changes-actor-comment.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_reason_id',
				"$base/$dbType/patch-cu_log-comment_table_for_reason.sql"
			);
			$updater->addExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-actor.sql"
			);
		} elseif ( $dbType === 'postgres' ) {
			// 1.37
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_log', 'cul_user' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_log', 'cul_target_id' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_changes', 'cuc_user' ] );
			$updater->addExtensionUpdate( [ 'dropFkey', 'cu_changes', 'cuc_page_id' ] );

			// 1.38
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_changes', 'cuc_actor', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_changes', 'cuc_comment_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_user_text', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgIndex', 'cu_changes', 'cuc_actor_ip_time', '( cuc_actor, cuc_ip, cuc_timestamp )' ]
			);

			// 1.39
			$updater->addExtensionIndex( 'cu_changes', 'cu_changes_pkey', "$base/$dbType/patch-cu_changes-pk.sql" );
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_namespace', 'INT', 'cuc_namespace::INT DEFAULT 0' ]
			);
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cuc_user' ) ) {
				$updater->addExtensionUpdate(
					[ 'changeNullableField', 'cu_changes', 'cuc_user', 'NOT NULL', true ]
				);
			}
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cuc_user_text' ) ) {
				$updater->addExtensionUpdate(
					[ 'changeField', 'cu_changes', 'cuc_user_text', 'VARCHAR(255)', '' ]
				);
				$updater->addExtensionUpdate(
					[ 'setDefault', 'cu_changes', 'cuc_user_text', '' ]
				);
			}
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_actor', 'BIGINT', 'cuc_actor::BIGINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_comment_id', 'BIGINT', 'cuc_comment_id::BIGINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_minor', 'SMALLINT', 'cuc_minor::SMALLINT DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_changes', 'cuc_page_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_page_id', 0 ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_changes', 'cuc_timestamp', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_ip', 'VARCHAR(255)', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_ip', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_ip_hex', 'VARCHAR(255)', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_changes', 'cuc_xff', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_xff_hex', 'VARCHAR(255)', '' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeField', 'cu_changes', 'cuc_private', 'TEXT', '' ]
			);
			$updater->addExtensionIndex( 'cu_log', 'cu_log_pkey', "$base/$dbType/patch-cu_log-pk.sql" );
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_timestamp', 'NOT NULL', true ]
			);
			if ( $maintenanceDb->fieldExists( 'cu_log', 'cul_user' ) ) {
				$updater->addExtensionUpdate(
					[ 'changeNullableField', 'cu_log', 'cul_user', 'NOT NULL', true ]
				);
			}
			$updater->addExtensionUpdate(
				[ 'dropDefault', 'cu_log', 'cul_type' ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_target_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'setDefault', 'cu_log', 'cul_target_id', 0 ]
			);
			$updater->addExtensionUpdate(
				[ 'dropDefault', 'cu_log', 'cul_target_text' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_reason_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_reason_plaintext_id', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgField', 'cu_log', 'cul_actor', 'INTEGER NOT NULL DEFAULT 0' ]
			);
			$updater->addExtensionUpdate(
				[ 'addPgIndex', 'cu_log', 'cul_actor_time', '( cul_actor, cul_timestamp )' ]
			);
		}

		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCulActor::class,
			'extensions/CheckUser/maintenance/populateCulActor.php'
		] );
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCulComment::class,
			'extensions/CheckUser/maintenance/populateCulComment.php'
		] );
		if ( $dbType === 'postgres' ) {
			# For wikis which ran update.php after pulling the master branch of CheckUser between
			#  4 June 2022 and 6 June 2022, the cul_reason_id and cul_reason_plaintext_id columns
			#  were added but were by default NULL.
			# This is needed for postgres installations that did the above. All other DB types
			#  make the columns "NOT NULL" when removing the default.
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_reason_id', 'NOT NULL', true ]
			);
			$updater->addExtensionUpdate(
				[ 'changeNullableField', 'cu_log', 'cul_reason_plaintext_id', 'NOT NULL', true ]
			);
		}

		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCucActor::class,
			'extensions/CheckUser/maintenance/populateCucActor.php'
		] );
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateCucComment::class,
			'extensions/CheckUser/maintenance/populateCucComment.php'
		] );

		// 1.40
		$updater->addExtensionTable(
			'cu_log_event',
			"$base/$dbType/patch-cu_log_event-def.sql"
		);
		$updater->addExtensionTable(
			'cu_private_event',
			"$base/$dbType/patch-cu_private_event-def.sql"
		);
		$updater->dropExtensionField(
			'cu_log',
			'cul_user',
			"$base/$dbType/patch-cu_log-drop-cul_user.sql"
		);
		if (
			$dbType !== 'sqlite' ||
			$maintenanceDb->fieldExists( 'cu_log', 'cul_reason' )
		) {
			// Only run this for SQLite if cul_reason exists,
			//  as modifyExtensionField does not take into account
			//  SQLite patches that use temporary tables. If the cul_reason
			//  field does not exist this SQL would fail, however, cul_reason
			//  not existing also means this change has been previously applied.
			$updater->modifyExtensionField(
				'cu_log',
				'cul_actor',
				"$base/$dbType/patch-cu_log-drop-actor_default.sql"
			);
		}
		$updater->dropExtensionField(
			'cu_log',
			'cul_reason',
			"$base/$dbType/patch-cu_log-drop-cul_reason.sql"
		);
		$updater->modifyExtensionField(
			'cu_log',
			'cul_reason_id',
			"$base/$dbType/patch-cu_log-drop-cul_reason_id_default.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_user',
			"$base/$dbType/patch-cu_changes-drop-cuc_user.sql"
		);
		$updater->addExtensionField(
			'cu_changes',
			'cuc_only_for_read_old',
			"$base/$dbType/patch-cu_changes-add-cuc_only_for_read_old.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_comment',
			"$base/$dbType/patch-cu_changes-drop-cuc_comment.sql"
		);
		$updater->modifyExtensionField(
			'cu_changes',
			'cuc_actor',
			"$base/$dbType/patch-cu_changes-drop-defaults.sql"
		);

		if ( !$isCUInstalled ) {
			// First time so populate cu_changes with recentchanges data.
			// Note: We cannot completely rely on updatelog here for old entries
			// as populateCheckUserTable.php doesn't check for duplicates
			$updater->addPostDatabaseUpdateMaintenance( PopulateCheckUserTable::class );
		}
	}

	/**
	 * Add a link to Special:CheckUser and Special:CheckUserLog
	 * on Special:Contributions/<username> for
	 * privileged users.
	 * @param int $id User ID
	 * @param Title $nt User page title
	 * @param string[] &$links Tool links
	 * @param SpecialPage $sp Special page
	 */
	public function onContributionsToolLinks(
		$id, Title $nt, array &$links, SpecialPage $sp
	) {
		$user = $sp->getUser();
		$linkRenderer = $sp->getLinkRenderer();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( $permissionManager->userHasRight( $user, 'checkuser' ) ) {
			$links['checkuser'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$sp->msg( 'checkuser-contribs' )->text(),
				[ 'class' => 'mw-contributions-link-check-user' ],
				[ 'user' => $nt->getText() ]
			);
		}
		if ( $permissionManager->userHasRight( $user, 'checkuser-log' ) ) {
			$links['checkuser-log'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$sp->msg( 'checkuser-contribs-log' )->text(),
				[ 'class' => 'mw-contributions-link-check-user-log' ],
				[
					'cuSearch' => $nt->getText()
				]
			);
			if ( $id ) {
				$links['checkuser-log-initiator'] = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$sp->msg( 'checkuser-contribs-log-initiator' )->text(),
					[ 'class' => 'mw-contributions-link-check-user-initiator' ],
					[
						'cuInitiator' => $nt->getText()
					]
				);
			}
		}
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

		$dbr = $services
			->getDBLoadBalancerFactory()
			->getMainLB( $block->getWikiId() )
			->getConnectionRef( DB_REPLICA, [], $block->getWikiId() );

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
	 * @param array &$updateFields
	 *
	 * @return bool
	 */
	public static function onUserMergeAccountFields( array &$updateFields ) {
		$updateFields[] = [
			'cu_changes',
			'batch_key' => 'cuc_id',
			'actorId' => 'cuc_actor',
			'actorStage' => SCHEMA_COMPAT_NEW
		];
		$updateFields[] = [
			'cu_log',
			'batch_key' => 'cul_id',
			'actorId' => 'cul_actor',
			'actorStage' => SCHEMA_COMPAT_NEW
		];
		$updateFields[] = [ 'cu_log', 'cul_target_id' ];

		return true;
	}

	/**
	 * For integration with user renames.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( RenameuserSQL $renameUserSQL ) {
		$renameUserSQL->tables['cu_log'] = [ 'cul_target_text', 'cul_target_id' ];
		return true;
	}

	/**
	 * @param int $userId
	 * @param string $userText
	 * @param string[] &$items
	 */
	public function onUserToolLinksEdit( $userId, $userText, &$items ) {
		$requestTitle = RequestContext::getMain()->getTitle();
		if (
			$requestTitle !== null &&
			$requestTitle->inNamespace( NS_SPECIAL )
		) {
			$specialPageName = MediaWikiServices::getInstance()->getSpecialPageFactory()
				->resolveAlias( $requestTitle->getText() )[0];
			if ( $specialPageName === 'CheckUserLog' ) {
				$items[] = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'CheckUserLog', $userText ),
					wfMessage( 'checkuser-log-checks-on' )->text()
				);
			} elseif ( $specialPageName === 'CheckUser' ) {
				$items[] = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'CheckUser', $userText ),
					wfMessage( 'checkuser-toollink-check' )->text(),
					[],
					[ 'reason' => RequestContext::getMain()->getRequest()->getVal( 'reason', '' ) ]
				);
			}
		}
	}

	/**
	 * @param RecentChange $recentChange
	 */
	public function onRecentChange_save( $recentChange ) {
		self::updateCheckUserData( $recentChange );
		$this->maybePruneIPData();
	}
}
