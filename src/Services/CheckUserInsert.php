<?php

namespace MediaWiki\CheckUser\Services;

use DatabaseLogEntry;
use Language;
use LogEntryBase;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use RecentChange;
use RequestContext;
use WebRequest;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * This service provides methods that can be used
 * to insert data into the CheckUser result tables.
 *
 * Extensions other than CheckUser should not use
 * the methods marked as internal.
 */
class CheckUserInsert {

	private ActorStore $actorStore;
	private CheckUserUtilityService $checkUserUtilityService;
	private CommentStore $commentStore;
	private HookRunner $hookRunner;
	private IConnectionProvider $connectionProvider;
	private Language $contentLanguage;

	/**
	 * The maximum number of bytes that fit in CheckUser's text fields,
	 * specifically user agent, XFF strings and action text.
	 */
	public const TEXT_FIELD_LENGTH = 255;

	public function __construct(
		ActorStore $actorStore,
		CheckUserUtilityService $checkUserUtilityService,
		CommentStore $commentStore,
		HookContainer $hookContainer,
		IConnectionProvider $connectionProvider,
		Language $contentLanguage
	) {
		$this->actorStore = $actorStore;
		$this->checkUserUtilityService = $checkUserUtilityService;
		$this->commentStore = $commentStore;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->connectionProvider = $connectionProvider;
		$this->contentLanguage = $contentLanguage;
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
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuLogEventTable(
		DatabaseLogEntry $logEntry,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): void {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		$row = [
			'cule_log_id' => $logEntry->getId()
		];

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertLogEventRow( $ip, $xff, $row, $user, $logEntry->getId(), $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$row = array_merge( [
			'cule_actor'     => $this->actorStore->acquireActorId( $user, $dbw ),
			'cule_timestamp' => $dbw->timestamp( $logEntry->getTimestamp() ),
			'cule_ip'        => IPUtils::sanitizeIP( $ip ),
			'cule_ip_hex'    => $ip ? IPUtils::toHex( $ip ) : null,
			'cule_xff'       => !$isSquidOnly ? $xff : '',
			'cule_xff_hex'   => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
			'cule_agent'     => $this->getAgent( $request ),
		], $row );

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cule_xff'] = $this->contentLanguage->truncateForDatabase( $row['cule_xff'], self::TEXT_FIELD_LENGTH );

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
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuPrivateEventTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): void {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );

		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertPrivateEventRow( $ip, $xff, $row, $user, $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
		$row = array_merge(
			[
				'cupe_namespace'  => 0,
				'cupe_title'      => '',
				'cupe_log_type'   => 'checkuser-private-event',
				'cupe_log_action' => '',
				'cupe_params'     => LogEntryBase::makeParamBlob( [] ),
				'cupe_page'       => 0,
				'cupe_actor'      => $this->actorStore->acquireActorId( $user, $dbw ),
				'cupe_timestamp'  => $dbw->timestamp( wfTimestampNow() ),
				'cupe_ip'         => IPUtils::sanitizeIP( $ip ),
				'cupe_ip_hex'     => $ip ? IPUtils::toHex( $ip ) : null,
				'cupe_xff'        => !$isSquidOnly ? $xff : '',
				'cupe_xff_hex'    => ( $xff_ip && !$isSquidOnly ) ? IPUtils::toHex( $xff_ip ) : null,
				'cupe_agent'      => $this->getAgent( $request ),
			],
			$row
		);

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cupe_xff'] = $this->contentLanguage->truncateForDatabase( $row['cupe_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cupe_comment_id'] ) ) {
			$row += $this->commentStore->insert(
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
	 * @internal Only for use by the CheckUser extension
	 */
	public function insertIntoCuChangesTable(
		array $row,
		string $method,
		UserIdentity $user,
		?RecentChange $rc = null
	): void {
		$request = RequestContext::getMain()->getRequest();

		$ip = $request->getIP();
		$xff = $request->getHeader( 'X-Forwarded-For' );
		// Provide the ip, xff and row to code that hooks onto this so that they can modify the row before
		//  it's inserted. The ip and xff are provided separately so that the caller doesn't have to set
		//  the hex versions of the IP and XFF and can therefore leave that to this function.
		$this->hookRunner->onCheckUserInsertChangesRow( $ip, $xff, $row, $user, $rc );
		[ $xff_ip, $isSquidOnly, $xff ] = $this->checkUserUtilityService->getClientIPfromXFF( $xff );

		$dbw = $this->connectionProvider->getPrimaryDatabase();
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
				'cuc_agent'      => $this->getAgent( $request ),
			],
			$row
		);

		// (T199323) Truncate text fields prior to database insertion
		// Attempting to insert too long text will cause an error in MariaDB/MySQL strict mode
		$row['cuc_actiontext'] = $this->contentLanguage->truncateForDatabase(
			$row['cuc_actiontext'],
			self::TEXT_FIELD_LENGTH
		);
		$row['cuc_xff'] = $this->contentLanguage->truncateForDatabase( $row['cuc_xff'], self::TEXT_FIELD_LENGTH );

		if ( !isset( $row['cuc_actor'] ) ) {
			$row['cuc_actor'] = $this->actorStore->acquireActorId(
				$user,
				$dbw
			);
		}

		if ( !isset( $row['cuc_comment_id'] ) ) {
			$row += $this->commentStore->insert(
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
	 * Get user agent for the given request.
	 *
	 * @param WebRequest $request
	 * @return string
	 */
	private function getAgent( WebRequest $request ): string {
		$agent = $request->getHeader( 'User-Agent' );
		if ( $agent === false ) {
			// no agent was present, store as an empty string (otherwise, it would
			// end up stored as a zero due to boolean casting done by the DB layer).
			return '';
		}
		return $this->contentLanguage->truncateForDatabase( $agent, self::TEXT_FIELD_LENGTH );
	}
}
