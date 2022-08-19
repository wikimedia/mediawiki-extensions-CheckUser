<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MailAddress;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RecentChange;
use RequestContext;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @coversDefaultClass \MediaWiki\CheckUser\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	public function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_changes',
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
	 * @todo Need to test xff and timestamp(?)
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

	public function provideInsertIntoCuChangesTable() {
		return [
			[ [], [ 'cuc_ip' ], [ '127.0.0.1' ] ],
		];
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
	 * A function to remove duplication for common tests
	 * across all the updateCheckUserData tests below.
	 * Called by the individual tests themselves.
	 *
	 * @param array $rcAttribs The attribs for the RecentChange object
	 * @param array $fields The fields to select from the DB when using assertSelect()
	 * @param array &$expectedRow The expected values for the fields from the DB when using assertSelect()
	 * @return RecentChange
	 */
	public function commonTestsUpdateCheckUserData(
		array $rcAttribs, array $fields, array &$expectedRow
	): RecentChange {
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		$this->setUpObject()->updateCheckUserData( $rc );
		foreach ( $fields as $index => $field ) {
			if ( $field === 'cuc_timestamp' ) {
				$expectedRow[$index] = $this->db->timestamp( $expectedRow[$index] );
			}
		}
		return $rc;
	}

	/**
	 * @covers ::updateCheckUserData
	 * @dataProvider provideUpdateCheckUserData
	 */
	public function testUpdateCheckUserData( $rcAttribs, $fields, $expectedRow ) {
		$this->commonTestsUpdateCheckUserData( $rcAttribs, $fields, $expectedRow );
		$this->assertSelect(
			'cu_changes',
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
		$this->assertSame(
			0,
			$this->db->newSelectQueryBuilder()
				->field( 'cuc_ip' )
				->table( 'cu_changes' )
				->fetchRowCount(),
			'A row was inserted to cu_changes when it should not have been.'
		);
	}

	public function getDefaultRecentChangeAttribs() {
		// From RecentChangeTest.php's provideAttribs
		return [
			'rc_timestamp' => wfTimestamp( TS_MW ),
			'rc_namespace' => NS_USER,
			'rc_title' => 'Tony',
			'rc_type' => RC_EDIT,
			'rc_source' => RecentChange::SRC_EDIT,
			'rc_minor' => 0,
			'rc_cur_id' => 77,
			'rc_user' => 858173476,
			'rc_user_text' => 'Tony',
			'rc_comment' => '',
			'rc_comment_text' => '',
			'rc_comment_data' => null,
			'rc_this_oldid' => 70,
			'rc_last_oldid' => 71,
			'rc_bot' => 0,
			'rc_ip' => '',
			'rc_patrolled' => 0,
			'rc_new' => 0,
			'rc_old_len' => 80,
			'rc_new_len' => 88,
			'rc_deleted' => 0,
			'rc_logid' => 0,
			'rc_log_type' => null,
			'rc_log_action' => '',
			'rc_params' => '',
		];
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
			[ 'cuc_user_text', 'cuc_user', 'cuc_type' ],
			[ '192.168.0.1', 0, RC_EDIT ]
		];

		yield 'registered user' => [
			array_merge( $attribs, [
				'rc_type' => RC_EDIT,
				'rc_user' => 5,
				'rc_user_text' => 'Test',
			] ),
			[ 'cuc_user_text', 'cuc_user' ],
			[ 'Test', 5 ]
		];

		yield 'special title' => [
			array_merge( $attribs, [
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
			] ),
			[ 'cuc_title', 'cuc_timestamp', 'cuc_namespace', 'cuc_type' ],
			[ 'Log', $attribs['rc_timestamp'], NS_SPECIAL, RC_LOG ]
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

	/**
	 * @covers ::onUser__mailPasswordInternal
	 */
	public function testonUser__mailPasswordInternal() {
		$performer = $this->getTestUser()->getUser();
		$account = $this->getTestSysop()->getUser();
		( new Hooks() )->onUser__mailPasswordInternal( $performer, 'IGNORED', $account );
		$this->assertSame(
			1,
			$this->db->newSelectQueryBuilder()
				->table( 'cu_changes' )
				->where( [
					'cuc_user' => $performer->getId(),
					'cuc_user_text' => $performer->getName(),
					'cuc_actiontext' . $this->db->buildLike(
						$this->db->anyString(),
						'[[User:', $account->getName(), '|', $account->getName(), ']]',
						$this->db->anyString()
					)
				] )
				->fetchRowCount(),
			'The row was not inserted or was inserted with the wrong data'
		);
	}

	/**
	 * @covers ::onLocalUserCreated
	 * @dataProvider provideOnLocalUserCreated
	 */
	public function testOnLocalUserCreated( $autocreated ) {
		$user = $this->getTestUser()->getUser();
		( new Hooks() )->onLocalUserCreated( $user, $autocreated );
		$this->assertSelect(
			'cu_changes',
			[ 'cuc_namespace', 'cuc_actiontext', 'cuc_user', 'cuc_user_text' ],
			[],
			[ [
				NS_USER,
				wfMessage(
					$autocreated ? 'checkuser-autocreate-action' : 'checkuser-create-action'
				)->inContentLanguage()->text(),
				$user->getId(),
				$user->getName()
			] ]
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
		$subject = '';
		$text = '';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		$this->assertSame(
			0,
			$this->db->newSelectQueryBuilder()
				->field( 'cuc_ip' )
				->table( 'cu_changes' )
				->fetchRowCount(),
			'A row was inserted to cu_changes when it should not have been.'
		);
	}

	public function provideTestOnEmailUserNoSave() {
		return [
			[
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

	public function commonOnEmailUser( $to, $from, $fields, $expectedValues ) {
		$subject = 'Test subject';
		$text = 'Test text';
		$error = false;
		( new Hooks() )->onEmailUser( $to, $from, $subject, $text, $error );
		\DeferredUpdates::doUpdates();
		$fields[] = 'cuc_namespace';
		$expectedValues[] = NS_USER;
		$this->assertSelect(
			'cu_changes',
			$fields,
			[],
			[ $expectedValues ]
		);
	}

	/**
	 * @covers ::onEmailUser
	 */
	public function testOnEmailUserFrom() {
		$userTo = $this->getTestUser()->getUserIdentity();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[ 'cuc_user', 'cuc_user_text' ],
			[ $userFrom->getId(), $userFrom->getName() ]
		);
	}

	/**
	 * @covers ::onEmailUser
	 */
	public function testOnEmailUserActionText() {
		global $wgSecretKey;
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$expectedActionText = wfMessage(
			'checkuser-email-action',
			md5( $userTo->getEmail() . $userTo->getId() . $wgSecretKey )
		)->inContentLanguage()->text();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[ 'cuc_actiontext' ],
			[ $expectedActionText ]
		);
	}

	/**
	 * @covers ::onEmailUser
	 * @todo test when the wgCUPublicKey is set and that the cuc_private is correct - to do this there would need
	 *   to be a test open_ssl key pair included here with encryption / decryption.
	 */
	public function testOnEmailUserPrivateInfoNoPublicKey() {
		$this->setMwGlobals( [
			'wgCUPublicKey' => ' '
		] );
		$userTo = $this->getTestUser()->getUser();
		$userFrom = $this->getTestSysop()->getUserIdentity();
		$this->commonOnEmailUser(
			new MailAddress( 'test@test.com', $userTo->getName() ),
			new MailAddress( 'testing@test.com', $userFrom->getName() ),
			[ 'cuc_private' ],
			[ '' ]
		);
	}

	/**
	 * @covers ::onRecentChange_save
	 * @dataProvider provideUpdateCheckUserData
	 * @todo test that maybePruneIPData() is called?
	 */
	public function testonRecentChange_save( $rcAttribs, $fields, $expectedRow ) {
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		( new Hooks() )->onRecentChange_save( $rc );
		foreach ( $fields as $index => $field ) {
			if ( $field === 'cuc_timestamp' ) {
				$expectedRow[$index] = $this->db->timestamp( $expectedRow[$index] );
			}
		}
		$this->assertSelect(
			'cu_changes',
			$fields,
			'',
			[ $expectedRow ]
		);
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
}
