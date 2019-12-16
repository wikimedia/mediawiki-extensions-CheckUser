<?php

use MediaWiki\CheckUser\PreliminaryCheckService;
use Wikimedia\Rdbms\FakeResultWrapper;
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
		$attachedWikis = $options['attachedWikis'] ?? [ 'testwiki' ];

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
		$dbRef->method( 'select' )
			->willReturn(
				new FakeResultWrapper( array_map(
					function ( $wiki ) use ( $user ) {
						return (object)[
							'lu_name' => $user['name'],
							'lu_wiki' => $wiki,
							'lu_name_wiki' => $user['name'] . '>' . $wiki,
						];
					},
					$attachedWikis
				) )
			);

		$lb = $this->getMockLoadBalancer();
		$lb->method( 'getConnectionRef' )->willReturn( $dbRef );
		$lbFactory = $this->getMockLoadBalancerFactory();
		$lbFactory->method( 'getMainLB' )->willReturn( $lb );

		$registry = $this->getMockExtensionRegistry();
		$registry->method( 'isLoaded' )->willReturn( $options['isCentralAuthAvailable'] );

		$service = $this->getMockBuilder( PreliminaryCheckService::class )
			->setConstructorArgs( [
				$lbFactory,
				$registry,
				$options['localWikiId']
			] )
			->setMethods( [ 'getCentralAuthDB', 'isUserBlocked', 'getUserGroups' ] )
			->getMock();

		$service->method( 'isUserBlocked' )
			->willReturn( $user['blocked'] );
		$service->method( 'getUserGroups' )
			->willReturn( $user['groups'] );
		$service->method( 'getCentralAuthDB' )
			->willReturn( $dbRef );

		$users = [ User::newFromName( $user['name'] ) ];
		$pageInfo = [
			'includeOffset' => true,
			'offsets' => [
				'name' => 'Test User',
				'wiki' => $attachedWikis[0],
			],
			'limit' => 100,
			'order' => true,
		];
		$data = $service->getPreliminaryData( $users, $pageInfo );

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
					$userData + [ 'wiki' => 'enwiki', 'lu_name_wiki' => 'Test User>enwiki' ],
					$userData + [ 'wiki' => 'frwiki', 'lu_name_wiki' => 'Test User>frwiki' ],
					$userData + [ 'wiki' => 'testwiki', 'lu_name_wiki' => 'Test User>testwiki' ],
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
					$userData + [ 'wiki' => 'testwiki', 'lu_name_wiki' => 'Test User>testwiki' ],
				],
			],
			'CentralAuth not available' => [
				$userData,
				[
					'isCentralAuthAvailable' => false,
					'localWikiId' => 'somewiki',
				],
				[
					$userData + [ 'wiki' => 'somewiki' ],
				],
			],
		];
	}
}
