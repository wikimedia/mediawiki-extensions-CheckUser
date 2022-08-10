<?php

namespace MediaWiki\CheckUser\Tests;

use ExtensionRegistry;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use RequestContext;
use Wikimedia\Rdbms\ILBFactory;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager
 */
class PreliminaryCheckPagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return MockObject|ExtensionRegistry
	 */
	private function getMockExtensionRegistry() {
		return $this->getMockBuilder( ExtensionRegistry::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|TokenQueryManager
	 */
	private function getMockTokenQueryManager() {
		return $this->getMockBuilder( TokenQueryManager::class )
			->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return MockObject|PreliminaryCheckService
	 */
	private function getMockPreliminaryCheckService() {
		return $this->getMockBuilder( PreliminaryCheckService::class )
			->disableOriginalConstructor()->getMock();
	}

	public function testGetQueryInfoFiltersIPsFromTargets() {
		$registry = $this->getMockExtensionRegistry();
		$registry->method( 'isLoaded' )->willReturn( true );

		$tokenQueryManager = $this->getMockTokenQueryManager();
		$tokenQueryManager->method( 'getDataFromRequest' )->willReturn( [
			'targets' => [ 'UserA', 'UserB', '1.2.3.4' ]
		] );

		$lbf = $this->getMockBuilder( ILBFactory::class )
			->disableOriginalConstructor()->getMock();

		$preliminaryCheckService = new PreliminaryCheckService(
			$lbf,
			$registry,
			$this->createNoOpMock( UserGroupManagerFactory::class ),
			'testwiki'
		);

		$services = MediaWikiServices::getInstance();
		$pager = new PreliminaryCheckPager( RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$tokenQueryManager,
			$registry,
			$preliminaryCheckService
		);

		$result = $pager->getQueryInfo();

		$expected = [
			'tables' => 'localuser',
			'fields' => [
				'lu_name',
				'lu_wiki',
			],
			'conds' => [ 'lu_name' => [ 'UserA', 'UserB' ] ]
		];
		$this->assertSame( $expected, $result );
	}

	public function testGetIndexFieldLocal() {
		$services = MediaWikiServices::getInstance();
		$pager = new PreliminaryCheckPager(
			RequestContext::getMain(),
			$services->getLinkRenderer(),
			$services->getNamespaceInfo(),
			$services->get( 'CheckUserTokenQueryManager' ),
			$this->getMockExtensionRegistry(),
			$this->getMockPreliminaryCheckService()
		);
		$this->assertEquals( 'user_name', $pager->getIndexfield() );
	}

	public function testGetIndexFieldGlobal() {
		$services = MediaWikiServices::getInstance();
		$registry = $this->getMockExtensionRegistry();
		$preliminaryCheckService = $this->getMockPreliminaryCheckService();
		$pager = $this->getMockBuilder( PreliminaryCheckPager::class )
			->setConstructorArgs( [ RequestContext::getMain(),
				$services->getLinkRenderer(),
				$services->getNamespaceInfo(),
				$services->get( 'CheckUserTokenQueryManager' ),
				$registry,
				$preliminaryCheckService
			 ] )
			->onlyMethods( [ 'isGlobalCheck' ] )
			->getMock();

		$pager->method( 'isGlobalCheck' )->willReturn( true );
		$this->assertEquals( [ [ 'lu_name', 'lu_wiki' ] ], $pager->getIndexfield() );
	}
}
