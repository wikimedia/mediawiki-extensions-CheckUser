<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use Wikimedia\IPUtils;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionHandler
 */
class TemporaryAccountRevisionHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountRevisionHandler
	 */
	private function getTemporaryAccountRevisionHandler( array $options = [] ): TemporaryAccountRevisionHandler {
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

		return new TemporaryAccountRevisionHandler( ...array_values( array_merge(
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
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler(),
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
			'One revision' => [
				[
					'10' => '1.2.3.4',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => 10,
				],
			],
			'Multiple revisions' => [
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
			'Nonexistent revisions included' => [
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

	public function testErrorOnMissingRevisionIds() {
		$this->expectExceptionCode( 400 );
		$this->expectExceptionMessage( 'paramvalidator-missingparam' );
		$this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler(),
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
			'cuc_comment_id' => 0,
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as $row ) {
			$this->db->newInsertQueryBuilder()
				->insertInto( 'cu_changes' )
				->row( $row + $commonData )
				->execute();
		}

		$this->tablesUsed[] = 'cu_changes';
	}
}
