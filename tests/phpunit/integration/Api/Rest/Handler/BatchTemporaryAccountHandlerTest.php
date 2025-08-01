<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use JobQueueGroup;
use MediaWiki\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler
 */
class BatchTemporaryAccountHandlerTest extends MediaWikiIntegrationTestCase {

	use FilterFromSpecsTestTrait;
	use HandlerTestTrait;
	use MockServiceDependenciesTrait;
	use TempUserTestTrait;

	private static UserIdentity $tempUser;
	private static int $pageCreationLogId;
	private static array $logIdsForPerformLogsLookupTest;

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute(
		int $jobQueueGroupExpects,
		int $loggerExpects,
		bool $autoRevealAvailable,
		bool $autoRevealEnabled,
		bool $abuseFilterLoaded
	) {
		if ( $abuseFilterLoaded ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		}

		$this->enableAutoCreateTempUser();

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );
		$checkUserPermissionManager->method( 'canAutoRevealIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 12345 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 12345, '~12345' ) );

		if ( $autoRevealAvailable ) {
			$preferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
			$preferencesFactory->method( 'getGlobalPreferencesValues' )
				->willReturn(
					$autoRevealEnabled ?
					[ Preferences::ENABLE_IP_AUTO_REVEAL => time() + 10000 ] :
					[]
				);
			$autoRevealLookup = new CheckUserTemporaryAccountAutoRevealLookup(
				$preferencesFactory, $checkUserPermissionManager
			);
		} else {
			$autoRevealLookup = $this->createMock(
				CheckUserTemporaryAccountAutoRevealLookup::class
			);
		}

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( $abuseFilterLoaded );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->exactly( $jobQueueGroupExpects ) )
			->method( 'push' );

