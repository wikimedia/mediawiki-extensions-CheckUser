<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Profiler\Profiler;
use MediaWiki\Tests\Unit\Libs\Rdbms\AddQuoterMock;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserLogService
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserLogService
 */
class CheckUserLogServiceTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	protected function tearDown(): void {
		Profiler::instance()->getTransactionProfiler()->resetExpectations();
	}

	protected function setUpObject(): CheckUserLogService {
		return $this->getServiceContainer()->get( 'CheckUserLogService' );
	}

	public function commonTestAddLogEntry(
		$logType,
		$targetType,
		$target,
		$reason,
		$targetID,
		$assertSelectFieldNames,
		$assertSelectFieldValues
	): void {
		$object = $this->setUpObject();
		$object->addLogEntry(
			$this->getTestUser( 'checkuser' )->getUser(),
			$logType,
			$targetType,
			$target,
			$reason,
			$targetID
		);
		DeferredUpdates::doUpdates();
		$this->newSelectQueryBuilder()
			->select( $assertSelectFieldNames )
			->from( 'cu_log' )
			->assertRowValue( $assertSelectFieldValues );
	}

	public function testPerformerIsIP() {
		// Cannot have an IP actor if autocreation of temporary accounts is enabled,
		// so disable it.
		$this->disableAutoCreateTempUser();
		// Test that an IP performing a check actually saves a cu_log entry
		// as if the checkuser right is granted to all users (i.e the * group)
		// then any checks should definitely still be logged.
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity(
			UserIdentityValue::newAnonymous( '127.0.0.1' )
		);
		$object = $this->setUpObject();
		$object->addLogEntry( $user, 'ipusers', 'ip', '127.0.0.1', 'test', 0 );
		DeferredUpdates::doUpdates();
		$this->newSelectQueryBuilder()
			->select( 'cul_actor' )
			->from( 'cu_log' )
			->assertFieldValue(
				$this->getServiceContainer()->getActorStore()->acquireActorId( $user, $this->getDb() )
			);
	}

	/** @dataProvider provideAddLogEntryIPs */
	public function testAddLogEntryIPs(
		$logType,
		$target,
		$reason,
		$assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry(
			$logType,
			'ip',
			$target,
			$reason,
			0,
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
		$logType,
		UserIdentity $target,
		$reason,
		$assertSelectFieldValues
	) {
		$this->commonTestAddLogEntry(
			$logType,
			'user',
			$target->getName(),
			$reason,
			$target->getId(),
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
			'ipusers',
			'user',
			$testUser->getName(),
			'testing',
			$testUser->getId(),
			[ 'cul_timestamp' ],
			[ $this->getDb()->timestamp( $timestamp ) ]
		);
	}

	public static function provideAddLogEntryTimestamp() {
		return [
			[ '1653047635' ],
			[ '1653042345' ],
		];
	}

	public function testAddLogEntryPerformer() {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', '', 0 );
		DeferredUpdates::doUpdates();
		$this->newSelectQueryBuilder()
			->select( 'cul_actor' )
			->from( 'cu_log' )
			->assertFieldValue( $testUser->getActorId() );
	}

	/** @dataProvider provideAddLogEntryReasonId */
	public function testAddLogEntryReasonId( $reason, $expectedPlaintextReason ) {
		$object = $this->setUpObject();
		$testUser = $this->getTestUser( 'checkuser' )->getUser();
		$object->addLogEntry( $testUser, 'ipusers', 'ip', '127.0.0.1', $reason, 0 );
		DeferredUpdates::doUpdates();
		$commentQuery = $this->getServiceContainer()->getCommentStore()->getJoin( 'cul_reason' );
		$commentQuery['tables'][] = 'cu_log';
		$row = $this->getDb()->newSelectQueryBuilder()
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
		$row = $this->getDb()->newSelectQueryBuilder()
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

	public function testAddLogEntryWhenInsertCommentThrows(): void {
		// Avoid logging the DBError in the exception channel, as this fails the test in CI if logged there
		// by the DeferredUpdates handler
		$this->setLogger( 'exception', new NullLogger() );

		$mockCommentStore = $this->createMock( CommentStore::class );
		$mockCommentStore->method( 'insert' )
			->willThrowException( new DBError( $this->getDb(), 'test' ) );
		$this->setService( 'CommentStore', $mockCommentStore );

		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'critical' )
			->with(
				'CheckUserLog entry was not recorded. This means checks can occur without being auditable. ' .
				'Immediate fix required.'
			);
		$this->setLogger( 'CheckUser', $mockLogger );

		$this->expectException( DBError::class );
		$this->setUpObject()->addLogEntry(
			$this->getTestUser( 'checkuser' )->getUser(),
			'ipusers',
			'ip',
			'1.2.3.4',
			'testing'
		);
	}

	public function testAddLogEntryWhenSilencingReplicaOnlyExpectations(): void {
		Profiler::instance()->getTransactionProfiler()->setExpectation(
			'writes',
			0,
			__METHOD__
		);

		$checkuser = $this->getTestUser( 'checkuser' )->getUser();

		$realCommentStore = $this->getServiceContainer()->getCommentStore();
		$mockCommentStore = $this->createMock( CommentStore::class );
		$mockCommentStore->expects( $this->atLeastOnce() )
			->method( 'insert' )
			->willReturnCallback( function ( IDatabase $dbw, $key, $text ) use ( $realCommentStore ) {
				$this->assertTrue( Profiler::instance()->getTransactionProfiler()->isSilenced( 'writes' ) );
				return $realCommentStore->insert( $dbw, $key, $text );
			} );
		$this->setService( 'CommentStore', $mockCommentStore );

		$realActorStore = $this->getServiceContainer()->getActorStore();
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockActorStore->expects( $this->atLeastOnce() )
			->method( 'acquireActorId' )
			->willReturnCallback( function ( UserIdentity $user, IDatabase $dbw ) use ( $realActorStore ) {
				$this->assertTrue( Profiler::instance()->getTransactionProfiler()->isSilenced( 'writes' ) );
				return $realActorStore->acquireActorId( $user, $dbw );
			} );
		$this->setService( 'ActorStore', $mockActorStore );

		$this->setUpObject()->addLogEntry(
			$checkuser,
			'ipusers',
			'ip',
			'1.2.3.4',
			'testing',
			silenceReplicasOnlyExpectations: true
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

	public function testGetTargetSearchCondsUser() {
		// Tests the conditions are as expected when the target is an existing user.
		$object = $this->setUpObject();
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$actualResult = $object->getTargetSearchConds( $testUser->getName() );
		foreach ( $actualResult as &$result ) {
			if ( $result instanceof IExpression ) {
				$result = $result->toSql( new AddQuoterMock() );
			}
		}
		$this->assertArrayEquals(
			$this->getExpectedGetTargetSearchConds( 'user', $testUser->getId() ),
			$actualResult,
			false,
			true,
			'For an existing user the valid search cond should be returned.'
		);
	}

	/** @dataProvider provideGetTargetSearchCondsIP */
	public function testGetTargetSearchCondsIP( $target, $type, $start, $end ) {
		$this->overrideConfigValue( 'CheckUserLogMaxRangeToShowInLog', false );

		$object = $this->setUpObject();
		$actualResult = $object->getTargetSearchConds( $target );
		foreach ( $actualResult as &$result ) {
			if ( $result instanceof IExpression ) {
				$result = $result->toSql( new AddQuoterMock() );
			}
		}
		$this->assertArrayEquals(
			$this->getExpectedGetTargetSearchConds( $type, null, $start, $end ),
			$actualResult,
			false,
			true,
			'Valid IP addresses should have associated search conditions.'
		);
	}

	public static function provideGetTargetSearchCondsIP(): array {
		return [
			'Single IP' => [ '124.0.0.0', 'ip', '7C000000', '7C000000' ],
			'/24 IP range' => [ '124.0.0.0/24', 'range', '7C000000', '7C0000FF' ],
			'/16 IP range' => [ '124.0.0.0/16', 'range', '7C000000', '7C00FFFF' ],
			'Single IP notated as a /32 range' => [ '1.2.3.4/32', 'ip', '01020304', '01020304' ],
			'Single IPv6' => [ '::e:f:2001', 'ip',
				'v6-00000000000000000000000E000F2001',
				'v6-00000000000000000000000E000F2001',
			],
			'/96 IPv6 range' => [ '::e:f:2001/96', 'range',
				'v6-00000000000000000000000E00000000',
				'v6-00000000000000000000000EFFFFFFFF',
			],
		];
	}

	/** @dataProvider provideGetTargetSearchCondsIPWhenMaxRangeConfigSet */
	public function testGetTargetSearchCondsIPWhenMaxRangeConfigSet(
		array $maxRangeToShowInLogConfig,
		string $target,
		string $type,
		string $expectedStart,
		string $expectedEnd,
		?string $expectedMinRangeStart,
		?string $expectedMaxRangeEnd
	) {
		$this->overrideConfigValue( 'CheckUserLogMaxRangeToShowInLog', $maxRangeToShowInLogConfig );

		$object = $this->setUpObject();
		$actualResult = $object->getTargetSearchConds( $target );
		foreach ( $actualResult as &$result ) {
			if ( $result instanceof IExpression ) {
				$result = $result->toSql( new AddQuoterMock() );
			}
		}
		$this->assertArrayEquals(
			$this->getExpectedGetTargetSearchConds(
				$type,
				null,
				$expectedStart,
				$expectedEnd,
				$expectedMinRangeStart,
				$expectedMaxRangeEnd
			),
			$actualResult,
			false,
			true,
			'Valid IP addresses should have associated search conditions.'
		);
	}

	public static function provideGetTargetSearchCondsIPWhenMaxRangeConfigSet(): array {
		return [
			'Single IPv4 address' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 16, 'IPv6' => 19 ],
				'target' => '124.1.0.0',
				'type' => 'ip',
				'expectedStart' => '7C010000',
				'expectedEnd' => '7C010000',
				'expectedMinRangeStart' => '7C010000',
				'expectedMaxRangeEnd' => '7C01FFFF',
			],
			'/24 IP range' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 10, 'IPv6' => 19 ],
				'target' => '124.0.0.0/24',
				'type' => 'range',
				'expectedStart' => '7C000000',
				'expectedEnd' => '7C0000FF',
				'expectedMinRangeStart' => '7C000000',
				'expectedMaxRangeEnd' => '7C3FFFFF',
			],
			'/8 IPv4 range' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 16, 'IPv6' => 19 ],
				'target' => '124.0.0.0/8',
				'type' => 'range',
				'expectedStart' => '7C000000',
				'expectedEnd' => '7CFFFFFF',
				'expectedMinRangeStart' => null,
				'expectedMaxRangeEnd' => null,
			],
			'Single IPv6 address' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 16, 'IPv6' => 19 ],
				'target' => '::e:f:2001',
				'type' => 'ip',
				'expectedStart' => 'v6-00000000000000000000000E000F2001',
				'expectedEnd' => 'v6-00000000000000000000000E000F2001',
				'expectedMinRangeStart' => 'v6-00000000000000000000000000000000',
				'expectedMaxRangeEnd' => 'v6-00001FFFFFFFFFFFFFFFFFFFFFFFFFFF',
			],
			'/96 IPv6 range' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 16, 'IPv6' => 76 ],
				'target' => '::e:f:2001/96',
				'type' => 'range',
				'expectedStart' => 'v6-00000000000000000000000E00000000',
				'expectedEnd' => 'v6-00000000000000000000000EFFFFFFFF',
				'expectedMinRangeStart' => 'v6-00000000000000000000000000000000',
				'expectedMaxRangeEnd' => 'v6-0000000000000000000FFFFFFFFFFFFF',
			],
			'/7 IPv6 range' => [
				'maxRangeToShowInLogConfig' => [ 'IPv4' => 16, 'IPv6' => 19 ],
				'target' => '::e:f:2001/7',
				'type' => 'range',
				'expectedStart' => 'v6-00000000000000000000000000000000',
				'expectedEnd' => 'v6-01FFFFFFFFFFFFFFFFFFFFFFFFFFFFFF',
				'expectedMinRangeStart' => null,
				'expectedMaxRangeEnd' => null,
			],
		];
	}

	/**
	 * Gets the expected SQL where conditions that should be returned by
	 * {@link CheckUserLogService::getTargetSearchConds}.
	 */
	private function getExpectedGetTargetSearchConds(
		string $type,
		?int $id,
		?string $start = null,
		?string $end = null,
		?string $minRangeStart = null,
		?string $maxRangeEnd = null
	): ?array {
		switch ( $type ) {
			case 'ip':
				$expectedSql = "(cul_target_hex = '$start' OR " .
					"(cul_range_end >= '$start' AND " .
					"cul_range_start <= '$start'";
				if ( $minRangeStart !== null ) {
					$expectedSql .= " AND cul_range_start >= '$minRangeStart'";
				}
				if ( $maxRangeEnd !== null ) {
					$expectedSql .= " AND cul_range_end <= '$maxRangeEnd'";
				}
				$expectedSql .= '))';

				return [ $expectedSql ];
			case 'range':
				$expectedSql = "((cul_target_hex >= '$start' AND " .
					"cul_target_hex <= '$end') OR " .
					"(cul_range_end >= '$start' AND " .
					"cul_range_start <= '$end'";
				if ( $minRangeStart !== null ) {
					$expectedSql .= " AND cul_range_start >= '$minRangeStart'";
				}
				if ( $maxRangeEnd !== null ) {
					$expectedSql .= " AND cul_range_end <= '$maxRangeEnd'";
				}
				$expectedSql .= '))';

				return [ $expectedSql ];
			case 'user':
				if ( $id === null ) {
					return null;
				}
				return [
					'cul_target_id' => $id,
				];
			default:
				$this->fail( 'getExpectedGetTargetSearchConds() got an unexpected type.' );
		}
	}

	/** @dataProvider provideHasLogEntrySince */
	public function testHasLogEntrySince(
		string $logType,
		string $target,
		int $sinceOffset,
		bool $expected
	): void {
		ConvertibleTimestamp::setFakeTime( '20250101000000' );
		$performer = $this->getTestUser( 'checkuser' )->getUserIdentity();
		$object = $this->setUpObject();
		$object->addLogEntry( $performer, 'af-view-protected-var-value', 'user', 'Target', '' );
		DeferredUpdates::doUpdates();

		$this->assertSame(
			$expected,
			$object->hasLogEntrySince(
				$performer,
				$logType,
				$target,
				ConvertibleTimestamp::time() + $sinceOffset
			),
			'::hasLogEntrySince returned an unexpected value'
		);
	}

	public static function provideHasLogEntrySince(): iterable {
		return [
			'Matching entry after the timestamp' => [
				'logType' => 'af-view-protected-var-value',
				'target' => 'Target',
				'sinceOffset' => -60,
				'expected' => true,
			],
			'Matching entry before the timestamp' => [
				'logType' => 'af-view-protected-var-value',
				'target' => 'Target',
				'sinceOffset' => 60,
				'expected' => false,
			],
			'Different log type' => [
				'logType' => 'investigate',
				'target' => 'Target',
				'sinceOffset' => -60,
				'expected' => false,
			],
			'Different target' => [
				'logType' => 'af-view-protected-var-value',
				'target' => 'OtherTarget',
				'sinceOffset' => -60,
				'expected' => false,
			],
		];
	}

	public function testHasLogEntrySinceForDifferentPerformer(): void {
		$object = $this->setUpObject();
		$performer = $this->getTestUser( 'checkuser' )->getUserIdentity();
		$object->addLogEntry( $performer, 'investigate', 'user', 'Target', '' );
		DeferredUpdates::doUpdates();

		$this->assertFalse(
			$object->hasLogEntrySince( $this->getTestSysop()->getUserIdentity(), 'investigate', 'Target', 0 ),
			'Entries by other performers should be ignored'
		);
	}

	public function testHasLogEntrySinceForPerformerWithoutActor(): void {
		$this->assertFalse(
			$this->setUpObject()->hasLogEntrySince(
				UserIdentityValue::newRegistered( 123456, 'NonExistingTestUser1234' ),
				'investigate',
				'Target',
				0
			),
			'Performers without an actor ID should have no entries'
		);
	}

	public function testVerifyTargetUser() {
		$object = $this->setUpObject();
		// Existing user
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$this->assertSame(
			$testUser->getId(),
			$object->verifyTarget( $testUser->getName() ),
			'For an existing user it\'s ID should be returned.'
		);
	}

	/** @dataProvider provideVerifyTargetUserForNonExistingUser */
	public function testVerifyTargetUserForNonExistingUser( $username ) {
		$object = $this->setUpObject();
		$this->assertFalse(
			$object->verifyTarget( $username ),
			'If the target was not valid or did not exist, then false should be returned by ::verifyTarget.'
		);
	}

	public static function provideVerifyTargetUserForNonExistingUser() {
		return [
			'Non-existing user' => [ 'Non-existent user testing 123456789' ],
			'Invalid username' => [ '/' ],
		];
	}
}
