<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use ExtensionRegistry;
use MailAddress;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\CheckUser\Test\Integration\CheckUserCommonTraitTest;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RecentChange;
use RequestContext;
use SpecialPage;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @coversDefaultClass \MediaWiki\CheckUser\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use MockAuthorityTrait;

	public function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_changes',
			'cu_private_event',
			'cu_log_event',
			'user',
			'logging',
			'ipblocks',
			'ipblocks_restrictions',
			'recentchanges'
		];

		$this->setMwGlobals( [
			'wgCheckUserActorMigrationStage' => 3,
			'wgCheckUserLogActorMigrationStage' => 3
		] );
	}

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromClass( Hooks::class );
	}

	/**
	 * @covers ::onUserMergeAccountFields
	 * @todo test that the values returned by the hook are correct or not invalid?
	 */
	public function testOnUserMergeAccountFields() {
		$updateFields = [];
		$expectedCount = 3;
		$actorMigrationStage = $this->getServiceContainer()->getMainConfig()->get( 'CheckUserActorMigrationStage' );
		if ( ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ) {
			$expectedCount++;
		}
		$actorMigrationStage = $this->getServiceContainer()->getMainConfig()->get( 'CheckUserLogActorMigrationStage' );
		if ( ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ) {
			$expectedCount++;
		}
		Hooks::onUserMergeAccountFields( $updateFields );
		$this->assertCount(
			$expectedCount,
			$updateFields,
			'3 updates were added'
		);
	}

	/**
	 * @covers ::getAgent
	 * @dataProvider provideGetAgent
	 */
	public function testGetAgent( $userAgent, $expected ) {
		$request = TestingAccessWrapper::newFromObject( new \WebRequest() );
		$request->headers = [ 'USER-AGENT' => $userAgent ];
		RequestContext::getMain()->setRequest( $request->object );
		$this->assertEquals(
			$expected,
			$this->setUpObject()->getAgent(),
			'The expected user agent was not returned.'
		);
	}

	public function provideGetAgent() {
		return [
			[ false, '' ],
			[ '', '' ],
			[ 'Test', 'Test' ],
			[
				str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH ),
				str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH )
			],
			[
				str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH + 10 ),
				str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH - 3 ) . '...'
			]
		];
	}

	/**
	 * @todo Test timestamp(?)
	 *
	 * @covers ::insertIntoCuChangesTable
	 * @dataProvider provideInsertIntoCuChangesTable
	 */
	public function testInsertIntoCuChangesTable( $row, $fields, $expectedRow ) {
		$this->setUpObject()->insertIntoCuChangesTable( $row, __METHOD__, $this->getTestUser()->getUserIdentity() );
		$this->assertSelect(
			'cu_changes',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public function provideInsertIntoCuChangesTable() {
		return [
			'IP defaults' => [
				[],
				[ 'cuc_ip', 'cuc_ip_hex' ],
				[ '127.0.0.1', '7F000001' ]
			],
			'XFF defaults' => [
				[],
				[ 'cuc_xff', 'cuc_xff_hex' ],
				[ '', '' ]
			],
			'Other defaults' => [
				[],
				[ 'cuc_page_id', 'cuc_namespace', 'cuc_minor', 'cuc_title', 'cuc_actiontext',
					'cuc_comment', 'cuc_this_oldid', 'cuc_last_oldid', 'cuc_type', 'cuc_agent' ],
				[ 0, NS_MAIN, 0, '', '', '', 0, 0, RC_LOG, '' ]
			]
		];
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 * @dataProvider provideTestTruncationInsertIntoCuChangesTable
	 */
	public function testTruncationInsertIntoCuChangesTable( $field ) {
		$this->testInsertIntoCuChangesTable(
			[ $field => str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH + 9 ) ],
			[ $field ],
			[ str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH - 3 ) . '...' ]
		);
	}

	public function provideTestTruncationInsertIntoCuChangesTable() {
		return [
			[ 'cuc_comment' ],
			[ 'cuc_actiontext' ],
			[ 'cuc_xff' ]
		];
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 * @dataProvider provideXFFValues
	 */
	public function testInsertIntoCuChangesTableXFF( $xff, $xff_hex ) {
		RequestContext::getMain()->getRequest()->setHeader( 'X-Forwarded-For', $xff );
		$this->testInsertIntoCuChangesTable(
			[],
			[ 'cuc_xff', 'cuc_xff_hex' ],
			[ $xff, $xff_hex ]
		);
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertChangesRow
	 * @dataProvider provideXFFValues
	 */
	public function testInsertChangesRowHookXFF( $test_xff, $xff_hex ) {
		$this->setTemporaryHook(
			'CheckUserInsertChangesRow',
			static function ( &$ip, &$xff ) use ( $test_xff ) {
				$xff = $test_xff;
			}
		);
		$this->testInsertIntoCuChangesTable(
			[], [ 'cuc_xff', 'cuc_xff_hex' ], [ $test_xff, $xff_hex ]
		);
	}

	public function provideXFFValues() {
		return [
			'Empty XFF' => [
				'',
				''
			],
			'XFF not empty' => [
				'1.2.3.4, 5.6.7.8',
				'01020304'
			],
			'Invalid XFF' => [
				'Invalid XFF',
				''
			],
		];
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertChangesRow
	 */
	public function testInsertChangesRowHookIP() {
		$this->setTemporaryHook(
			'CheckUserInsertChangesRow',
			static function ( &$ip ) {
				$ip = '1.2.3.4';
			}
		);
		$this->testInsertIntoCuChangesTable(
			[], [ 'cuc_ip', 'cuc_ip_hex' ], [ '1.2.3.4', '01020304' ]
		);
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 */
	public function testUserInsertIntoCuChangesTable() {
		$user = $this->getTestUser()->getUserIdentity();
		$this->setUpObject()->insertIntoCuChangesTable( [], __METHOD__, $user );
		$this->assertSelect(
			'cu_changes',
			[ 'cuc_user', 'cuc_user_text' ],
			'',
			[ [ $user->getId(), $user->getName() ] ]
		);
	}

	/**
	 * @covers ::insertIntoCuChangesTable
	 */
	public function testActorInsertIntoCuChangesTable() {
		$actorMigrationStage = $this->getServiceContainer()->getMainConfig()->get( 'CheckUserActorMigrationStage' );
		if ( ( $actorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) ) {
			$user = $this->getTestUser();
			$this->setUpObject()->insertIntoCuChangesTable( [], __METHOD__, $user->getUserIdentity() );
			$this->assertSelect(
				'cu_changes',
				[ 'cuc_actor' ],
				'',
				[ [ $user->getUser()->getActorId() ] ]
			);
		} else {
			$this->expectNotToPerformAssertions();
		}
	}

	/**
	 * @todo Test for timestamp(?)
	 *
	 * @covers ::insertIntoCuPrivateEventTable
	 * @dataProvider provideInsertIntoCuPrivateEventTable
	 */
	public function testInsertIntoCuPrivateEventTable( $row, $fields, $expectedRow ) {
		$this->setUpObject()->insertIntoCuPrivateEventTable(
			$row, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_private_event',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public function provideInsertIntoCuPrivateEventTable() {
		return [
			'IP defaults' => [
				[],
				[ 'cupe_ip', 'cupe_ip_hex' ],
				[ '127.0.0.1', '7F000001' ]
			],
			'XFF defaults' => [
				[],
				[ 'cupe_xff', 'cupe_xff_hex' ],
				[ '', '' ]
			],
			'Other defaults' => [
				[],
				[ 'cupe_page', 'cupe_namespace', 'cupe_log_type', 'cupe_log_action',
					'cupe_title', 'cupe_params', 'cupe_agent', ],
				[ 0, NS_MAIN, 'checkuser-private-event', '', '', '', '' ]
			]
		];
	}

	/**
	 * @covers ::insertIntoCuPrivateEventTable
	 * @dataProvider provideTruncationInsertIntoCuPrivateEventTable
	 */
	public function testTruncationInsertIntoCuPrivateEventTable( $field ) {
		$this->testInsertIntoCuPrivateEventTable(
			[ $field => str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH + 9 ) ],
			[ $field ],
			[ str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH - 3 ) . '...' ]
		);
	}

	public function provideTruncationInsertIntoCuPrivateEventTable() {
		return [
			[ 'cupe_xff' ]
		];
	}

	/**
	 * @covers ::insertIntoCuPrivateEventTable
	 * @dataProvider provideXFFValues
	 */
	public function testInsertIntoCuPrivateEventTableXFF( $xff, $xff_hex ) {
		RequestContext::getMain()->getRequest()->setHeader( 'X-Forwarded-For', $xff );
		$this->testInsertIntoCuPrivateEventTable(
			[],
			[ 'cupe_xff', 'cupe_xff_hex' ],
			[ $xff, $xff_hex ]
		);
	}

	/**
	 * @covers ::insertIntoCuPrivateEventTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertPrivateEventRow
	 * @dataProvider provideXFFValues
	 */
	public function testInsertPrivateEventRowHookXFF( $test_xff, $xff_hex ) {
		$this->setTemporaryHook(
			'CheckUserInsertPrivateEventRow',
			static function ( &$ip, &$xff ) use ( $test_xff ) {
				$xff = $test_xff;
			}
		);
		$this->testInsertIntoCuPrivateEventTable(
			[], [ 'cupe_xff', 'cupe_xff_hex' ], [ $test_xff, $xff_hex ]
		);
	}

	/**
	 * @covers ::insertIntoCuPrivateEventTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertPrivateEventRow
	 */
	public function testInsertPrivateEventRowHookIP() {
		$this->setTemporaryHook(
			'CheckUserInsertPrivateEventRow',
			static function ( &$ip ) {
				$ip = '1.2.3.4';
			}
		);
		$this->testInsertIntoCuPrivateEventTable(
			[], [ 'cupe_ip', 'cupe_ip_hex' ], [ '1.2.3.4', '01020304' ]
		);
	}

	/**
	 * @covers ::insertIntoCuPrivateEventTable
	 */
	public function testUserInsertIntoCuPrivateEventTable() {
		$user = $this->getTestUser();
		$this->setUpObject()->insertIntoCuPrivateEventTable( [], __METHOD__, $user->getUserIdentity() );
		$this->assertSelect(
			'cu_private_event',
			[ 'cupe_actor' ],
			'',
			[ [ $user->getUser()->getActorId() ] ]
		);
	}

	/**
	 * @todo Test for timestamp(?)
	 *
	 * @covers ::insertIntoCuLogEventTable
	 * @dataProvider provideInsertIntoCuLogEventTable
	 */
	public function testInsertIntoCuLogEventTable( array $fields, array $expectedRow ) {
		$this->setUpObject()->insertIntoCuLogEventTable(
			$this->newLogEntry(), __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_log_event',
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	public function provideInsertIntoCuLogEventTable() {
		return [
			'IP defaults' => [
				[ 'cule_ip', 'cule_ip_hex' ], [ '127.0.0.1', '7F000001' ]
			],
			'XFF defaults' => [
				[ 'cule_xff', 'cule_xff_hex' ], [ '', '' ]
			],
			'Other defaults' => [
				[ 'cule_agent' ], [ '' ]
			]
		];
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertLogEventRow
	 * @dataProvider provideTruncationInsertIntoCuLogEventTable
	 */
	public function testTruncationInsertIntoCuLogEventTable( $field ) {
		$this->setTemporaryHook(
			'CheckUserInsertLogEventRow',
			static function ( &$ip, &$xff, &$row, $user, $id ) use ( $field ) {
				$row[$field] = str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH + 9 );
			}
		);
		$this->testInsertIntoCuLogEventTable(
			[ $field ],
			[ str_repeat( 'q', Hooks::TEXT_FIELD_LENGTH - 3 ) . '...' ]
		);
	}

	public function provideTruncationInsertIntoCuLogEventTable() {
		return [
			[ 'cule_xff' ]
		];
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 * @dataProvider provideXFFValues
	 */
	public function testInsertIntoCuLogEventTableXFF( $xff, $xff_hex ) {
		RequestContext::getMain()->getRequest()->setHeader( 'X-Forwarded-For', $xff );
		$this->testInsertIntoCuLogEventTable( [ 'cule_xff', 'cule_xff_hex' ], [ $xff, $xff_hex ] );
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertLogEventRow
	 * @dataProvider provideXFFValues
	 */
	public function testInsertLogEventRowHookXFF( $test_xff, $xff_hex ) {
		$this->setTemporaryHook(
			'CheckUserInsertLogEventRow',
			static function ( &$ip, &$xff ) use ( $test_xff ) {
				$xff = $test_xff;
			}
		);
		$this->testInsertIntoCuLogEventTable(
			[ 'cule_xff', 'cule_xff_hex' ], [ $test_xff, $xff_hex ]
		);
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertLogEventRow
	 */
	public function testInsertLogEventRowHookIP() {
		$this->setTemporaryHook(
			'CheckUserInsertLogEventRow',
			static function ( &$ip ) {
				$ip = '1.2.3.4';
			}
		);
		$this->testInsertIntoCuLogEventTable(
			[ 'cule_ip', 'cule_ip_hex' ], [ '1.2.3.4', '01020304' ]
		);
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 */
	public function testInsertIntoCuLogEventTableLogId() {
		$logId = $this->newLogEntry();
		$this->setUpObject()->insertIntoCuLogEventTable(
			$logId, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->assertSelect(
			'cu_log_event',
			[ 'cule_log_id' ],
			'',
			[ [ $logId ] ]
		);
	}

	/**
	 * @covers ::insertIntoCuLogEventTable
	 */
	public function testUserInsertIntoCuLogEventTable() {
		$user = $this->getTestUser();
		$this->setUpObject()->insertIntoCuLogEventTable( $this->newLogEntry(), __METHOD__, $user->getUserIdentity() );
		$this->assertSelect(
			'cu_log_event',
			[ 'cule_actor' ],
			'',
			[ [ $user->getUser()->getActorId() ] ]
		);
	}

	/**
	 * @covers ::updateCheckUserData
	 * @dataProvider provideUpdateCheckUserData
	 */
	public function testUpdateCheckUserData(
		$rcAttribs, $eventTableMigrationStage, $table, $fields, $expectedRow
	) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$this->commonTestsUpdateCheckUserData( $rcAttribs, $fields, $expectedRow );
		$this->assertSelect(
			$table,
			$fields,
			'',
			[ $expectedRow ]
		);
	}

	/**
	 * @covers ::updateCheckUserData
	 * @dataProvider provideUpdateCheckUserDataNoSave
	 */
	public function testUpdateCheckUserDataNoSave( $rcAttribs ) {
		$expectedRow = [];
		$this->commonTestsUpdateCheckUserData( $rcAttribs, [], $expectedRow );
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'A row was inserted to cu_changes when it should not have been.' );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'A row was inserted to cu_changes when it should not have been.' );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'A row was inserted to cu_changes when it should not have been.' );
	}

	public function provideUpdateCheckUserData() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = $this->getDefaultRecentChangeAttribs();
		yield 'anon user' => [
			array_merge( $attribs, [
				'rc_type' => RC_EDIT,
				'rc_user' => 0,
				'rc_user_text' => '192.168.0.1',
			] ),
			SCHEMA_COMPAT_NEW,
			'cu_changes',
			[ 'cuc_user_text', 'cuc_user', 'cuc_type' ],
			[ '192.168.0.1', 0, RC_EDIT ]
		];

		yield 'registered user' => [
			array_merge( $attribs, [
				'rc_type' => RC_EDIT,
				'rc_user' => 5,
				'rc_user_text' => 'Test',
			] ),
			SCHEMA_COMPAT_NEW,
			'cu_changes',
			[ 'cuc_user_text', 'cuc_user' ],
			[ 'Test', 5 ]
		];

		yield 'Log for special title with no log ID for write old' => [
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
		];

		yield 'Log for special title with no log ID for write new' => [
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
		];
	}

	/**
	 * @covers ::updateCheckUserData
	 * @dataProvider provideUpdateCheckUserDataLogEvent
	 */
	public function testUpdateCheckUserDataLogEvent(
		$rcAttribs, $eventTableMigrationStage, $table, $fields, $expectedRow
	) {
		ConvertibleTimestamp::setFakeTime( $rcAttribs['rc_timestamp'] );
		$logId = $this->newLogEntry();
		$rcAttribs['rc_logid'] = $logId;
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$fields[] = 'cule_log_id';
			$expectedRow[] = $logId;
		}
		$this->testUpdateCheckUserData( $rcAttribs, $eventTableMigrationStage, $table, $fields, $expectedRow );
	}

	public function provideUpdateCheckUserDataLogEvent() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = $this->getDefaultRecentChangeAttribs();

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

	public function provideUpdateCheckUserDataNoSave() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = $this->getDefaultRecentChangeAttribs();
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

	public function provideEventMigrationStageValues() {
		return [
			'With event table migration set to read old' => [ SCHEMA_COMPAT_OLD ],
			'With event table migration set to read new' => [ SCHEMA_COMPAT_NEW ]
		];
	}

	/**
	 * @covers ::onUser__mailPasswordInternal
	 * @dataProvider provideEventMigrationStageValues
	 */
	public function testonUser__mailPasswordInternal( $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$performer = $this->getTestUser()->getUser();
		$account = $this->getTestSysop()->getUser();
		( new Hooks() )->onUser__mailPasswordInternal( $performer, 'IGNORED', $account );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$table = 'cu_private_event';
			$idField = 'cupe_id';
			$where = [
				'cupe_actor' => $performer->getActorId(),
				'cupe_params' . $this->db->buildLike(
					$this->db->anyString(),
					'"4::receiver"',
					$this->db->anyString(),
					"UTSysop",
					$this->db->anyString()
				)
			];
		} else {
			$table = 'cu_changes';
			$idField = 'cuc_id';
			$where = [
				'cuc_user' => $performer->getId(),
				'cuc_user_text' => $performer->getName(),
				'cuc_actiontext' . $this->db->buildLike(
					$this->db->anyString(),
					'[[User:', $account->getName(), '|', $account->getName(), ']]',
					$this->db->anyString()
				)
			];
		}
		$this->assertRowCount(
			1, $table, $idField,
			'The row was not inserted or was inserted with the wrong data', $where
		);
	}

	/**
	 * @covers ::onLocalUserCreated
	 * @dataProvider provideOnLocalUserCreated
	 */
	public function testOnLocalUserCreatedReadOld( $autocreated ) {
		$this->testOnLocalUserCreated( $autocreated, SCHEMA_COMPAT_OLD );
	}

	/**
	 * @covers ::onLocalUserCreated
	 * @dataProvider provideOnLocalUserCreated
	 */
	public function testOnLocalUserCreatedReadNew( $autocreated ) {
		$this->testOnLocalUserCreated( $autocreated, SCHEMA_COMPAT_NEW );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	private function testOnLocalUserCreated( $autocreated, $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$user = $this->getTestUser()->getUser();
		( new Hooks() )->onLocalUserCreated( $user, $autocreated );
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$table = 'cu_private_event';
			$idField = 'cupe_id';
			$where = [
				'cupe_actor'  => $user->getActorId(),
				'cupe_log_action' => $autocreated ? 'autocreate-account' : 'create-account'
			];
		} else {
			$table = 'cu_changes';
			$idField = 'cuc_id';
			$where = [
				'cuc_namespace'  => NS_USER,
				'cuc_actiontext' => wfMessage(
					$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
				)->inContentLanguage()->text()
			];
		}
		$this->assertRowCount(
			1, $table, $idField,
			'The row was not inserted or was inserted with the wrong data', $where
		);
	}

	public function provideOnLocalUserCreated() {
		return [
			[ true ],
			[ false ]
		];
	}

	/**
	 * @covers ::onEmailUser
	 * @dataProvider provideTestOnEmailUserNoSave
	 */
	public function testOnEmailUserNoSave( $to, $from ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_OLD );
		$subject = '';
		$text = '';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		$this->assertRowCount(
			0, 'cu_changes', 'cuc_id',
			'A row was inserted to cu_changes when it should not have been.'
		);
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		$this->assertRowCount(
			0, 'cu_private_event', 'cupe_id',
			'A row was inserted to cu_private_event when it should not have been.'
		);
	}

	public function provideTestOnEmailUserNoSave() {
		return [
			'Email with the sender and recipient as the same user' => [
				new MailAddress( 'test@test.com', 'Test' ),
				new MailAddress( 'test@test.com', 'Test' ),
			]
		];
	}

	/**
	 * @covers ::onEmailUser
	 */
	public function testOnEmailUserNoSecretKey() {
		$this->setMwGlobals( [
			'wgSecretKey' => null
		] );
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$this->testOnEmailUserNoSave( $to, $from );
	}

	/**
	 * @covers ::onEmailUser
	 */
	public function testOnEmailUserReadOnlyMode() {
		$this->setMwGlobals( [
			'wgReadOnly' => true
		] );
		$to = new MailAddress( 'test@test.com', 'Test' );
		$from = new MailAddress( 'testing@test.com', 'Testing' );
		$this->testOnEmailUserNoSave( $to, $from );
	}

	public function commonOnEmailUser( $to, $from, $eventTableMigrationStage, $where ) {
		$subject = 'Test subject';
		$text = 'Test text';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		\DeferredUpdates::doUpdates();
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$table = 'cu_private_event';
			$where = array_merge( $where, [ 'cupe_namespace' => NS_USER ] );
		} else {
			$table = 'cu_changes';
			$where = array_merge( $where, [ 'cuc_namespace' => NS_USER ] );
		}
		$this->assertRowCount(
			1, $table, '*', 'A row was not inserted with the correct data', $where
		);
	}

	/**
	 * @covers ::onEmailUser
	 * @dataProvider provideEventMigrationStageValues
	 */
	public function testOnEmailUserFrom( $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$userTo = $this->getTestUser()->getUserIdentity();
		$userFrom = $this->getTestSysop()->getUser();
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$where = [ 'cupe_actor' => $userFrom->getActorId() ];
		} else {
			$where = [ 'cuc_user' => $userFrom->getId(), 'cuc_user_text' => $userFrom->getName() ];
		}
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			$eventTableMigrationStage,
			$where
		);
	}

	/**
	 * @covers ::onEmailUser
	 * @dataProvider provideEventMigrationStageValues
	 */
	public function testOnEmailUserActionText( $eventTableMigrationStage ) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		global $wgSecretKey;
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		if ( $eventTableMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$where = [
				'cupe_params' . $this->db->buildLike(
					$this->db->anyString(),
					'4::hash',
					$this->db->anyString()
				)
			];
		} else {
			$where = [
				'cuc_actiontext' => wfMessage(
					'checkuser-email-action',
					md5( $userTo->getEmail() . $userTo->getId() . $wgSecretKey )
				)->inContentLanguage()->text()
			];
		}
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			$eventTableMigrationStage,
			$where
		);
	}

	/**
	 * @covers ::onRecentChange_save
	 * @dataProvider provideUpdateCheckUserData
	 * @todo test that maybePruneIPData() is called?
	 */
	public function testonRecentChange_save(
		$rcAttribs, $eventTableMigrationStage, $table, $fields, $expectedRow
	) {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', $eventTableMigrationStage );
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		( new Hooks() )->onRecentChange_save( $rc );
		foreach ( $fields as $index => $field ) {
			if ( array_search( $field, [ 'cuc_timestamp', 'cule_timstamp', 'cupe_timestamp' ] ) ) {
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

	/**
	 * @covers ::onUserToolLinksEdit
	 * @dataProvider provideOnUserToolLinksEdit
	 */
	public function testOnUserToolLinksEdit( $requestTitle, $expectedItems ) {
		$testUser = $this->getTestUser()->getUserIdentity();
		$mainRequest = RequestContext::getMain();
		$mainRequest->setTitle( \Title::newFromText( $requestTitle ) );
		$mainRequest->getRequest()->setVal( 'reason', 'testing' );
		$mockLinkRenderer = $this->createMock( LinkRenderer::class );
		if ( $requestTitle == 'Special:CheckUserLog' ) {
			$mockLinkRenderer->method( 'makeLink' )
				->with(
					SpecialPage::getTitleFor( 'CheckUserLog', $testUser->getName() ),
					wfMessage( 'checkuser-log-checks-on' )->text()
				)->willReturn( 'CheckUserLog mocked link' );
		} else {
			$mockLinkRenderer->method( 'makeLink' )
				->with(
					SpecialPage::getTitleFor( 'CheckUser', $testUser->getName() ),
					wfMessage( 'checkuser-toollink-check' )->text(),
					[],
					[ 'reason' => 'testing' ]
				)->willReturn( 'CheckUser mocked link' );
		}
		$this->setService( 'LinkRenderer', $mockLinkRenderer );
		$items = [];
		( new Hooks() )->onUserToolLinksEdit( $testUser->getId(), $testUser->getName(), $items );
		if ( count( $expectedItems ) != 0 ) {
			$this->assertCount(
				1, $items, 'A tool link should have been added'
			);
			$this->assertArrayEquals(
				$expectedItems,
				$items,
				'The link was not correctly generated'
			);
		} else {
			$this->assertCount(
				0, $items, 'A tool link should not have been added'
			);
		}
	}

	public function provideOnUserToolLinksEdit() {
		return [
			'Current title is not in special namespace' => [
				'Testing1234', []
			],
			'Current title is in the special namespace, but not the CheckUserLog or CheckUser' => [
				'Special:History', []
			],
			'Current title is Special:CheckUser' => [
				'Special:CheckUser', [ 'CheckUser mocked link' ]
			],
			'Current title is Special:CheckUserLog' => [
				'Special:CheckUserLog', [ 'CheckUserLog mocked link' ]
			]
		];
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 * @dataProvider provideOnAuthManagerLoginAuthenticateAudit
	 */
	public function testOnAuthManagerLoginAuthenticateAudit(
		AuthenticationResponse $ret, string $user, string $messageKey, bool $isAnonPerformer
	) {
		$this->setMwGlobals( [ 'wgCheckUserLogLogins' => true, 'wgCheckUserLogSuccessfulBotLogins' => true ] );
		$userObj = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $user );
		( new Hooks() )->onAuthManagerLoginAuthenticateAudit(
			$ret,
			$userObj,
			$user,
			[]
		);
		$fields = [ 'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_actiontext' ];
		$expectedValues = [ NS_USER, $user ];
		if ( $isAnonPerformer ) {
			$expectedValues[] = 0;
			$expectedValues[] = RequestContext::getMain()->getRequest()->getIP();
		} else {
			$expectedValues[] = $userObj->getId();
			$expectedValues[] = $user;
		}
		$target = "[[User:$user|$user]]";
		$expectedValues[] = wfMessage( $messageKey, $target )->text();
		$this->assertSelect(
			'cu_changes',
			$fields,
			[],
			[ $expectedValues ]
		);
	}

	public function provideOnAuthManagerLoginAuthenticateAudit() {
		return [
			'successful login' => [
				AuthenticationResponse::newPass( 'UTSysop' ),
				'UTSysop',
				'checkuser-login-success',
				false
			],
			'failed login' => [
				AuthenticationResponse::newFail( wfMessage( 'test' ) ),
				'UTSysop',
				'checkuser-login-failure',
				true,
			]
		];
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 * @dataProvider provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled
	 */
	public function testOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled(
		AuthenticationResponse $ret, string $user, string $messageKey, bool $isAnonPerformer
	) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			$this->testOnAuthManagerLoginAuthenticateAudit(
				$ret, $user, $messageKey, $isAnonPerformer
			);
		} else {
			// Skip tests that will only pass if CentralAuth is installed.
			$this->expectNotToPerformAssertions();
		}
	}

	public function provideOnAuthManagerLoginAuthenticateAuditWithCentralAuthInstalled() {
		return [
			'failed login with correct password' => [
				AuthenticationResponse::newFail(
					wfMessage( 'test' ),
					// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD, but cannot be referenced
					//  directly due to T321864
					[ "good password" ]
				),
				'UTSysop',
				'checkuser-login-failure-with-good-password',
				true,
			],
			'failed login with the correct password but locked and no local account' => [
				AuthenticationResponse::newFail(
					wfMessage( 'test' ),
					// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
					//  respectively but cannot be referenced directly due to T321864
					[ "good password", "locked" ]
				),
				'Nonexisting test account 1234567',
				'checkuser-login-failure-with-good-password',
				true,
			],
			'failed login with correct password but locked' => [
				AuthenticationResponse::newFail(
					wfMessage( 'test' ),
					// This is CentralAuthUser::AUTHENTICATE_GOOD_PASSWORD and CentralAuthUser::AUTHENTICATE_LOCKED,
					//  respectively but cannot be referenced directly due to T321864
					[ "good password", "locked" ]
				),
				'UTSysop',
				'checkuser-login-failure-with-good-password',
				false,
			],
		];
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 */
	public function testCheckUserLogBotSuccessfulLoginsSetToTrue() {
		$this->setMwGlobals( 'wgCheckUserLogLogins', true );
		$user = $this->getTestUser( [ 'bot' ] )->getUserIdentity()->getName();
		$this->testOnAuthManagerLoginAuthenticateAudit(
			AuthenticationResponse::newPass( $user ),
			$user,
			'checkuser-login-success',
			false
		);
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 * @dataProvider provideOnAuthManagerLoginAuthenticateAuditNoSave
	 */
	public function testOnAuthManagerLoginAuthenticateAuditNoSave( $ret, $user, $logLogins, $logBots ) {
		$this->setMwGlobals( [
			'wgCheckUserLogLogins' => $logLogins,
			'wgCheckUserLogSuccessfulBotLogins' => $logBots
		] );
		$userObj = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $user );
		( new Hooks() )->onAuthManagerLoginAuthenticateAudit(
			$ret,
			$userObj,
			$user,
			[]
		);
		$this->assertSame(
			0,
			$this->db->newSelectQueryBuilder()
				->field( 'cuc_ip' )
				->table( 'cu_changes' )
				->fetchRowCount(),
			'A row was inserted to cu_changes when it should not have been.'
		);
	}

	public function provideOnAuthManagerLoginAuthenticateAuditNoSave() {
		$req = $this->getMockForAbstractClass( AuthenticationRequest::class );
		return [
			'invalid user' => [
				AuthenticationResponse::newPass( '' ),
				'',
				true,
				true
			],
			'Abstain authentication response' => [
				AuthenticationResponse::newAbstain(),
				'UTSysop',
				true,
				true
			],
			'Redirect authentication response' => [
				AuthenticationResponse::newRedirect( [ $req ], '' ),
				'UTSysop',
				true,
				true
			],
			'UI authentication response' => [
				AuthenticationResponse::newUI( [ $req ], wfMessage( 'test' ) ),
				'UTSysop',
				true,
				true
			],
			'Restart authentication response' => [
				AuthenticationResponse::newRestart( wfMessage( 'test' ) ),
				'UTSysop',
				true,
				true
			],
		];
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 */
	public function testCheckUserLogLoginsSetToFalse() {
		$this->testOnAuthManagerLoginAuthenticateAuditNoSave(
			AuthenticationResponse::newPass( 'UTSysop' ),
			'UTSysop',
			false,
			true
		);
	}

	/**
	 * @covers ::onAuthManagerLoginAuthenticateAudit
	 * @dataProvider provideCheckUserLogBotSuccessfulLoginsNoSave
	 */
	public function testCheckUserLogBotSuccessfulLoginsSetToFalse( $ret, $logBots ) {
		$user = $this->getTestUser( [ 'bot' ] )->getUserIdentity()->getName();
		$this->testOnAuthManagerLoginAuthenticateAuditNoSave(
			$ret,
			$user,
			true,
			$logBots
		);
	}

	public function provideCheckUserLogBotSuccessfulLoginsNoSave() {
		return [
			'Successful authentication with wgCheckUserLogSuccessfulBotLogins set to false' => [
				AuthenticationResponse::newPass( 'test' ),
				false
			]
		];
	}

	/**
	 * @covers ::onPerformRetroactiveAutoblock
	 * @dataProvider provideOnPerformRetroactiveAutoblock
	 * @todo test that the $blockIds variable is correct after calling the hook
	 */
	public function testOnPerformRetroactiveAutoblock( $isIP, $hasCUChangesRow, $shouldAutoblock ) {
		if ( $isIP ) {
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
				array_merge( $this->getDefaultRecentChangeAttribs(), [
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
	 *
	 * @return array[]
	 */
	public function provideOnPerformRetroactiveAutoblock() {
		return [
			[ true, false, false ],
			[ true, true, false ],
			[ false, false, false ],
			[ false, true, true ],
		];
	}

	/**
	 * @covers ::pruneIPData
	 * @dataProvider providePruneIPDataData
	 * @todo test when getScopedLockAndFlush() returns null.
	 */
	public function testPruneIPDataData( $currentTime, $maxCUDataAge, $timestamps, $afterCount ) {
		$this->setMwGlobals( [
			'wgCUDMaxAge' => $maxCUDataAge,
			'wgCheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_NEW
		] );
		$logEntryCutoff = $currentTime - $maxCUDataAge;
		foreach ( $timestamps as $timestamp ) {
			ConvertibleTimestamp::setFakeTime( $timestamp );
			$expectedRow = [];
			// Insertion into cu_changes
			$this->commonTestsUpdateCheckUserData( $this->getDefaultRecentChangeAttribs(), [], $expectedRow );
			// Insertion into cu_private_event
			$this->commonTestsUpdateCheckUserData(
				array_merge( $this->getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_log_type' => '' ] ),
				[],
				$expectedRow
			);
			// Insertion into cu_log_event
			$logId = $this->newLogEntry();
			$this->commonTestsUpdateCheckUserData(
				array_merge( $this->getDefaultRecentChangeAttribs(), [ 'rc_type' => RC_LOG, 'rc_logid' => $logId ] ),
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
		\DeferredUpdates::doUpdates();
		// Check that all the old entries are gone
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'cu_changes has stale entries after calling pruneIPData.', [ "cuc_timestamp < $logEntryCutoff" ] );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'cu_private_event has stale entries after calling pruneIPData.', [ "cupe_timestamp < $logEntryCutoff" ] );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'cu_log_event has stale entries after calling pruneIPData.', [ "cule_timestamp < $logEntryCutoff" ] );
		// Assert that no still in date entries were removed
		$this->assertRowCount( $afterCount, 'cu_changes', 'cuc_id',
			'cu_changes is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_private_event', 'cupe_id',
			'cu_private_event is missing rows that were not stale after calling pruneIPData.' );
		$this->assertRowCount( $afterCount, 'cu_log_event', 'cule_id',
			'cu_log_event is missing rows that were not stale after calling pruneIPData.' );
	}

	public function providePruneIPDataData() {
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
