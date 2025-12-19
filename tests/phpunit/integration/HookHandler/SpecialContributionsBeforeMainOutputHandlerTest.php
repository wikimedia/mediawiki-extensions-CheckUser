<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use ArrayIterator;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\HookHandler\SpecialContributionsBeforeMainOutputHandler;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserSelectQueryBuilder;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialContributionsBeforeMainOutputHandler
 */
class SpecialContributionsBeforeMainOutputHandlerTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

	private function createHookHandler( array $performerPermissions ) {
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
		$hookHandler = new SpecialContributionsBeforeMainOutputHandler(
			$services->getTempUserConfig(),
			$mockTempAcctLookup,
			$mockUserIdentityLookup,
			$mockBlockStore,
		);

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

		$hookHandler = $this->createHookHandler( $permissions );

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
}
