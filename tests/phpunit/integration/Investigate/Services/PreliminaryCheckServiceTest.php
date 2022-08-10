<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Services;

use ExtensionRegistry;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Test class for PreliminaryCheckService class
 *
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService
 */
class PreliminaryCheckServiceTest extends MediaWikiIntegrationTestCase {
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
	 * @dataProvider preprocessResultsProvider()
	 */
	public function testPreprocessResults( $user, $options, $expected ) {
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

		$registry = $this->getMockExtensionRegistry();
		$registry->method( 'isLoaded' )->willReturn( $options['isCentralAuthAvailable'] );

		$ugm = $this->createNoOpMock( UserGroupManager::class, [ 'getUserGroups' ] );
		$ugm->method( 'getUserGroups' )->willReturn( $user['groups'] );
		$ugmf = $this->createNoOpMock( UserGroupManagerFactory::class, [ 'getUserGroupManager' ] );
		$ugmf->method( 'getUserGroupManager' )->willReturn( $ugm );

		$service = $this->getMockBuilder( PreliminaryCheckService::class )
			->setConstructorArgs( [
				$lbFactory,
				$registry,
				$ugmf,
				$options['localWikiId']
			] )
			->onlyMethods( [ 'isUserBlocked' ] )
			->getMock();

		$service->method( 'isUserBlocked' )
			->willReturn( $user['blocked'] );

		if ( $options['isCentralAuthAvailable'] ) {
			$rows = new FakeResultWrapper( array_map(
				static function ( $wiki ) use ( $user ) {
					return (object)[
						'lu_name' => $user['name'],
						'lu_wiki' => $wiki,
					];
				},
				$options['attachedWikis']
			) );
		} else {
			$rows = new FakeResultWrapper( [
				[
					'user_id' => $user['id'],
					'user_name' => $user['name'],
					'user_registration' => $user['registration'],
					'user_editcount' => $user['editcount'],
					'wiki' => $options['localWikiId'],
				]
			] );
		}

		$data = $service->preprocessResults( $rows );
		$this->assertEquals( $expected, $data );
	}

	public function preprocessResultsProvider() {
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
					$userData + [ 'wiki' => 'enwiki' ],
					$userData + [ 'wiki' => 'frwiki' ],
					$userData + [ 'wiki' => 'testwiki' ],
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
					$userData + [ 'wiki' => 'testwiki' ],
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

	/**
	 * @dataProvider getQueryInfoProvider()
	 */
	public function testGetQueryInfo( $users, $options, $expected ) {
		$lbFactory = $this->getMockLoadBalancerFactory();
		$registry = $this->getMockExtensionRegistry();

		$registry->method( 'isLoaded' )->willReturn( $options['isCentralAuthAvailable'] );

		$service = new PreliminaryCheckService(
			$lbFactory,
			$registry,
			$this->createNoOpMock( UserGroupManagerFactory::class ),
			'devwiki'
		);
		$result = $service->getQueryInfo( $users );

		$this->assertSame(
			array_replace_recursive( $result, $expected ),
			$result
		);
	}

	public function getQueryInfoProvider() {
		return [
			'local users as string' => [
				[ 'UserA', 'UserB' ],
				[ 'isCentralAuthAvailable' => false ],
				[
					'tables' => 'user',
					'conds' => [
						'user_name' => [ 'UserA', 'UserB' ],
					],
				],
			],
			'empty users' => [
				[],
				[ 'isCentralAuthAvailable' => false ],
				[
					'tables' => 'user',
					'conds' => [ 0 ],
				],
			],
			'global users as string' => [
				[ 'UserA', 'UserB' ],
				[ 'isCentralAuthAvailable' => true ],
				[
					'tables' => 'localuser',
					'conds' => [
						'lu_name' => [ 'UserA', 'UserB' ],
					],
				],
			],
		];
	}
}
