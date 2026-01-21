<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use ArrayIterator;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\HookHandler\SpecialContributionsHandler;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Pager\ContribsPager;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialContributionsHandler
 */
class SpecialContributionsHandlerTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

	private function createHookHandler( array $overrideServices = [] ) {
		$services = $this->getServiceContainer();
		$hookHandler = new SpecialContributionsHandler(
			$overrideServices['TempUserConfig'] ?? $services->getTempUserConfig(),
			$overrideServices['CheckUserTemporaryAccountsByIPLookup'] ??
				$this->createMock( CheckUserTemporaryAccountsByIPLookup::class ),
			$overrideServices['CheckUserPermissionManager'] ?? $this->createMock( CheckUserPermissionManager::class ),
			$overrideServices['SpecialPageFactory'] ?? $this->createMock( SpecialPageFactory::class ),
			$overrideServices['UserIdentityLookup'] ?? $this->createMock( UserIdentityLookup::class ),
			$overrideServices['DatabaseBlockStore'] ?? $this->createMock( DatabaseBlockStore::class ),
		);

		return $hookHandler;
	}

	private function createHookHandlerForBeforeMainOutput( array $performerPermissions ) {
		$mockTempAcctLookup = $this->createMock( CheckUserTemporaryAccountsByIPLookup::class );
		$mockTempAcctLookup->method( 'getBucketedCount' )
			->willReturn( [ 1, 1 ] );
		$mockTempAcctLookup->method( 'getAggregateActiveTempAccountCount' )
			->willReturn( 1 );
		$mockTempAcctLookup->method( 'get' )
			->willReturnCallback( static function () use ( $performerPermissions ) {
				if ( in_array( 'checkuser-temporary-account-no-preference', $performerPermissions ) ) {
					return Status::newGood( [ '~check-user-test-1' ] );
				} else {
					return Status::newFatal( 'checkuser-rest-access-denied' );
				}
			} );

		$mockUserSelectQueryBuilder = $this->createMock( UserSelectQueryBuilder::class );
		$mockUserSelectQueryBuilder->method( 'whereUserNames' )->willReturnSelf();
		$mockUserSelectQueryBuilder->method( 'caller' )->willReturnSelf();
		$mockUserSelectQueryBuilder->method( 'fetchUserIdentities' )
			->willReturn( new ArrayIterator( [] ) );

		$mockUserIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$mockUserIdentityLookup->method( 'newSelectQueryBuilder' )
			->willReturn( $mockUserSelectQueryBuilder );

		$mockBlockStore = $this->createMock( DatabaseBlockStore::class );
		$mockBlockStore->method( 'newListFromConds' )
			->willReturn( [] );

		$services = $this->getServiceContainer();
		$hookHandler = $this->createHookHandler( [
			'CheckUserTemporaryAccountsByIPLookup' => $mockTempAcctLookup,
			'UserIdentityLookup' => $mockUserIdentityLookup,
			'DatabaseBlockStore' => $mockBlockStore,
		] );

		return $hookHandler;
	}

	/** @dataProvider provideOnSpecialContributionsBeforeMainOutput */
	public function testOnSpecialContributionsBeforeMainOutput(
		bool $tempAccountsEnabled,
		string $target,
		bool $targetExists,
		bool $targetHidden,
		array $permissions,
		?string $expectedSubtitle
	) {
		if ( $tempAccountsEnabled ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser( [ 'known' => false ] );
		}

		$hookHandler = $this->createHookHandlerForBeforeMainOutput( $permissions );

		$performer = $this->createMock( User::class );
		$performer->method( 'isRegistered' )
			->willReturn( true );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $performer );
		$context->setLanguage( 'qqx' );

		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getName' )
			->willReturn( $target );
		$mockUser->method( 'isRegistered' )
			->willReturn( $targetExists );
		$mockUser->method( 'isHidden' )
			->willReturn( $targetHidden );

		$mockOutputPage = $this->createMock( OutputPage::class );
		if ( $expectedSubtitle === null ) {
			$mockOutputPage->expects( $this->never() )->method( 'addSubtitle' );
		} else {
			$mockOutputPage->expects( $this->once() )->method( 'addSubtitle' )
				->willReturnCallback( function ( $sub ) use ( $expectedSubtitle ) {
					$this->assertStringContainsString( $expectedSubtitle, $sub );
				} );
		}

		$mockSpecialPage = $this->getMockBuilder( ContributionsSpecialPage::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getOutput', 'getAuthority', 'getContext' ] )
			->getMock();
		$mockSpecialPage->method( 'getOutput' )
			->willReturn( $mockOutputPage );
		$mockSpecialPage->method( 'getAuthority' )
			->willReturn( $this->mockUserAuthorityWithPermissions( $performer, $permissions ) );
		$mockSpecialPage->method( 'getContext' )
			->willReturn( $context );

		$hookHandler->onSpecialContributionsBeforeMainOutput( $targetExists ? 1 : 0, $mockUser, $mockSpecialPage );
	}

	public function provideOnSpecialContributionsBeforeMainOutput(): array {
		return [
			'Temporary user, exists' => [
				'tempAccountsEnabled' => true,
				'target' => '~check-user-test-1',
				'targetExists' => true,
				'targetHidden' => false,
				'permissions' => [],
				'expectedSubtitle' => 'checkuser-userinfocard-temporary-account-bucketcount',
			],
			'Temporary user, doesn\'t exist' => [
				'tempAccountsEnabled' => true,
				'target' => '~check-user-test-10000',
				'targetExists' => false,
				'targetHidden' => false,
				'permissions' => [],
				'expectedSubtitle' => null,
			],
			'Named user, exists' => [
				'tempAccountsEnabled' => true,
				'target' => 'Admin',
				'targetExists' => true,
				'targetHidden' => false,
				'permissions' => [],
				'expectedSubtitle' => null,
			],
			'Named user, doesn\'t exist' => [
				'tempAccountsEnabled' => true,
				'target' => 'Admin',
				'targetExists' => false,
				'targetHidden' => false,
				'permissions' => [],
				'expectedSubtitle' => null,
			],
			'Anonymous user' => [
				'tempAccountsEnabled' => true,
				'target' => '127.0.0.1',
				'targetExists' => false,
				'targetHidden' => false,
				'permissions' => [],
				'expectedSubtitle' => null,
			],
			'Hidden temporary user, no special permissions' => [
				'tempAccountsEnabled' => true,
				'target' => '~check-user-test-1',
				'targetExists' => true,
				'targetHidden' => true,
				'permissions' => [],
				'expectedSubtitle' => null,
			],
			'Hidden temporary user, hideuser permissions' => [
				'tempAccountsEnabled' => true,
				'target' => '~check-user-test-1',
				'targetExists' => true,
				'targetHidden' => true,
				'permissions' => [ 'hideuser' ],
				'expectedSubtitle' => 'checkuser-userinfocard-temporary-account-bucketcount',
			],
			'IP target, has TAIV right' => [
				'tempAccountsEnabled' => true,
				'target' => '127.0.0.1',
				'targetExists' => false,
				'targetHidden' => false,
				'permissions' => [ 'checkuser-temporary-account-no-preference' ],
				'expectedSubtitle' => 'checkuser-contributions-temporary-accounts-on-ip',
			],
			'IP target, has TAIV right, temp accounts not known' => [
				'tempAccountsEnabled' => false,
				'target' => '127.0.0.1',
				'targetExists' => false,
				'targetHidden' => false,
				'permissions' => [ 'checkuser-temporary-account-no-preference' ],
				'expectedSubtitle' => null,
			],
		];
	}

	/** @dataProvider provideOnSpecialContributions__getForm__filters */
	public function testOnSpecialContributions__getForm__filters(
		bool $hasRights,
		bool $isTemp,
		string $pageName,
		int $expectedFiltersCount
	) {
		$this->enableAutoCreateTempUser();

		$handler = $this->createHookHandler( [
			'CheckUserPermissionManager' => $this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
		] );

		if ( $hasRights ) {
			$user = $this->mockRegisteredUltimateAuthority();
		} else {
			$user = $this->mockRegisteredNullAuthority();
		}

		$target = $isTemp ? '~check-user-test-1' : 'TestUser';

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getText' )->willReturn( $target );

		$specialPage = $this->createMock( ContributionsSpecialPage::class );
		$specialPage->method( 'getUser' )->willReturn( $user );
		$specialPage->method( 'getRequest' )->willReturn( $request );
		$specialPage->method( 'getName' )->willReturn( $pageName );

		$filters = [];

		$handler->onSpecialContributions__getForm__filters( $specialPage, $filters );

		$this->assertCount( $expectedFiltersCount, $filters );
	}

	public static function provideOnSpecialContributions__getForm__filters() {
		return [
			'Add checkbox' => [
				'hasRights' => true,
				'isTemp' => true,
				'pageName' => 'Contributions',
				'expected' => 1,
			],
			'Unsupported contributions page' => [
				'hasRights' => true,
				'isTemp' => true,
				'pageName' => 'DeletedContributions',
				'expected' => 0,
			],
			'User does not have permission to view IPs' => [
				'hasRights' => false,
				'isTemp' => true,
				'pageName' => 'Contributions',
				'expected' => 0,
			],
			'Target is not temporary user' => [
				'hasRights' => false,
				'isTemp' => true,
				'pageName' => 'Contributions',
				'expected' => 0,
			],
		];
	}

	/** @dataProvider provideOnContribsPager__getQueryInfo */
	public function testOnContribsPager__getQueryInfo(
		bool $hasRights,
		bool $isTemp,
		bool $showRelated,
		string $pageName,
		$expected
	) {
		$this->enableAutoCreateTempUser();

		$userLookup = $this->createMock( UserIdentityLookup::class );
		$userLookup->method( 'getUserIdentityByName' )
			->willReturn( $this->createMock( UserIdentity::class ) );

		$lookupStatus = $this->createMock( StatusValue::class );
		$lookupStatus->method( 'isGood' )->willReturn( true );
		$lookupStatus->method( 'getValue' )->willReturn( [ '~check-user-test-2', '~check-user-test-3' ] );

		$tempLookup = $this->createMock( CheckUserTemporaryAccountsByIPLookup::class );
		$tempLookup->method( 'getActiveTempAccountNames' )->willReturn( $lookupStatus );

		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->method( 'resolveAlias' )->willReturn( [ $pageName ] );

		$handler = $this->createHookHandler( [
			'CheckUserTemporaryAccountsByIPLookup' => $tempLookup,
			'CheckUserPermissionManager' => $this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			'SpecialPageFactory' => $specialPageFactory,
			'UserIdentityLookup' => $userLookup,
		] );

		if ( $hasRights ) {
			$user = $this->mockRegisteredUltimateAuthority();
		} else {
			$user = $this->mockRegisteredNullAuthority();
		}

		$target = $isTemp ? '~check-user-test-1' : 'TestUser';

		$request = $this->createMock( WebRequest::class );
		$request->method( 'getText' )->willReturn( $target );
		$request->method( 'getBool' )->willReturn( $showRelated );

		$context = $this->createMock( RequestContext::class );
		$context->method( 'getRequest' )->willReturn( $request );
		$context->method( 'getTitle' )->willReturn( $this->createMock( Title::class ) );

		$pager = $this->createMock( ContribsPager::class );
		$pager->method( 'getUser' )->willReturn( $user );
		$pager->method( 'getContext' )->willReturn( $context );

		$queryInfo = [ 'conds' => [ 'actor_name' => $target ] ];

		$handler->onContribsPager__getQueryInfo( $pager, $queryInfo );

		if ( is_array( $expected ) ) {
			$this->assertArrayEquals( $expected, $queryInfo['conds']['actor_name'] );
		} else {
			$this->assertSame( $expected, $queryInfo['conds']['actor_name'] );
		}
	}

	public static function provideOnContribsPager__getQueryInfo() {
		return [
			'Add extra temporary accounts' => [
				'hasRights' => true,
				'isTemp' => true,
				'showRelated' => true,
				'pageName' => 'Contributions',
				'expected' => [ '~check-user-test-1', '~check-user-test-2', '~check-user-test-3' ],
			],
			'Unsupported contributions page' => [
				'hasRights' => true,
				'isTemp' => true,
				'showRelated' => true,
				'pageName' => 'GlobalContributions',
				'expected' => '~check-user-test-1',
			],
			'User chooses not to show extra temporary accounts' => [
				'hasRights' => true,
				'isTemp' => true,
				'showRelated' => false,
				'pageName' => 'Contributions',
				'expected' => '~check-user-test-1',
			],
			'User does not have permission to view IPs' => [
				'hasRights' => false,
				'isTemp' => true,
				'showRelated' => true,
				'pageName' => 'Contributions',
				'expected' => '~check-user-test-1',
			],
			'Target is not temporary user' => [
				'hasRights' => false,
				'isTemp' => true,
				'showRelated' => true,
				'pageName' => 'Contributions',
				'expected' => '~check-user-test-1',
			],
		];
	}
}
