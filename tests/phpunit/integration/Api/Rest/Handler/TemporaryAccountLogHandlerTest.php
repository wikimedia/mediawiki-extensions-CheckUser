<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use JobQueueGroup;
use LogPage;
use ManualLogEntry;
use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler
 */
class TemporaryAccountLogHandlerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;
	use MockAuthorityTrait;
	use HandlerTestTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountLogHandler|MockObject
	 */
	private function getTemporaryAccountLogHandler( array $options = [] ) {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userOptionsLookup = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookup->method( 'getOption' )
			->willReturn( true );

		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )
			->willReturn( true );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 1234 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 1234, '*Unregistered 1' ) );

		// Mock ::performLogsLookup to avoid DB lookups when these tests do not create entries
		// in the logging table.
		return $this->getMockBuilder( TemporaryAccountLogHandler::class )
			->onlyMethods( [ 'performLogsLookup' ] )
			->setConstructorArgs( array_values( array_merge(
				[
					'config' => $this->getServiceContainer()->getMainConfig(),
					'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
					'permissionManager' => $permissionManager,
					'userOptionsLookup' => $userOptionsLookup,
					'userNameUtils' => $userNameUtils,
					'dbProvider' => $this->getServiceContainer()->getDBLoadBalancerFactory(),
					'actorStore' => $actorStore,
					'blockManager' => $this->getServiceContainer()->getBlockManager(),
				],
				$options
			) ) )
			->getMock();
	}

	/**
	 * @return Authority
	 */
	private function getAuthorityForSuccess(): Authority {
		$user = $this->createMock( UserIdentityValue::class );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $options['user'] ?? $user );
		$authority->method( 'isNamed' )
			->willReturn( true );
		$authority->method( 'getBlock' )
			->willReturn( null );

		return $authority;
	}

	private function getRequestData( array $options = [] ): RequestData {
		return new RequestData( [
			'pathParams' => [
				'name' => $options['name'] ?? '*Unregistered 1',
				'ids' => $options['ids'] ?? [ 10 ],
			],
		] );
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $expected, $options ) {
		$temporaryAccountLogHandler = $this->getTemporaryAccountLogHandler();
		$temporaryAccountLogHandler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data. These rows also have log_deleted as 0. Other values for log_deleted are tested in
				// other tests.
				return new FakeResultWrapper( array_values( array_map( static function ( $id ) {
					return [ 'log_id' => $id, 'log_deleted' => 0 ];
				}, array_intersect( $ids, [ 10, 100, 1000 ] ) ) ) );
			} );
		$data = $this->executeHandlerAndGetBodyData(
			$temporaryAccountLogHandler,
			$this->getRequestData( $options ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			$expected,
			$data['ips'],
			true,
			true
		);
	}

	public static function provideExecute() {
		return [
			'One log entry' => [
				[
					'10' => '1.2.3.4',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => 10,
				],
			],
			'Multiple log entries' => [
				[
					'10' => '1.2.3.4',
					'100' => '1.2.3.5',
					'1000' => '1.2.3.5',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => [ 1000, 10, 100 ],
				],
			],
			'Nonexistent log entries included' => [
				[
					'10' => '1.2.3.4',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => [ 9999, 10 ],
				],
			],
		];
	}

	public function testErrorOnMissingLogIds() {
		$this->expectExceptionCode( 400 );
		$this->expectExceptionMessage( 'paramvalidator-missingparam' );
		$this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountLogHandler(),
			$this->getRequestData( [
				'ids' => []
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
	}

	public function testWhenLogPerformerIsSuppressed() {
		$this->enableAutoCreateTempUser();
		$this->getServiceContainer()->getTempUserCreator()->create( '*Unregistered 1', new FauxRequest() );
		// Set up a mock actor store that gets the real actor ID for the test temp user.
		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( $this->getServiceContainer()->getActorStore()
				->findActorId( new UserIdentityValue( 1234, '*Unregistered 1' ), $this->getDb() )
			);
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 1234, '*Unregistered 1' ) );
		$temporaryAccountLogHandler = $this->getTemporaryAccountLogHandler( [
			'actorStore' => $actorStore,
		] );
		$temporaryAccountLogHandler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data. These rows also have log_deleted as 0. Other values for log_deleted are tested in
				// other tests.
				return new FakeResultWrapper( array_map( static function ( $id ) {
					return [ 'log_id' => $id, 'log_deleted' => LogPage::DELETED_RESTRICTED | LogPage::DELETED_USER ];
				}, array_intersect( $ids, [ 10, 100, 1000 ] ) ) );
			} );
		$data = $this->executeHandlerAndGetBodyData(
			$temporaryAccountLogHandler,
			$this->getRequestData( [
				'name' => '*Unregistered 1',
				'ids' => 10,
			] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredAuthorityWithPermissions( [ 'checkuser-temporary-account' ] )
		);
		$this->assertArrayEquals( [], $data['ips'] );
	}

	public function testPerformLogsLookup() {
		// Tests ::performLogsLookup, which is mocked in other tests to avoid
		// having to create log entries for every test.
		$firstLogId = $this->createLogEntry()->insert();
		$secondLogId = $this->createLogEntry()->insert();
		$logIdsForTest = [ $firstLogId, $secondLogId ];
		$temporaryAccountLogHandler = $this->getMockBuilder( TemporaryAccountLogHandler::class )
			->disableOriginalConstructor()
			->getMock();
		$temporaryAccountLogHandler = TestingAccessWrapper::newFromObject( $temporaryAccountLogHandler );
		$temporaryAccountLogHandler->dbProvider = $this->getServiceContainer()->getConnectionProvider();
		$actualRows = $temporaryAccountLogHandler->performLogsLookup( $logIdsForTest );
		foreach ( $actualRows as $index => $row ) {
			$this->assertSame(
				(int)$row->log_id,
				$logIdsForTest[$index],
				"Log ID for row $index is not as expected"
			);
		}
	}

	private function createLogEntry(): ManualLogEntry {
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( new UserIdentityValue( 1234, '*Unregistered 1' ) );
		$logEntry->setDeleted( LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED );
		$logEntry->setTarget( $this->getExistingTestPage() );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0'
		] );
		return $logEntry;
	}

	public function addDBData() {
		$testData = [
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id' => 10,
				'cule_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id' => 100,
				'cule_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id' => 1000,
				'cule_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
		];

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $testData )
			->caller( __METHOD__ )
			->execute();
	}
}
