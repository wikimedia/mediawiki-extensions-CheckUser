<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountLogHandler
 */
class TemporaryAccountLogHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountLogHandler
	 */
	private function getTemporaryAccountLogHandler( array $options = [] ): TemporaryAccountLogHandler {
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

		return new TemporaryAccountLogHandler( ...array_values( array_merge(
			[
				'config' => MediaWikiServices::getInstance()->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'userOptionsLookup' => $userOptionsLookup,
				'userNameUtils' => $userNameUtils,
				'dbProvider' => MediaWikiServices::getInstance()->getDBLoadBalancerFactory(),
				'actorStore' => $actorStore,
			],
			$options
		) ) );
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
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountLogHandler(),
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
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_NEW );
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

	public function testErrorOnWrongMigrationStage() {
		$this->setMwGlobals( 'wgCheckUserEventTablesMigrationStage', SCHEMA_COMPAT_OLD );
		$this->expectExceptionCode( 404 );
		$this->expectExceptionMessage( 'rest-no-match' );
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

	public function addDBData() {
		$testData = [
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_log_id' => 10,
				'cule_timestamp'  => $this->db->timestamp( '20200101000000' ),
			],
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id' => 100,
				'cule_timestamp'  => $this->db->timestamp( '20210101000000' ),
			],
			[
				'cule_actor'      => 1234,
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_log_id' => 1000,
				'cule_timestamp'  => $this->db->timestamp( '20220101000000' ),
			],
		];

		$commonData = [
			'cule_agent'      => 'foo user agent',
			'cule_xff'        => 0,
			'cule_xff_hex'    => null,
		];

		foreach ( $testData as $row ) {
			$this->db->insert( 'cu_log_event', $row + $commonData );
		}
	}
}
