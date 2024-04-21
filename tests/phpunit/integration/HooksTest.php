<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use LogEntryBase;
use MailAddress;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Message;
use Psr\Log\LoggerInterface;
use RecentChange;
use RequestContext;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use MockAuthorityTrait;
	use TempUserTestTrait;

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromClass( Hooks::class );
	}

	private function updateCheckUserData(
		array $rcAttribs,
		int $eventTableMigrationStage,
		string $table,
		array $fields,
		array &$expectedRow
	): void {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$this->commonTestsUpdateCheckUserData( $rcAttribs, $fields, $expectedRow );
		$this->assertSelect(
			$table,
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	/** @dataProvider provideUpdateCheckUserDataNoSave */
	public function testUpdateCheckUserDataNoSave( array $rcAttribs ) {
		$expectedRow = [];
		$this->commonTestsUpdateCheckUserData( $rcAttribs, [], $expectedRow );
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'A row was inserted to cu_changes when it should not have been.' );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'A row was inserted to cu_private_event when it should not have been.' );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'A row was inserted to cu_log_event when it should not have been.' );
	}

	public function testProvideUpdateCheckUserData() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		$testUser = new UserIdentityValue( 1337, 'YeaaaahTests' );
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId(
			$testUser,
			$this->getDb()
		);
		$testCases = [
			'registered user' => [
				array_merge( $attribs, [
					'rc_type' => RC_EDIT,
					'rc_user' => $testUser->getId(),
					'rc_user_text' => $testUser->getName(),
				] ),
				SCHEMA_COMPAT_NEW,
				'cu_changes',
				[ 'cuc_actor', 'cuc_type' ],
				[ $actorId, RC_EDIT ]
			],
			'Log for special title with no log ID for write old' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => ''
				] ),
				SCHEMA_COMPAT_OLD,
				'cu_changes',
				[ 'cuc_title', 'cuc_timestamp', 'cuc_namespace' ],
				[ 'Log', $attribs['rc_timestamp'], NS_SPECIAL ]
			],
			'Log for special title with no log ID for write new' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => ''
				] ),
				SCHEMA_COMPAT_NEW,
				'cu_private_event',
				[ 'cupe_title', 'cupe_timestamp', 'cupe_namespace' ],
				[ 'Log', $attribs['rc_timestamp'], NS_SPECIAL ]
			]
		];
		foreach ( $testCases as $testCase => $values ) {
			$this->onRecentChangeSave(
				$values[0],
				$values[1],
				$values[2],
				$values[3],
				$values[4]
			);
			$this->truncateTables( [
				'cu_changes',
				'cu_private_event',
				'cu_log_event',
				'recentchanges'
			] );
			$this->updateCheckUserData(
				$values[0],
				$values[1],
				$values[2],
				$values[3],
				$values[4]
			);
			$this->truncateTables( [
				'cu_changes',
				'cu_private_event',
				'cu_log_event',
				'recentchanges'
			] );
		}
	}

	/** @dataProvider provideUpdateCheckUserDataLogEvent */
	public function testUpdateCheckUserDataLogEvent(
		array $rcAttribs, int $eventTableMigrationStage, string $table, array $fields, array $expectedRow
	) {
		ConvertibleTimestamp::setFakeTime( $rcAttribs['rc_timestamp'] );
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [
			'cu_log_event',
		] );
		$rcAttribs['rc_logid'] = $logId;
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$fields[] = 'cule_log_id';
			$expectedRow[] = $logId;
		}
		$this->updateCheckUserData( $rcAttribs, $eventTableMigrationStage, $table, $fields, $expectedRow );
	}

	public function testUpdateCheckUserDataWhenLogEntryIsMissingT343983() {
		$expectsWarningLogger = $this->getMockBuilder( LoggerInterface::class )->getMock();
		$expectsWarningLogger->expects( $this->once() )
			->method( 'warning' )
			->willReturnCallback( function ( $message, $context ) {
				$this->assertSame( -1, $context['rc_logid'] );
				$this->assertArrayHasKey( 'exception', $context );
			} );
		$this->setLogger( 'CheckUser', $expectsWarningLogger );

		$attribs = array_merge(
			self::getDefaultRecentChangeAttribs(),
			[
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
				'rc_log_type' => ''
			] );
		$table = 'cu_private_event';
		$fields = [ 'cupe_timestamp' ];
		$expectedRow = [ $attribs['rc_timestamp'] ];
		ConvertibleTimestamp::setFakeTime( $attribs['rc_timestamp'] );
		$attribs['rc_logid'] = -1;
		$this->updateCheckUserData( $attribs, SCHEMA_COMPAT_WRITE_NEW, $table, $fields, $expectedRow );
	}

	public static function provideUpdateCheckUserDataLogEvent() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();

		yield 'Log with log ID with write old' => [
			array_merge( $attribs, [
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
				'rc_log_type' => ''
			] ),
			SCHEMA_COMPAT_OLD,
			'cu_changes',
			[ 'cuc_timestamp' ],
			[ $attribs['rc_timestamp'] ]
		];

		yield 'Log with log ID with write new' => [
			array_merge( $attribs, [
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
				'rc_log_type' => ''
			] ),
			SCHEMA_COMPAT_NEW,
			'cu_log_event',
			[ 'cule_timestamp' ],
			[ $attribs['rc_timestamp'] ]
		];
	}

	public static function provideUpdateCheckUserDataNoSave() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		yield 'external user' => [
			array_merge( $attribs, [
				'rc_type' => RC_EXTERNAL,
				'rc_user' => 0,
				'rc_user_text' => 'm>External User',
			] ),
			[ 'cuc_ip' ],
			[]
		];

		yield 'categorize' => [
			array_merge( $attribs, [
				'rc_namespace' => NS_MAIN,
				'rc_title' => '',
				'rc_type' => RC_CATEGORIZE,
			] ),
			[ 'cuc_ip' ],
			[]
		];
	}

	public static function provideEventMigrationStageValues() {
		return [
			'With event table migration set to old' => [ SCHEMA_COMPAT_OLD ],
			'With event table migration set to write old and new, read new' =>
				[ SCHEMA_COMPAT_NEW | SCHEMA_COMPAT_WRITE_OLD ],
			'With event table migration set to write old and new, read old' =>
				[ SCHEMA_COMPAT_OLD | SCHEMA_COMPAT_WRITE_NEW ],
			'With event table migration set to new' => [ SCHEMA_COMPAT_NEW ]
		];
	}

	/** @dataProvider provideEventMigrationStageValues */
	public function testonUser__mailPasswordInternal( int $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$performer = $this->getTestUser()->getUser();
		$account = $this->getTestSysop()->getUser();
		( new Hooks() )->onUser__mailPasswordInternal( $performer, 'IGNORED', $account );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->assertRowCount(
				1, 'cu_private_event', 'cupe_id',
				'The row was not inserted or was inserted with the wrong data',
				[
					'cupe_actor' => $performer->getActorId(),
					'cupe_namespace' => NS_USER,
					'cupe_title' => $account->getName(),
					$this->db->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
						$this->db->anyString(),
						'"4::receiver"',
						$this->db->anyString(),
						$account->getName(),
						$this->db->anyString()
					) )
				]
			);
		}
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->assertRowCount(
				1, 'cu_changes', 'cuc_id',
				'The row was not inserted or was inserted with the wrong data',
				[
					'cuc_actor' => $performer->getActorId(),
					'cuc_namespace' => NS_USER,
					'cuc_title' => $account->getName(),
					$this->db->expr( 'cuc_actiontext', IExpression::LIKE, new LikeValue(
						$this->db->anyString(),
						'[[User:', $account->getName(), '|', $account->getName(), ']]',
						$this->db->anyString()
					) ),
					'cuc_only_for_read_old' => ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ? 1 : 0
				]
			);
		}
	}

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreatedReadOld( bool $autocreated ) {
		$this->testOnLocalUserCreated( $autocreated, SCHEMA_COMPAT_OLD );
	}

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreatedReadNew( bool $autocreated ) {
		$this->testOnLocalUserCreated( $autocreated, SCHEMA_COMPAT_NEW );
	}

	private function testOnLocalUserCreated( bool $autocreated, int $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$user = $this->getTestUser()->getUser();
		( new Hooks() )->onLocalUserCreated( $user, $autocreated );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->assertRowCount(
				1, 'cu_private_event', 'cupe_id',
				'The row was not inserted or was inserted with the wrong data',
				[
					'cupe_actor'  => $user->getActorId(),
					'cupe_namespace' => NS_USER,
					'cupe_title' => $user->getName(),
					'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account'
				]
			);
		}
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->assertRowCount(
				1, 'cu_changes', 'cuc_id',
				'The row was not inserted or was inserted with the wrong data',
				[
					'cuc_actor'  => $user->getActorId(),
					'cuc_namespace'  => NS_USER,
					'cuc_title' => $user->getName(),
					'cuc_actiontext' => wfMessage(
						$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
					)->inContentLanguage()->text(),
					'cuc_only_for_read_old' => ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ? 1 : 0
				]
			);
		}
	}

	public static function provideOnLocalUserCreated() {
		return [
			[ true ],
			[ false ]
		];
	}

	/**
	 * Re-define the CheckUserInsert service to expect no calls to any of it's methods.
	 * This is done to assert that no inserts to the database occur instead of having
	 * to assert a row count of zero.
	 *
	 * @return void
	 */
	private function expectNoCheckUserInsertCalls() {
		$checkUserInsertMock = $this->createMock( CheckUserInsert::class );
		$checkUserInsertMock->expects( $this->never() )
			->method( $this->anything() );
		$this->setService( 'CheckUserInsert', static function () use ( $checkUserInsertMock ) {
			return $checkUserInsertMock;
		} );
	}

	/** @dataProvider provideTestOnEmailUserNoSave */
	public function testOnEmailUserNoSave( MailAddress $to, MailAddress $from ) {
		$this->expectNoCheckUserInsertCalls();
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_OLD );
		$subject = '';
		$text = '';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		DeferredUpdates::doUpdates();
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		DeferredUpdates::doUpdates();
	}

	public static function provideTestOnEmailUserNoSave() {
		return [
			'Email with the sender and recipient as the same user' => [
				new MailAddress( 'test@test.com', 'Test' ),
				new MailAddress( 'test@test.com', 'Test' ),
			]
		];
	}

	public function testOnEmailUserNoSecretKey() {
		$this->setMwGlobals( [
			'wgSecretKey' => null
		] );
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$this->testOnEmailUserNoSave( $to, $from );
	}

	public function testOnEmailUserReadOnlyMode() {
		$this->setMwGlobals( [
			'wgReadOnly' => true
		] );
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$this->testOnEmailUserNoSave( $to, $from );
	}

	public function commonOnEmailUser(
		MailAddress $to, MailAddress $from, int $eventTableMigrationStage,
		array $cuChangesWhere, array $cuPrivateWhere
	) {
		$subject = 'Test subject';
		$text = 'Test text';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		\DeferredUpdates::doUpdates();
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->assertRowCount(
				1, 'cu_private_event', '*',
				'A row was not inserted with the correct data',
				array_merge( $cuPrivateWhere, [ 'cupe_namespace' => NS_USER ] )
			);
		}
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->assertRowCount(
				1, 'cu_changes', '*',
				'A row was not inserted with the correct data',
				array_merge( $cuChangesWhere, [
					'cuc_namespace' => NS_USER,
					'cuc_only_for_read_old' => ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ? 1 : 0
				] )
			);
		}
	}

	/** @dataProvider provideEventMigrationStageValues */
	public function testOnEmailUserFrom( int $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$userTo = $this->getTestUser()->getUserIdentity();
		$userFrom = $this->getTestSysop()->getUser();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			$eventTableMigrationStage,
			[ 'cuc_actor' => $userFrom->getActorId(), 'cuc_title' => $userFrom->getName() ],
			[ 'cupe_actor' => $userFrom->getActorId(), 'cupe_title' => $userFrom->getName() ]
		);
	}

	/** @dataProvider provideEventMigrationStageValues */
	public function testOnEmailUserActionText( int $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		global $wgSecretKey;
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			$eventTableMigrationStage,
			[
				'cuc_actiontext' => wfMessage(
					'checkuser-email-action',
					md5( $userTo->getEmail() . $userTo->getId() . $wgSecretKey )
				)->inContentLanguage()->text()
			],
			[
				$this->db->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->db->anyString(),
					'4::hash',
					$this->db->anyString()
				) )
			]
		);
	}

	private function onRecentChangeSave(
		array $rcAttribs,
		int $eventTableMigrationStage,
		string $table,
		array $fields,
		array $expectedRow
	): void {
		// @todo test that maybePruneIPData() is called?
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		( new Hooks() )->onRecentChange_save( $rc );
		foreach ( $fields as $index => $field ) {
			if ( in_array( $field, [ 'cuc_timestamp', 'cule_timestamp', 'cupe_timestamp' ] ) ) {
				$expectedRow[$index] = $this->db->timestamp( $expectedRow[$index] );
			}
		}
		$this->assertSelect(
			$table,
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	private function doTestOnAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $authResp,
		User $userObj,
		string $userName,
		bool $isAnonPerformer,
		string $messageKey,
		int $eventTableMigrationStage
	): void {
		if ( $isAnonPerformer ) {
			$this->disableAutoCreateTempUser();
		}
		( new Hooks() )->onAuthManagerLoginAuthenticateAudit(
			$authResp,
			$userObj,
			$userName,
			[]
		);
		$cuChangesFields = [ 'cuc_namespace', 'cuc_title', 'actor_user', 'actor_name', 'cuc_actiontext' ];
		$cuPrivateFields = [
			'cupe_namespace', 'cupe_title', 'actor_user', 'actor_name', 'cupe_params', 'cupe_log_action'
		];
		$expectedValues = [ NS_USER, $userName ];
		if ( $isAnonPerformer ) {
			$expectedValues[] = 0;
			$expectedValues[] = RequestContext::getMain()->getRequest()->getIP();
		} else {
			$expectedValues[] = $userObj->getId();
			$expectedValues[] = $userName;
		}
		$target = "[[User:$userName|$userName]]";
		$cuChangesExpectedValues = $expectedValues;
		$cuPrivateExpectedValues = $expectedValues;
		$cuChangesExpectedValues[] = wfMessage( $messageKey, $target )->text();
		$cuPrivateExpectedValues[] = LogEntryBase::makeParamBlob( [ '4::target' => $userName ] );
		$cuPrivateExpectedValues[] = substr( $messageKey, strlen( 'checkuser-' ) );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->assertSelect(
				[ 'cu_private_event', 'actor' ],
				$cuPrivateFields,
				[],
				[ $cuPrivateExpectedValues ],
				[],
				[ 'actor' => [ 'JOIN', 'actor_id=cupe_actor' ] ]
			);
		}
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->assertSelect(
				[ 'cu_changes', 'actor' ],
				$cuChangesFields,
				[],
				[ $cuChangesExpectedValues ],
				[],
				[ 'actor' => [ 'JOIN', 'actor_id=cuc_actor' ] ]
			);
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAudit */
	public function testOnAuthManagerLoginAuthenticateAudit(
		string $authStatus, string $messageKey,
		bool $isAnonPerformer, array $userGroups, int $eventTableMigrationStage
	) {
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => true,
			'wgCheckUserLogSuccessfulBotLogins' => true,
			'wgCheckUserEventTablesMigrationStage' => $eventTableMigrationStage
		] );
		$userObj = $this->getTestUser( $userGroups )->getUser();
		$userName = $userObj->getName();
		$authResp = $this->getMockAuthenticationResponseForStatus( $authStatus, $userName );

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $messageKey, $eventTableMigrationStage
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAudit() {
		$eventTableMigrationStageValues = self::provideEventMigrationStageValues();
		$testCases = [
			'successful login' => [
				AuthenticationResponse::PASS,
				'checkuser-login-success',
				false,
				[],
			],
			'failed login' => [
				AuthenticationResponse::FAIL,
				'checkuser-login-failure',
				true,
				[],
			],
			'successful bot login' => [
				AuthenticationResponse::PASS,
				'checkuser-login-success',
				false,
				[ 'bot' ]
			]
		];
		foreach ( $eventTableMigrationStageValues as $additionalName => $eventTableMigrationStageValue ) {
			foreach ( $testCases as $name => $testCase ) {
				$testCase[] = $eventTableMigrationStageValue[0];
				yield $name . ' ' . $additionalName => $testCase;
			}
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled */
	public function testOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled(
		array $authFailReasons, bool $existingUser, string $messageKey,
		bool $isAnonPerformer, int $eventTableMigrationStage
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$authResp = AuthenticationResponse::newFail(
			$this->createMock( Message::class ),
			$authFailReasons
		);

		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => true,
			'wgCheckUserLogSuccessfulBotLogins' => true,
			'wgCheckUserEventTablesMigrationStage' => $eventTableMigrationStage
		] );
		$userObj = $existingUser
			? $this->getTestUser()->getUser()
			: MediaWikiServices::getInstance()->getUserFactory()->newFromName( wfRandomString() );
		$userName = $userObj->getName();

		$this->doTestOnAuthManagerLoginAuthenticateAudit(
			$authResp, $userObj, $userName, $isAnonPerformer, $messageKey, $eventTableMigrationStage
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled() {
		$eventTableMigrationStageValues = self::provideEventMigrationStageValues();
		$testCases = [
			'failed login with correct password' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, but cannot be referenced
				//  directly due to T321864
				[ "good password" ],
				true,
				'checkuser-login-failure-with-good-password',
				true,
			],
			'failed login with the correct password but locked and no local account' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				false,
				'checkuser-login-failure-with-good-password',
				true,
			],
			'failed login with correct password but locked' => [
				// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
				//  respectively but cannot be referenced directly due to T321864
				[ "good password", "locked" ],
				true,
				'checkuser-login-failure-with-good-password',
				false,
			],
		];
		foreach ( $eventTableMigrationStageValues as $additionalName => $eventTableMigrationStageValue ) {
			foreach ( $testCases as $name => $testCase ) {
				$testCase[] = $eventTableMigrationStageValue[0];
				yield $name . ' ' . $additionalName => $testCase;
			}
		}
	}

	private function getMockAuthenticationResponseForStatus( $status, $user = 'test' ) {
		$req = $this->getMockForAbstractClass( AuthenticationRequest::class );
		switch ( $status ) {
			case AuthenticationResponse::PASS:
				return AuthenticationResponse::newPass( $user );
			case AuthenticationResponse::FAIL:
				return AuthenticationResponse::newFail( $this->createMock( Message::class ) );
			case AuthenticationResponse::ABSTAIN:
				return AuthenticationResponse::newAbstain();
			case AuthenticationResponse::REDIRECT:
				return AuthenticationResponse::newRedirect( [ $req ], '' );
			case AuthenticationResponse::RESTART:
				return AuthenticationResponse::newRestart( $this->createMock( Message::class ) );
			case AuthenticationResponse::UI:
				return AuthenticationResponse::newUI( [ $req ], $this->createMock( Message::class ) );
			default:
				$this->fail( 'No AuthenticationResponse mock was defined for the status ' . $status );
		}
	}

	/** @dataProvider provideOnAuthManagerLoginAuthenticateAuditNoSave */
	public function testOnAuthManagerLoginAuthenticateAuditNoSave(
		string $status,
		bool $validUser,
		array $userGroups,
		bool $logLogins,
		bool $logBots,
		int $eventTableMigrationStage
	) {
		$this->expectNoCheckUserInsertCalls();
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => $logLogins,
			'wgCheckUserLogSuccessfulBotLogins' => $logBots,
			'wgCheckUserEventTablesMigrationStage' => $eventTableMigrationStage
		] );
		if ( $validUser ) {
			$userObj = $this->getTestUser( $userGroups )->getUser();
			$userName = $userObj->getName();
		} else {
			$userObj = null;
			$userName = '';
		}
		$ret = $this->getMockAuthenticationResponseForStatus( $status, $userName );
		( new Hooks() )->onAuthManagerLoginAuthenticateAudit(
			$ret,
			$userObj,
			$userName,
			[]
		);
	}

	public static function provideOnAuthManagerLoginAuthenticateAuditNoSave() {
		$eventTableMigrationStageValues = self::provideEventMigrationStageValues();
		$testCases = [
			'invalid user' => [
				AuthenticationResponse::PASS,
				false,
				[],
				true,
				true
			],
			'Abstain authentication response' => [
				AuthenticationResponse::ABSTAIN,
				true,
				[],
				true,
				true
			],
			'Redirect authentication response' => [
				AuthenticationResponse::REDIRECT,
				true,
				[],
				true,
				true
			],
			'UI authentication response' => [
				AuthenticationResponse::UI,
				true,
				[],
				true,
				true
			],
			'Restart authentication response' => [
				AuthenticationResponse::RESTART,
				true,
				[],
				true,
				true
			],
			'LogLogins set to false' => [
				AuthenticationResponse::PASS,
				true,
				[],
				false,
				true
			],
			'Successful authentication with wgCheckUserLogSuccessfulBotLogins set to false' => [
				AuthenticationResponse::PASS,
				false,
				[ 'bot' ],
				true,
				false
			]
		];
		foreach ( $eventTableMigrationStageValues as $additionalName => $eventTableMigrationStageValue ) {
			foreach ( $testCases as $name => $testCase ) {
				$testCase[] = $eventTableMigrationStageValue[0];
				yield $name . ' ' . $additionalName => $testCase;
			}
		}
	}

	/** @dataProvider provideEventMigrationStageValues */
	public function testUserLogoutComplete( int $eventTableSchemaValue ) {
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => true,
			'wgCheckUserEventTablesMigrationStage' => $eventTableSchemaValue
		] );
		$testUser = $this->getTestUser()->getUserIdentity();
		$html = '';
		( new Hooks() )->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			$testUser->getName()
		);
		if ( $eventTableSchemaValue & SCHEMA_COMPAT_WRITE_OLD ) {
			$this->assertRowCount(
				1, 'cu_changes', 'cuc_id',
				'Should have logged the event to cu_changes',
				[
					'cuc_only_for_read_old' => ( $eventTableSchemaValue & SCHEMA_COMPAT_WRITE_NEW ) ? 1 : 0
				]
			);
		}
		if ( $eventTableSchemaValue & SCHEMA_COMPAT_WRITE_NEW ) {
			$this->assertRowCount(
				1, 'cu_private_event', 'cupe_id',
				'Should have logged the event to cu_private_event'
			);
		}
	}

	/** @dataProvider provideEventMigrationStageValues */
	public function testUserLogoutCompleteNoSave( $eventTableSchemaValue ) {
		// TODO: Can this be moved to a non-DB integration test class?
		$this->expectNoCheckUserInsertCalls();
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => false,
			'wgCheckUserEventTablesMigrationStage' => $eventTableSchemaValue
		] );
		$services = MediaWikiServices::getInstance();
		$testUser = $this->getTestUser()->getUserIdentity();
		$html = '';
		( new Hooks() )->onUserLogoutComplete(
			$services->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			$testUser->getName()
		);
	}

	public function testUserLogoutCompleteInvalidUser() {
		// TODO: Can this be moved to a non-DB integration test class?
		$this->expectNoCheckUserInsertCalls();
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => true,
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_OLD
		] );
		$services = MediaWikiServices::getInstance();
		$html = '';
		( new Hooks() )->onUserLogoutComplete(
			$services->getUserFactory()->newAnonymous( '127.0.0.1' ),
			$html,
			'Nonexisting test user1234567'
		);
	}

	/**
	 * @dataProvider provideOnPerformRetroactiveAutoblock
	 * @todo test that the $blockIds variable is correct after calling the hook
	 */
	public function testOnPerformRetroactiveAutoblock( bool $isIP, bool $hasCUChangesRow, bool $shouldAutoblock ) {
		if ( $isIP ) {
			// Need to create actor IDs for IPs, so disable auto creation
			// of temporary users if enabled by default.
			$this->disableAutoCreateTempUser();
			$target = UserIdentityValue::newAnonymous( '127.0.0.1' );
			// Need to create an actor ID for the IP in case it makes no edits as part of the test.
			$this->getServiceContainer()->getActorStore()->createNewActor( $target, $this->db );
		} else {
			$target = $this->getTestUser()->getUserIdentity();
		}
		$this->setTemporaryHook(
			'CheckUserInsertChangesRow',
			static function ( string &$ip, string &$xff, array &$row ) {
				$ip = '127.0.0.2';
			}
		);
		if ( $hasCUChangesRow ) {
			$rc = new RecentChange();
			$rc->setAttribs(
				array_merge( self::getDefaultRecentChangeAttribs(), [
					'rc_user' => $target->getId(),
					'rc_user_text' => $target->getName()
				] )
			);
			$rc->setExtra( [
				'pageStatus' => 'changed'
			] );
			$rc->save();
		}
		$userAuthority = $this->mockRegisteredUltimateAuthority();
		$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
			$target,
			$userAuthority,
			'1 week'
		)->placeBlock();
		$block = DatabaseBlock::newFromTarget( $target->getName() );
		$result = ( new Hooks() )->onPerformRetroactiveAutoblock( $block, $blockIds );
		$blockManager = $this->getServiceContainer()->getBlockManager();
		$ipBlock = $blockManager->getIpBlock( '127.0.0.2', false );
		if ( $shouldAutoblock ) {
			$this->assertNotNull(
				$ipBlock,
				'One autoblock should have been placed on the IP.'
			);
			$this->assertFalse(
				$result,
				'The hook applied autoblocks so it should have returned false to stop further execution.'
			);
		} else {
			$this->assertNull(
				$ipBlock,
				'No autoblock should have been placed on the IP.'
			);
			if ( $isIP ) {
				$this->assertTrue(
					$result,
					'The hook shouldn\'t have applied autoblocks so it should not stop execution of further hooks.'
				);
			} else {
				$this->assertFalse(
					$result,
					'The hook should have attempted to autoblock, found no IPs to block and returned false.'
				);
			}
		}
	}

	/**
	 * Returns an array of arrays with each second
	 * level array containing boolean values to
	 * represent test conditions as follows:
	 * * The first is whether the target of the block
	 *    that was previously applied is an IP.
	 * * The second is whether the the target of the
	 *    block will have made any actions to store
	 *    an entry in cu_changes before the retroactive
	 *    autoblock.
	 * * The third is whether the hook should apply a
	 *    retroactive autoblock to the IP used.
	 */
	public static function provideOnPerformRetroactiveAutoblock() {
		return [
			[ true, false, false ],
			[ true, true, false ],
			[ false, false, false ],
			[ false, true, true ],
		];
	}

	/**
	 * @dataProvider providePruneIPDataData
	 * @covers \MediaWiki\CheckUser\Jobs\PruneCheckUserDataJob
	 */
	public function testPruneIPDataData( int $currentTime, int $maxCUDataAge, array $timestamps, int $afterCount ) {
		$this->setMwGlobals( [
			'wgCUDMaxAge' => $maxCUDataAge,
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_NEW
		] );
		$logEntryCutoff = $this->getDb()->timestamp( $currentTime - $maxCUDataAge );
		foreach ( $timestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			$expectedRow = [];
			// Insertion into cu_changes
			$this->commonTestsUpdateCheckUserData( self::getDefaultRecentChangeAttribs(), [], $expectedRow );
			// Insertion into cu_private_event
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
			// Insertion into cu_log_event
			$logId = $this->newLogEntry();
			$this->commonTestsUpdateCheckUserData(
				array_merge( self::getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_logid' => $logId ] ),
				[],
				$expectedRow
			);
		}
		$this->assertRowCount( count( $timestamps ), 'cu_changes', 'cuc_id',
			'cu_changes was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_private_event', 'cupe_id',
			'cu_private_event was not set up correctly for the test.' );
		$this->assertRowCount( count( $timestamps ), 'cu_log_event', 'cule_id',
			'cu_log_event was not set up correctly for the test.' );
		ConvertibleTimestamp::setFakeTime( $currentTime );
		$object = TestingAccessWrapper::newFromObject( ( new Hooks() ) );
		$object->pruneIPData();
		MediaWikiServices::getInstance()->getJobRunner()->run( [ 'type' => 'checkuserPruneCheckUserDataJob' ] );
		// Check that all the old entries are gone
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'cu_changes has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cuc_timestamp', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'cu_private_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cupe_timestamp ', '<', $logEntryCutoff ) ] );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'cu_log_event has stale entries after calling pruneIPData.',
			[ $this->getDb()->expr( 'cule_timestamp', '<', $logEntryCutoff ) ] );
		// Assert that no still in date entries were removed
		$this->assertRowCount( $afterCount, 'cu_changes', 'cuc_id',
			'cu_changes is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_private_event', 'cupe_id',
			'cu_private_event is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_log_event', 'cule_id',
			'cu_log_event is missing rows that were not stale after calling pruneIPData.' );
	}

	public static function providePruneIPDataData() {
		$currentTime = time();
		$defaultMaxAge = 7776000;
		return [
			'No entries to prune' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - 2,
					$currentTime - $defaultMaxAge + 100,
					$currentTime,
					$currentTime + 10
				],
				4
			],
			'Two entries to prune with two to be left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime,
					$currentTime + 10
				],
				2
			],
			'Four entries to prune with no left' => [
				$currentTime,
				$defaultMaxAge,
				[
					$currentTime - $defaultMaxAge - 20000,
					$currentTime - $defaultMaxAge - 100,
					$currentTime - $defaultMaxAge - 1,
					$currentTime - $defaultMaxAge - 100000
				],
				0
			]
		];
	}
}