		// Set a mock logger for the test and then reset the services as we need services to use this mock logger.
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( $loggerExpects ) )
			->method( 'info' )
			->with(
				'{username} viewed IP addresses for {target}',
				$this->callback( static function ( $context ) {
					return $context['target'] === '~12345';
				} )
			);
		$this->setLogger( 'CheckUser', $logger );
		$this->resetServices();

		$services = $this->getServiceContainer();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'getRevisionsIps', 'getLogIps', 'getActorIps' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$actorStore,
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$autoRevealLookup,
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode(),
				$extensionRegistry
			] )
			->getMock();
		$handler->method( 'getRevisionsIps' )
			->with( 12345, [ 1 ] )
			->willReturn( [ 1 => '1.2.3.4' ] );
		$handler->method( 'getLogIps' )
			->with( 12345, [ 1 ] )
			->willReturn( [ 1 => '5.6.7.8' ] );
		$handler->method( 'getActorIps' )
			->with( 12345, 1 )
			->willReturn( [ '9.8.7.6' ] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler,
			new RequestData(),
			[],
			[],
			[],
			[
				'users' => [
					'~12345' => [
						'revIds' => [ 1 ],
						'logIds' => [ 1 ],
						'lastUsedIp' => true,
					],
				],
			],
			$this->getTestUser()->getAuthority()
		);

		$expectedData = [
			'~12345' => [
				'revIps' => [ 1 => '1.2.3.4' ],
				'logIps' => [ 1 => '5.6.7.8' ],
				'lastUsedIp' => '9.8.7.6',
			]
		];
		if ( $abuseFilterLoaded ) {
			$expectedData['~12345']['abuseLogIps'] = null;
		}
		if ( $autoRevealAvailable ) {
			$expectedData['autoReveal'] = $autoRevealEnabled;
		}

		$this->assertSame( $expectedData, $data );
	}

	public static function provideExecute() {
		return [
			'The correct logger is called when auto-reveal is on' => [
				'jobQueueGroupExpects' => 0,
				'loggerExpects' => 1,
				'autoRevealAvailable' => true,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => true,
			],
			'The correct logger is called when auto-reveal is off' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => true,
				'autoRevealEnabled' => false,
				'abuseFilterLoaded' => true,
			],
			'The correct logger is called when auto-reveal is unavailable' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => false,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => true,
			],
			'abuseLogIps key is not added if AbuseFilter is not installed' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => false,
				'autoRevealEnabled' => true,
				'abuseFilterLoaded' => false,
			],
		];
	}

	/** @dataProvider provideExecuteForSpecificTypeOfIds */
	public function testExecuteForSpecificTypeOfIds(
		$revIdsCallback, $logIdsCallback, $abuseLogIdsCallback, $expectedResponseCallback, $permissions
	) {
		if ( count( $abuseLogIdsCallback() ) ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		}

		$handler = $this->mockHandler();
		// Assume that existing log entries are visible to the user, except for log 1000, which is suppressed
		$handler->method( 'performLogsLookup' )
			->willReturnCallback( static function ( $ids ) {
				// Only return log entries for the log IDs that are in the input array and are defined log IDs in
				// the test data.
				return new FakeResultWrapper( array_values( array_map( static function ( $id ) {
					return [
						'log_id' => $id,
						'log_deleted' => $id !== 1000 ? 0 : LogPage::DELETED_RESTRICTED | LogPage::DELETED_USER
					];
				}, array_intersect( $ids, [ 10, 100, 1000, self::$pageCreationLogId ] ) ) ) );
			} );

		$authority = $this->mockRegisteredAuthorityWithPermissions( $permissions );

		$userName = self::$tempUser->getName();
		$data = $this->executeHandlerAndGetBodyData(
			$handler, new RequestData(), [], [], [],
			[
				'users' => [
					$userName => [
						'revIds' => $revIdsCallback(),
						'logIds' => $logIdsCallback(),
						'lastUsedIp' => false,
						'abuseLogIds' => $abuseLogIdsCallback(),
					],
				],
			],
			$authority
		);

		$responseTemplate = [
			'revIps' => null,
			'logIps' => null,
			'lastUsedIp' => null,
		];
		$extensionRegistry = $this->getServiceContainer()->getExtensionRegistry();
		if ( $extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$responseTemplate['abuseLogIps'] = null;
		}
		$expectedResponse = array_merge(
			$responseTemplate,
			$expectedResponseCallback()
		);

		$this->assertArrayEquals(
			[
				$userName => $expectedResponse,
				'autoReveal' => false,
			],
			$data,
			false, true
		);
	}

	public static function provideExecuteForSpecificTypeOfIds() {
		return [
			'Request with no IDs' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [],
				'permissions' => [],
			],
			'Single log ID' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4' ],
				],
				'permissions' => [],
			],
			'Two log IDs' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 100 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4', 100 => '1.2.3.5' ],
				],
				'permissions' => [],
			],
			'One visible and one suppressed log (unprivileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 1000 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4' ],
				],
				'permissions' => [],
			],
			'One visible and one suppressed log (privileged user)' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 1000 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4', 1000 => '1.2.3.5' ],
				],
				'permissions' => [ 'viewsuppressed' ],
			],
			'Nonexistent log IDs included' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ 10, 9999 ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ 10 => '1.2.3.4' ],
				],
				'permissions' => [],
			],
			'Creation log' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [ self::$pageCreationLogId ],
				'abuseLogIdsCallback' => static fn () => [],
				'expectedResponseCallback' => static fn () => [
					'logIps' => [ self::$pageCreationLogId => '1.2.3.20' ],
				],
				'permissions' => [],
			],
			'AbuseFilter log' => [
				'revIdsCallback' => static fn () => [],
				'logIdsCallback' => static fn () => [],
				'abuseLogIdsCallback' => static fn () => [ 1, 2 ],
				'expectedResponseCallback' => static fn () => [
					'abuseLogIps' => [ 1 => '1.2.3.4' ]
				],
				'permissions' => [ 'abusefilter-log-detail', 'abusefilter-log-private' ],
			]
		];
	}

	public function testPerformLogsLookup() {
		// Tests ::performLogsLookup, which is mocked in other tests to avoid
		// having to create log entries for every test.
		$handler = $this->mockHandler();
		$handler = TestingAccessWrapper::newFromObject( $handler );
		$actualRows = $handler->performLogsLookup( self::$logIdsForPerformLogsLookupTest );
		foreach ( $actualRows as $index => $row ) {
			$this->assertSame(
				(int)$row->log_id,
				self::$logIdsForPerformLogsLookupTest[$index],
				"Log ID for row $index is not as expected"
			);
		}
	}

	private function mockHandler() {
		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );

		$this->setLogger( 'CheckUser', $this->createNoOpMock( LoggerInterface::class ) );

		$services = $this->getServiceContainer();
		$extensionRegistry = $services->getExtensionRegistry();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'performLogsLookup' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$services->getActorStore(),
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$services->get(
					'CheckUserTemporaryAccountAutoRevealLookup'
				),
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode(),
				$extensionRegistry
			] )
			->getMock();

		return $handler;
	}

	public function addDBDataOnce(): void {
		$this->enableAutoCreateTempUser();

		// Create temporary accounts for use in generating test data
		$tempUser1 = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();
		$tempUser2 = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )
			->getUser();

		$this->addDBDataForAbuseLog( $tempUser1, $tempUser2 );
		$this->addDBDataForLogs( $tempUser1 );

		// tempUser2 is not referenced in tests; it's only a confounding factor for the code
		self::$tempUser = $tempUser1;
	}

	private function addDBDataForLogs( User $tempUser ): void {
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId( $tempUser, $this->getDb() );

		// And some additional log entries that associate log ids with IP addresses
		$testData = [
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id'     => 10,
				'cule_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 100,
				'cule_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
				'cule_agent'      => 'foo user agent',
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			],
			[
				'cule_actor'      => $actorId,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id'     => 1000,
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

		$this->addDBDataForCreationLog( $tempUser, $actorId );

		self::$logIdsForPerformLogsLookupTest = [
			$this->createLogEntry( $tempUser )->insert(), $this->createLogEntry( $tempUser )->insert()
		];
	}

	private function addDBDataForCreationLog( User $tempUser, int $actorId ): void {
		// Create a page using the temporary account, so that we can test looking up CU data for log entries
		// which don't have CU data but have an associated revision which does.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.20' );
		$this->editPage(
			$this->getNonexistingTestPage(), 'testingabc', 'test create', NS_MAIN, $tempUser
		);

		// Assert that no cu_log_event row exists for the page creation (as then we won't be testing
		// that the CU data comes from cu_changes)
		self::$pageCreationLogId = $this->newSelectQueryBuilder()
			->select( 'log_id' )
			->from( 'logging' )
			->where( [ 'log_type' => 'create', 'log_actor' => $actorId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertNotFalse( self::$pageCreationLogId );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'log_search' )
			->where( [ 'ls_log_id' => self::$pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertFieldValue( '1' );
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cu_log_event' )
			->where( [ 'cule_log_id' => self::$pageCreationLogId ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	private function addDBDataForAbuseLog( User $tempUser1, User $tempUser2 ): void {
		$performer = $this->getTestSysop()->getUser();
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer, null,
			$this->getFilterFromSpecs( [
				'id' => '1',
				'name' => 'Test filter',
				'privacy' => Flags::FILTER_HIDDEN,
				'rules' => 'old_wikitext = "abc"',
			] ),
			MutableFilter::newDefault()
		) );

		// Insert two hits on the filter performed by different users but on the same IP
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$tempUser1,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $tempUser1->getName(),
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );

		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$tempUser2,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $tempUser2->getName(),
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );
	}

	private function createLogEntry( UserIdentity $performer ): ManualLogEntry {
		$logEntry = new ManualLogEntry( 'move', 'move' );
		$logEntry->setPerformer( $performer );
		$logEntry->setDeleted( LogPage::DELETED_USER | LogPage::DELETED_RESTRICTED );
		$logEntry->setTarget( $this->getExistingTestPage() );
		$logEntry->setParameters( [
			'4::target' => wfRandomString(),
			'5::noredir' => '0'
		] );
		return $logEntry;
	}
}
