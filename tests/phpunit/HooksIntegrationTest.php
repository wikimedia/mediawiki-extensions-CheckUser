<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\Hooks;
use MediaWikiIntegrationTestCase;
use RecentChange;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @coversDefaultClass \MediaWiki\CheckUser\Hooks
 */
class HooksIntegrationTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->tablesUsed = [
			'cu_changes'
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
	 */
	public function testOnUserMergeAccountFields() {
		$updateFields = [];
		Hooks::onUserMergeAccountFields( $updateFields );
		$this->assertCount(
			3,
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
		\RequestContext::getMain()->setRequest( $request->object );
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
	 * @covers ::updateCheckUserData
	 * @dataProvider provideUpdateCheckUserData
	 */
	public function testUpdateCheckUserData( $rcAttribs, $fields, $expectedRow ) {
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		$this->assertTrue(
			$this->setUpObject()->updateCheckUserData( $rc ),
			'updateCheckUserData should return true.'
		);
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
		$rc = new RecentChange;
		$rc->setAttribs( $rcAttribs );
		$this->assertTrue(
			$this->setUpObject()->updateCheckUserData( $rc ),
			'updateCheckUserData should return true.'
		);
		$db = $this->getServiceContainer()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$this->assertSame(
			0,
			$db->newSelectQueryBuilder()
				->field( 'cuc_ip' )
				->table( 'cu_changes' )
				->fetchRowCount(),
			'A row was inserted to cu_changes when it should not have been.'
		);
	}

	public function getDefaultRecentChangeAttribs() {
		// From RecentChangeTest.php's provideAttribs but modified
		return [
			'rc_timestamp' => wfTimestamp( TS_MW ),
			'rc_namespace' => NS_USER,
			'rc_title' => 'Tony',
			'rc_type' => RC_EDIT,
			'rc_minor' => 0,
			'rc_cur_id' => 77,
			'rc_user' => 858173476,
			'rc_user_text' => 'Tony',
			'rc_comment' => '',
			'rc_comment_text' => '',
			'rc_comment_data' => null,
			'rc_this_oldid' => 70,
			'rc_last_oldid' => 71,
		];
	}

	public function provideUpdateCheckUserData() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = $this->getDefaultRecentChangeAttribs();
		yield 'anon user' => [
			[
				'rc_type' => RC_EDIT,
				'rc_user' => 0,
				'rc_user_text' => '192.168.0.1',
			] + $attribs,
			[ 'cuc_user_text', 'cuc_user', 'cuc_type' ],
			[ '192.168.0.1', 0, RC_EDIT ]
		];

		yield 'registered user' => [
			[
				'rc_type' => RC_EDIT,
				'rc_user' => 5,
				'rc_user_text' => 'Test',
			] + $attribs,
			[ 'cuc_user_text', 'cuc_user' ],
			[ 'Test', 5 ]
		];

		yield 'special title' => [
			[
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
			] + $attribs,
			[ 'cuc_title', 'cuc_timestamp', 'cuc_namespace', 'cuc_type' ],
			[ 'Log', $attribs['rc_timestamp'], NS_SPECIAL, RC_LOG ]
		];
	}

	public function provideUpdateCheckUserDataNoSave() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = $this->getDefaultRecentChangeAttribs();
		yield 'external user' => [
			[
				'rc_type' => RC_EXTERNAL,
				'rc_user' => 0,
				'rc_user_text' => 'm>External User',
			] + $attribs,
			[ 'cuc_ip' ],
			[]
		];

		yield 'categorize' => [
			[
				'rc_namespace' => NS_MAIN,
				'rc_title' => '',
				'rc_type' => RC_CATEGORIZE,
			] + $attribs,
			[ 'cuc_ip' ],
			[]
		];
	}
}
