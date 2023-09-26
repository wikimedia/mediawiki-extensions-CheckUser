<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use DeferredUpdates;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserLogService
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserLogService
 */
class CheckUserLogServiceTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_log',
				'comment',
			]
		);

		$this->truncateTable( 'cu_log' );
	}

	protected function setUpObject(): CheckUserLogService {
		return $this->getServiceContainer()->get( 'CheckUserLogService' );
	}

	public function commonTestAddLogEntry(
		$logType, $targetType, $target, $reason, $targetID, $assertSelectFieldNames, $assertSelectFieldValues
	) {
		$object = $this->setUpObject();
		$object->addLogEntry(
			$this->getTestUser( 'checkuser' )->getUser(), $logType, $targetType, $target, $reason, $targetID
		);
		DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			$assertSelectFieldNames,
			[],
			[ $assertSelectFieldValues ]
		);
	}

	public function testPerformerIsIP() {
		// Test that an IP performing a check actually saves a cu_log entry
		// as if the checkuser right is granted to all users (i.e the * group)
		// then any checks should definitely still be logged.
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
			UserIdentityValue::newAnonymous( '127.0.0.1' )
		);
		$object = $this->setUpObject();
		$object->addLogEntry( $user, 'ipusers', 'ip', '127.0.0.1', 'test', 0 );
		DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			[ 'cul_actor' ],
			[],
			[ [ $this->getServiceContainer()->getActorStore()->acquireActorId( $user, $this->getDb() ) ] ]
		);
	}

	/** @dataProvider provideAddLogEntryIPs */
	public function testAddLogEntryIPs(
		$logType, $target, $reason, $assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry( $logType, 'ip', $target, $reason, 0,
			[ 'cul_target_id', 'cul_type', 'cul_target_text', 'cul_target_hex', 'cul_range_start', 'cul_range_end' ],
			array_merge( [ 0 ], $assertSelectFieldValues )
		);
	}

	public static function provideAddLogEntryIPs() {
		return [
			[
				'ipusers', '127.0.0.1', 'test',
				[ 'ipusers', '127.0.0.1', '7F000001', '', '' ],
			],
			[
				'ipedits', '1.2.3.4', 'testing',
				[ 'ipedits', '1.2.3.4', '01020304', '', '' ],
			],
			[
				'ipedits', '1.2.3.4/21', 'testing',
				[ 'ipedits', '1.2.3.4/21', '01020000', '01020000', '010207FF' ],
			],
		];
	}

	/** @dataProvider provideAddLogEntryUsers */
	public function testAddLogEntryUser(
		$logType, UserIdentity $target, $reason, $assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry( $logType, 'user', $target->getName(), $reason, $target->getId(),
			[ 'cul_target_hex', 'cul_range_start', 'cul_range_end', 'cul_type', 'cul_target_text', 'cul_target_id' ],
			array_merge( [ '', '', '' ], $assertSelectFieldValues )
		);
	}

	public static function provideAddLogEntryUsers() {
		return [
			[
				'userips', UserIdentityValue::newRegistered( 3, 'Test' ), 'test',
				[ 'userips', 'Test', 3 ],
			],
			[
				'useredits', UserIdentityValue::newRegistered( 10, 'Testing' ), 'test',
				[ 'useredits', 'Testing', 10 ],
			],
			[
				'useredits', UserIdentityValue::newRegistered( 2, 'Testing1234' ), 'test',
				[ 'useredits', 'Testing1234', 2 ],
			],
		];
	}

	/** @dataProvider provideAddLogEntryTimestamp */
	public function testAddLogEntryTimestamp( $timestamp ) {
		ConvertibleTimestamp::setFakeTime( $timestamp );
		$testUser = $this->getTestUser()->getUserIdentity();
		$this->commonTestAddLogEntry(
			'ipusers', 'user', $testUser->getName(), 'testing', $testUser->getId(),
			[ 'cul_timestamp' ], [ $this->db->timestamp( $timestamp ) ]
		);
	}

	public static function provideAddLogEntryTimestamp() {
		return [
			[ '1653047635' ],
			[ '1653042345' ]
		];
	}

	public function testAddLogEntryPerformer() {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', '', 0 );
		DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			[ 'cul_actor' ],
			[],
			[ [ $testUser->getActorId() ] ]
		);
	}

	/** @dataProvider provideAddLogEntryReasonId */
	public function testAddLogEntryReasonId( $reason, $expectedPlaintextReason ) {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', $reason, 0 );
		DeferredUpdates::doUpdates();
		$commentQuery = $this->getServiceContainer()->getCommentStore()->getJoin( 'cul_reason' );
		$commentQuery['tables'][] = 'cu_log';
		$row = $this->db->newSelectQueryBuilder()
			->fields( $commentQuery['fields'] )
			->tables( $commentQuery['tables'] )
			->joinConds( $commentQuery['joins'] )
			->fetchRow();
		$this->assertSame(
			$reason,
			$row->cul_reason_text,
			'The reason saved was not correctly saved.'
		);

		$commentQuery = $this->getServiceContainer()->getCommentStore()->getJoin( 'cul_reason_plaintext' );
		$commentQuery['tables'][] = 'cu_log';
		$row = $this->db->newSelectQueryBuilder()
			->fields( $commentQuery['fields'] )
			->tables( $commentQuery['tables'] )
			->joinConds( $commentQuery['joins'] )
			->fetchRow();
		$this->assertSame(
			$expectedPlaintextReason,
			$row->cul_reason_plaintext_text,
			'The plaintext reason saved was not correctly saved.'
		);
	}

	/** @dataProvider provideAddLogEntryReasonId */
	public function testGetPlaintextReason( $reason, $expectedPlaintextReason ) {
		$this->assertSame(
			$expectedPlaintextReason,
			$this->setUpObject()->getPlaintextReason( $reason ),
			'Returned plaintext reason did not match expected plaintext reason.'
		);
	}

	public static function provideAddLogEntryReasonId() {
		return [
			[ 'Testing 1234', 'Testing 1234' ],
			[ 'Testing 1234 [[test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [[:mw:Testing|test]]', 'Testing 1234 test' ],
			[ 'Testing 1234 [test]', 'Testing 1234 [test]' ],
			[ 'Testing 1234 [https://example.com]', 'Testing 1234 [https://example.com]' ],
			[ 'Testing 1234 [[test]', 'Testing 1234 [[test]' ],
			[ 'Testing 1234 [test]]', 'Testing 1234 [test]]' ],
			[ 'Testing 1234 <var>', 'Testing 1234 <var>' ],
			[ 'Testing 1234 {{test}}', 'Testing 1234 {{test}}' ],
			[ 'Testing 12345 [[{{test}}]]', 'Testing 12345 [[{{test}}]]' ],
		];
	}
}
