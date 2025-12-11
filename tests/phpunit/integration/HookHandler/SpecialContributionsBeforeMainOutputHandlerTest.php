<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\SpecialContributionsBeforeMainOutputHandler;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\SpecialContributionsBeforeMainOutputHandler
 */
class SpecialContributionsBeforeMainOutputHandlerTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

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

		$mockOutputPage = $this->createMock( OutputPage::class );
		if ( $expectedSubtitle === null ) {
			$mockOutputPage->expects( $this->never() )->method( 'addSubtitle' );
		} else {
			$mockOutputPage->expects( $this->once() )->method( 'addSubtitle' )
				->willReturnCallback( function ( $sub ) use ( $expectedSubtitle ) {
					$this->assertStringContainsString( $expectedSubtitle, $sub );
				} );
		}

		$performer = $this->createMock( User::class );
		$performer->method( 'isRegistered' )
			->willReturn( true );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $performer );
		$context->setLanguage( 'qqx' );

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

		$mockLookup = $this->createMock( CheckUserTemporaryAccountsByIPLookup::class );
		$mockLookup->method( 'getBucketedCount' )
			->willReturn( [ 1, 1 ] );
		$mockLookup->method( 'getAggregateActiveTempAccountCount' )
			->willReturn( 1 );

		$services = $this->getServiceContainer();
		$hookHandler = new SpecialContributionsBeforeMainOutputHandler(
			$services->getTempUserConfig(),
			$mockLookup,
		);

		$mockUser = $this->createMock( User::class );
		$mockUser->method( 'getName' )
			->willReturn( $target );
		$mockUser->method( 'isRegistered' )
			->willReturn( $targetExists );
		$mockUser->method( 'isHidden' )
			->willReturn( $targetHidden );

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
		];
	}
}
