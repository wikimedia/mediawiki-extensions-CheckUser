<?php

namespace MediaWiki\CheckUser\Tests;

use DeferredUpdates;
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
 */
class CheckUserLogServiceTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_log',
			]
		);

		$this->truncateTable( 'cu_log' );
	}

	protected function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject( $this->getServiceContainer()->get( 'CheckUserLogService' ) );
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

	/**
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
	 */
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
			[ [ $this->getServiceContainer()->getActorStore()->acquireActorId( $user, $this->db ) ] ]
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
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
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
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
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
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
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
	 */
	public function testAddLogEntryPerformer() {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', '', 0 );
		DeferredUpdates::doUpdates();
		$this->assertSelect(
			'cu_log',
			[ 'cul_user', 'cul_user_text' ],
			[],
			[ [ $testUser->getId(), $testUser->getName() ] ]
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUserLogService::addLogEntry
	 */
	public function testAddLogEntryActor() {
		$object = $this->setUpObject();
		if ( $object->culActorMigrationStage & SCHEMA_COMPAT_WRITE_NEW ) {
			$testUser = $this->getTestUser( 'checkuser' )->getUser();
			$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', '', 0 );
			\DeferredUpdates::doUpdates();
			$this->assertSelect(
				'cu_log',
				[ 'cul_actor' ],
				[],
				[ [ $testUser->getActorId() ] ]
			);
		} else {
			$this->expectNotToPerformAssertions();
		}
	}
}
