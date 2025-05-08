<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\CentralAuthHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CentralAuth\Special\SpecialGlobalGroupMembership;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use SpecialPageExecutor;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CentralAuthHandler
 * @group CheckUser
 * @group Database
 */
class CentralAuthHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		// Add global groups
		$caDbw = CentralAuthServices::getDatabaseManager( $this->getServiceContainer() )
			->getCentralPrimaryDB();

		$caDbw->newInsertQueryBuilder()
			->insertInto( 'global_group_permissions' )
			->rows( [
				[ 'ggp_group' => 'group-one', 'ggp_permission' => 'right-one' ],
				[ 'ggp_group' => 'group-two', 'ggp_permission' => 'right-two' ],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function getHookHandler( $overrides = [] ) {
		$services = $this->getServiceContainer();
		return new CentralAuthHandler(
			$overrides[ 'wanCache' ] ?? $services->getMainWANObjectCache(),
			$overrides[ 'specialPageFactory' ] ?? $services->getSpecialPageFactory()
		);
	}

	public function testOnGlobalUserGroupsChanged() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		$services = $this->getServiceContainer();

		// Add the test user to a global group
		$user = $this->getMutableTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user->getUser() );
		$caUser->register( $user->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		$caUser->addToGlobalGroup( 'group-one' );

		// Manually set the cache value to be invalidated
		$performer = $this->getTestSysop();
		RequestContext::getMain()->setUser( $performer->getUser() );
		$wanCache = $services->getMainWANObjectCache();
		$cacheKey = $wanCache->makeGlobalKey(
			'globalcontributions-ext-permissions',
			$services->get( 'CentralIdLookup' )->centralIdFromLocalUser( $user->getUserIdentity() )
		);
		$wanCache->set( $cacheKey, [] );
		$this->assertSame( [], $wanCache->get( $cacheKey ) );

		// Instantiate Special:GlobalUserRights to change the test user's global group membership
		$specialGlobalGroupMembership = new SpecialGlobalGroupMembership(
			$services->getHookContainer(),
			$services->getTitleFactory(),
			$services->getUserNamePrefixSearch(),
			$services->getUserNameUtils(),
			CentralAuthServices::getAutomaticGlobalGroupManager( $services ),
			CentralAuthServices::getGlobalGroupLookup( $services )
		);
		$username = lcfirst( $user->getUser()->getName() );
		( new SpecialPageExecutor() )->executeSpecialPage(
			$specialGlobalGroupMembership,
			$username,
			new FauxRequest(
				[
					'user' => $username,
					'saveusergroups' => '1',
					'wpEditToken' => SessionManager::getGlobalSession()->getToken( $username ),
					'conflictcheck-originalgroups' => 'group-one',
					'wpGroup-group-two' => '1',
					'wpExpiry-group-two' => 'infinite',
					'user-reason' => 'test',
				],
				true
			),
			'qqx',
			new UltimateAuthority( $performer->getUser() ),
			false
		);

		// Assert that the hook invalidated the cache
		$this->assertSame( false, $wanCache->get( $cacheKey ) );
	}

	public function testOnCentralAuthGlobalUserGroupMembershipChangedRun() {
		// Test that the isolated hook operates as expected when the handler is run
		$services = $this->getServiceContainer();
		$wanCache = $services->getMainWANObjectCache();

		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->method( 'exists' )
			->with( 'GlobalContributions' )
			->willReturn( true );

		$handler = $this->getHookHandler( [
			'wanCache' => $wanCache,
			'specialPageFactory', $specialPageFactory,
		] );

		// Manually set the cache value to be invalidated
		$user = $this->getTestUser();
		$cacheKey = $wanCache->makeGlobalKey(
			'globalcontributions-ext-permissions',
			$services->get( 'CentralIdLookup' )->centralIdFromLocalUser( $user->getUserIdentity() )
		);
		$wanCache->set( $cacheKey, [] );
		$this->assertSame( [], $wanCache->get( $cacheKey ) );

		$handler->onCentralAuthGlobalUserGroupMembershipChanged(
			CentralAuthUser::getPrimaryInstance( $this->getTestUser()->getUserIdentity() ),
			[],
			[]
		);

		// Assert that the hook invalidated the cache
		$this->assertSame( false, $wanCache->get( $cacheKey ) );
	}
}
