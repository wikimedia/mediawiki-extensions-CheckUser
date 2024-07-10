<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MailAddress;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use RecentChange;
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
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( $table )
			->assertRowValue( $expectedRow );
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
				[ 'Log', $this->getDb()->timestamp( $attribs['rc_timestamp'] ), NS_SPECIAL ]
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
				[ 'Log', $this->getDb()->timestamp( $attribs['rc_timestamp'] ), NS_SPECIAL ]
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
		// Pass the expected timestamp through IReadableTimestamp::timestamp to ensure it is in the right format
		// for the current DB type (T366590).
		if ( array_key_exists( 'cule_timestamp', $fields ) ) {
			$keyForTimestamp = array_search( 'cule_timestamp', $fields );
			$expectedRow[$keyForTimestamp] = $this->getDb()->timestamp( $expectedRow[$keyForTimestamp] );
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
		$expectedRow = [ $this->getDb()->timestamp( $attribs['rc_timestamp'] ) ];
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
					$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
						$this->getDb()->anyString(),
						'"4::receiver"',
						$this->getDb()->anyString(),
						$account->getName(),
						$this->getDb()->anyString()
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
					$this->getDb()->expr( 'cuc_actiontext', IExpression::LIKE, new LikeValue(
						$this->getDb()->anyString(),
						'[[User:', $account->getName(), '|', $account->getName(), ']]',
						$this->getDb()->anyString()
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
		DeferredUpdates::doUpdates();
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
				$this->getDb()->expr( 'cupe_params', IExpression::LIKE, new LikeValue(
					$this->getDb()->anyString(),
					'4::hash',
					$this->getDb()->anyString()
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
				$expectedRow[$index] = $this->getDb()->timestamp( $expectedRow[$index] );
			}
		}
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( $table )
			->assertRowValue( $expectedRow );
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
