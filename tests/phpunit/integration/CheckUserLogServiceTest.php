<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserLogService
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUserLogService
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserLogService
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

	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject( $this->getServiceContainer()->get( 'CheckUserLogService' ) );
	}

	public function commonTestAddLogEntry(
		$logType, $targetType, $target, $reason, $targetID, $assertSelectFieldNames, $assertSelectFieldValues
	) {
		$object = $this->setUpObject();
		$object->addLogEntry(
			$this->getTestUser( 'checkuser' )->getUser(), $logType, $targetType, $target, $reason, $targetID
		);
		\DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			$assertSelectFieldNames,
			[],
			[ $assertSelectFieldValues ]
		);
	}

	/**
	 * @covers ::addLogEntry
	 * @dataProvider provideAddLogEntryIPs
	 */
	public function testAddLogEntryIPs(
		$logType, $target, $reason, $assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry( $logType, 'ip', $target, $reason, 0,
			[ 'cul_target_id', 'cul_type', 'cul_target_text', 'cul_target_hex', 'cul_range_start', 'cul_range_end' ],
			array_merge( [ 0 ], $assertSelectFieldValues )
		);
	}

	public function provideAddLogEntryIPs() {
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

	/**
	 * @covers ::addLogEntry
	 * @dataProvider provideAddLogEntryUsers
	 */
	public function testAddLogEntryUser(
		$logType, UserIdentity $target, $reason, $assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry( $logType, 'user', $target->getName(), $reason, $target->getId(),
			[ 'cul_target_hex', 'cul_range_start', 'cul_range_end', 'cul_type', 'cul_target_text', 'cul_target_id' ],
			array_merge( [ '', '', '' ], $assertSelectFieldValues )
		);
	}

	public function provideAddLogEntryUsers() {
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

	/**
	 * @covers ::addLogEntry
	 * @dataProvider provideAddLogEntryTimestamp
	 */
	public function testAddLogEntryTimestamp( $timestamp ) {
		ConvertibleTimestamp::setFakeTime( $timestamp );
		$testUser = $this->getTestUser()->getUserIdentity();
		$this->commonTestAddLogEntry(
			'ipusers', 'user', $testUser->getName(), 'testing', $testUser->getId(),
			[ 'cul_timestamp' ], [ $this->db->timestamp( $timestamp ) ]
		);
	}

	public function provideAddLogEntryTimestamp() {
		return [
			[ '1653047635' ],
			[ '1653042345' ]
		];
	}

	/**
	 * @covers ::addLogEntry
	 */
	public function testAddLogEntryPerformer() {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', '', 0 );
		\DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			[ 'cul_actor' ],
			[],
			[ [ $testUser->getActorId() ] ]
		);
	}

	/**
	 * Tests that nothing is written to
	 * the cul_reason_id or cul_reason_plaintext_id
	 * if the migration stage doesn't include write new.
	 *
	 * @covers ::addLogEntry
	 */
	public function testAddLogEntryReasonIdNoWriteNew() {
		$object = $this->setUpObject();
		// 3 means READ_OLD and WRITE_OLD
		$object->culReasonMigrationStage = 3;
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', 'Test', 0 );
		\DeferredUpdates::doUpdates();
		// Be sure that the fields exist before testing.
		if (
			$this->db->fieldExists( 'cu_log', 'cul_reason_id' ) &&
			$this->db->fieldExists( 'cu_log', 'cul_reason_plaintext_id' )
		) {
			$this->assertSelect(
				'cu_log',
				[ 'cul_reason_plaintext_id', 'cul_reason_id' ],
				[],
				[ [ 0, 0 ] ]
			);
		} else {
			$this->expectNotToPerformAssertions();
		}
	}

	/**
	 * @covers ::addLogEntry
	 * @dataProvider provideAddLogEntryReasonId
	 */
	public function testAddLogEntryReasonId( $reason, $expectedPlaintextReason ) {
		$object = $this->setUpObject();
		// Only attempt the test if culReasonMigrationStage says we can write to the new.
		if ( $object->culReasonMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$testUser = $this->getTestUser( 'checkuser' )->getUser();
			$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', $reason, 0 );
			\DeferredUpdates::doUpdates();
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
		} else {
			$this->expectNotToPerformAssertions();
		}
	}

	/**
	 * @covers ::getPlaintextReason
	 * @dataProvider provideAddLogEntryReasonId
	 */
	public function testGetPlaintextReason( $reason, $expectedPlaintextReason ) {
		$this->assertSame(
			$expectedPlaintextReason,
			$this->setUpObject()->getPlaintextReason( $reason ),
			'Returned plaintext reason did not match expected plaintext reason.'
		);
	}

	public function provideAddLogEntryReasonId() {
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
