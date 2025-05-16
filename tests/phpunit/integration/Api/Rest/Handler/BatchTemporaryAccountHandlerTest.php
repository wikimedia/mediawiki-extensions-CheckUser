<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use GlobalPreferences\GlobalPreferencesFactory;
use JobQueueGroup;
use MediaWiki\CheckUser\Api\Rest\Handler\BatchTemporaryAccountHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Preferences\DefaultPreferencesFactory;
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
		bool $autoRevealEnabled
	) {
		$this->enableAutoCreateTempUser();

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
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
		} else {
			$preferencesFactory = $this->createMock( DefaultPreferencesFactory::class );
		}

		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->exactly( $jobQueueGroupExpects ) )
			->method( 'push' );

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

		$services = $this->getServiceContainer();
		$handler = $this->getMockBuilder( BatchTemporaryAccountHandler::class )
			->onlyMethods( [ 'getRevisionsIps', 'getLogIps', 'getActorIps' ] )
			->setConstructorArgs( [
				$services->getMainConfig(),
				$jobQueueGroup,
				$services->getPermissionManager(),
				$preferencesFactory,
				$services->getUserNameUtils(),
				$services->getConnectionProvider(),
				$actorStore,
				$services->getBlockManager(),
				$services->getRevisionStore(),
				$checkUserPermissionManager,
				$services->get( 'CheckUserTemporaryAccountLoggerFactory' ),
				$services->getReadOnlyMode()
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
			],
			'The correct logger is called when auto-reveal is off' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => true,
				'autoRevealEnabled' => false,
			],
			'The correct logger is called when auto-reveal is unavailable' => [
				'jobQueueGroupExpects' => 1,
				'loggerExpects' => 0,
				'autoRevealAvailable' => false,
				'autoRevealEnabled' => true,
			],
		];
	}
}
