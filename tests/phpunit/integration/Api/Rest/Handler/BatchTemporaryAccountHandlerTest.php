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
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

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

	public function testExecuteWhenAbuseFilterLogIdsSpecified() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 12345 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 12345, '~2025-1' ) );

		$autoRevealLookup = $this->createMock( CheckUserTemporaryAccountAutoRevealLookup::class );

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->once() )
			->method( 'push' );

		$this->setLogger( 'CheckUser', $this->createNoOpMock( LoggerInterface::class ) );

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
				$services->getExtensionRegistry()
			] )
			->getMock();
		$handler->expects( $this->never() )
			->method( 'getRevisionsIps' );
		$handler->expects( $this->never() )
			->method( 'getLogIps' );
		$handler->method( 'getActorIps' )
			->with( 12345, 1 )
			->willReturn( [ '9.8.7.6' ] );

		$data = $this->executeHandlerAndGetBodyData(
			$handler, new RequestData(), [], [], [],
			[
				'users' => [
					'~2025-1' => [ 'revIds' => [], 'logIds' => [], 'lastUsedIp' => true, 'abuseLogIds' => [ 1, 2 ] ],
				],
			],
			$this->mockRegisteredUltimateAuthority()
		);

		$this->assertArrayEquals(
			[ '~2025-1' => [
				'revIps' => null,
				'logIps' => null,
				'lastUsedIp' => '9.8.7.6',
				'abuseLogIps' => [ 1 => '1.2.3.4' ],
			] ],
			$data,
			false, true
		);
	}

	public function addDBDataOnce(): void {
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
		$this->enableAutoCreateTempUser();
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getServiceContainer()->getTempUserCreator()->create( '~2025-1', new FauxRequest() )->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => '~2025-1',
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );

		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getServiceContainer()->getTempUserCreator()->create( '~2025-2', new FauxRequest() )->getUser(),
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => '~2025-2',
				'old_wikitext' => 'abc',
			] )
		)->addLogEntries( [ 1 => [] ] );
	}
}
