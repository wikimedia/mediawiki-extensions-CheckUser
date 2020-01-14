<?php

use MediaWiki\CheckUser\PreliminaryCheckService;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Test class for PreliminaryCheckService class
 *
 * @group CheckUser
 *
 * @coversDefaultClass \MediaWiki\CheckUser\PreliminaryCheckService
 */
class PreliminaryCheckServiceTest extends MediaWikiTestCase {
	/**
	 * @return MockObject|ILoadBalancer
	 */
	private function getMockLoadBalancer() {
		return $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|IDatabase
	 */
	private function getMockDb() {
		return $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|ILBFactory
	 */
	private function getMockLoadBalancerFactory() {
		return $this->getMockBuilder( ILBFactory::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|ExtensionRegistry
	 */
	private function getMockExtensionRegistry() {
		return $this->getMockBuilder( ExtensionRegistry::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @covers ::getPreliminaryData
	 * @dataProvider preliminaryDataProvider()
	 */
	public function testGetPreliminaryData( $user, $options, $expected ) {
		$dbRef = $this->getMockDb();
		$dbRef->method( 'selectRow' )
			->willReturn(
				(object)[
					'user_id' => $user['id'],
					'user_name' => $user['name'],
					'user_registration' => $user['registration'],
					'user_editcount' => $user['editcount'],
				]
			);

		$lb = $this->getMockLoadBalancer();
		$lb->method( 'getConnectionRef' )->willReturn( $dbRef );
		$lbFactory = $this->getMockLoadBalancerFactory();
		$lbFactory->method( 'getMainLB' )->willReturn( $lb );

		$service = $this->getMockBuilder( PreliminaryCheckService::class )
			->setConstructorArgs( [ $lbFactory,
				$this->getMockExtensionRegistry(),
				$options['localWikiId']
			] )
			->setMethods( [ 'isUserBlocked', 'getUserGroups', 'getGlobalUser' ] )
			->getMock();

		$service->method( 'isUserBlocked' )
			->willReturn( $user['blocked'] );
		$service->method( 'getUserGroups' )
			->willReturn( $user['groups'] );

		$globalUserMock = $this->getMockBuilder( CentralAuthUser::class )
			->setMethods( [ 'listAttached', 'exists' ] )
			->disableOriginalConstructor()
			->getMock();
		$globalUserMock->method( 'exists' )
			->willReturn( isset( $options['attachedWikis'] ) ? true : false );

		if ( $options['isCentralAuthAvailable'] ) {
			$globalUserMock->expects( $this->once() )
				->method( 'listAttached' )
				->willReturn( $options['attachedWikis'] );
		} else {
			$globalUserMock
				->expects( $this->never() )
				->method( 'listAttached' );
		}

		$service->method( 'getGlobalUser' )
			->willReturn( $globalUserMock );

		$users = [ User::newFromName( $user['name'] ) ];
		$data = $service->getPreliminaryData( $users );

		$this->assertEquals( $expected, $data );
	}

	public function preliminaryDataProvider() {
		$userData = [
			'id' => 1,
			'name' => 'Test User',
			'registration' => '20190101010101',
			'editcount' => 20,
			'blocked' => true,
			'groups' => [ 'sysop', 'autoconfirmed' ],
		];

		return [
			'User attached to 3 wikis' => [
				$userData,
				[
					'attachedWikis' => [ 'enwiki', 'frwiki', 'testwiki' ],
					'isCentralAuthAvailable' => true,
					'localWikiId' => 'testwiki',
				],
				[
					$userData['name'] => [
						'enwiki'  => $userData,
						'frwiki'  => $userData,
						'testwiki'  => $userData,
					],
				],
			],
			'User with only 1 wiki' => [
				$userData,
				[
					'attachedWikis' => [ 'testwiki' ],
					'isCentralAuthAvailable' => true,
					'localWikiId' => 'testwiki',
				],
				[
					$userData['name'] => [
						'testwiki'  => $userData,
					],
				],
			],
			'CentralAuth not available' => [
				$userData,
				[
					'isCentralAuthAvailable' => false,
					'localWikiId' => 'somewiki',
				],
				[
					$userData['name'] => [
						'somewiki'  => $userData,
					],
				],
			],
		];
	}
}
