<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountHandler
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler
 */
class TemporaryAccountHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountHandler
	 */
	private function getTemporaryAccountHandler( array $options = [] ): TemporaryAccountHandler {
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

		return new TemporaryAccountHandler( ...array_values( array_merge(
			[
				'config' => MediaWikiServices::getInstance()->getMainConfig(),
				'permissionManager' => $permissionManager,
				'userOptionsLookup' => $userOptionsLookup,
				'userNameUtils' => $userNameUtils,
				'loadBalancer' => MediaWikiServices::getInstance()->getDBLoadBalancer(),
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
		$pathParams = [
			'name' => $options['name'] ?? '*Unregistered 1',
		];
		$queryParams = [];
		if ( isset( $options['limit'] ) ) {
			$queryParams['limit'] = $options['limit'];
		}

		return new RequestData( [
			'pathParams' => $pathParams,
			'queryParams' => $queryParams,
		] );
	}

	public function testExecute() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 5000 );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[
				'1.2.3.5',
				'1.2.3.4',
			],
			$data['ips'],
			true
		);
	}

	public function testExecuteLimit() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 5000 );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData( [ 'limit' => 1 ] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[ '1.2.3.5' ],
			$data['ips']
		);
	}

	public function testExecuteLimitConfig() {
		$this->overrideConfigValue( 'CheckUserMaximumRowCount', 1 );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[ '1.2.3.5' ],
			$data['ips']
		);
	}

	/**
	 * @dataProvider provideExecutePermissionErrorsNoRight
	 */
	public function testExecutePermissionErrorsNoRight( bool $named, array $expected ) {
		$handler = $this->getTemporaryAccountHandler( [
			'permissionManager' => MediaWikiServices::getInstance()->getPermissionManager()
		] );

		$user = $this->getTestUser()->getUser();

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( $named );
		$authority->method( 'getUser' )
			->willReturn( $user );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied',
				),
				$expected['code']
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$response = $this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public function provideExecutePermissionErrorsNoRight() {
		return [
			'Anon or temporary user' => [
				false,
				[
					'code' => 401
				]
			],
			'Registered (named) user' => [
				true,
				[
					'code' => 403
				]
			],
		];
	}

	public function testExecutePermissionErrorsNoPreference() {
		$handler = $this->getTemporaryAccountHandler( [
			'userOptionsLookup' => MediaWikiServices::getInstance()->getUserOptionsLookup()
		] );

		$user = $this->getTestUser()->getUser();

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );
		$authority->method( 'getUser' )
			->willReturn( $user );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied',
				),
				403
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$response = $this->executeHandler(
			$handler,
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public function testExecutePermissionErrorsBlocked() {
		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );
		$authority->method( 'getBlock' )
			->willReturn( $this->createMock( Block::class ) );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'checkuser-rest-access-denied-blocked-user'
				),
				403
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$response = $this->executeHandler(
			$this->getTemporaryAccountHandler(),
			$this->getRequestData(),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	/**
	 * @dataProvider provideExecutePermissionErrorsBadName
	 */
	public function testExecutePermissionErrorsBadName( $name ) {
		$handler = $this->getTemporaryAccountHandler( [
			'userNameUtils' => MediaWikiServices::getInstance()->getUserNameUtils()
		] );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'rest-invalid-user'
				),
				404
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$response = $this->executeHandler(
			$handler,
			$this->getRequestData( [ 'name' => $name ] ),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public function provideExecutePermissionErrorsBadName() {
		return [
			'Registered username' => [ 'SomeName' ],
			'IP address' => [ '127.0.0.1' ]
		];
	}

	public function testExecutePermissionErrorsNonexistentName() {
		$actorStore = $this->createMock( ActorStore::class );
		$handler = $this->getTemporaryAccountHandler( [
			'actorStore' => $actorStore,
		] );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'isNamed' )
			->willReturn( true );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					'rest-nonexistent-user'
				),
				404
			)
		);

		// Can't use executeHandlerAndGetHttpException, since it doesn't take an Authority
		$response = $this->executeHandler(
			$handler,
			$this->getRequestData( [ 'name' => '*Unregistered 9999' ] ),
			[],
			[],
			[],
			[],
			$authority
		);
	}

	public function addDBData() {
		$testData = [
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_this_oldid' => 10,
				'cuc_timestamp'  => $this->db->timestamp( '20200101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 100,
				'cuc_timestamp'  => $this->db->timestamp( '20210101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 1000,
				'cuc_timestamp'  => $this->db->timestamp( '20220101000000' ),
			],
		];

		$commonData = [
			'cuc_type'       => RC_EDIT,
			'cuc_agent'      => 'foo user agent',
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_actiontext' => '',
			'cuc_comment'    => '',
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as $row ) {
			$this->db->insert( 'cu_changes', $row + $commonData );
		}

		$this->tablesUsed[] = 'cu_changes';
	}
}
