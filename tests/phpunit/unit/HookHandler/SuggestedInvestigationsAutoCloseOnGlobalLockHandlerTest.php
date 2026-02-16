<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalLockHandler;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserLockStatusChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\SuggestedInvestigationsAutoCloseOnGlobalLockHandler
 * @covers \MediaWiki\CheckUser\HookHandler\AbstractSuggestedInvestigationsAutoCloseHandler
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseOnGlobalLockHandlerTest extends MediaWikiUnitTestCase {

	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private UserIdentityLookup&MockObject $userIdentityLookup;
	private SuggestedInvestigationsAutoCloseOnGlobalLockHandler $handler;

	/**
	 * @return void
	 */
	private function mockGetUserIdentityByName( ?UserIdentityValue $user ): void {
		$this->userIdentityLookup->expects( $this->once() )
			->method( 'getUserIdentityByName' )
			->with( 'TestUser' )
			->willReturn( $user );
	}

	protected function setUp(): void {
		parent::setUp();

		if ( !interface_exists( CentralAuthGlobalUserLockStatusChangedHook::class ) ) {
			$this->markTestSkipped( 'CentralAuth extension is not available' );
		}

		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$this->userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$this->handler = new SuggestedInvestigationsAutoCloseOnGlobalLockHandler(
			$this->caseLookup,
			$this->jobQueueGroup,
			new NullLogger(),
			$this->userIdentityLookup
		);
	}

	public static function provideEarlyReturnCases(): iterable {
		yield 'Suggested Investigations feature disabled' => [
			'isLocked' => true,
			'isExtensionEnabled' => false,
			'localUserExists' => true,
			'localUserRegistered' => true,
		];
		yield 'local user not found' => [
			'isLocked' => true,
			'isExtensionEnabled' => true,
			'localUserExists' => false,
			'localUserRegistered' => false,
		];
		yield 'local user not registered' => [
			'isLocked' => true,
			'isExtensionEnabled' => true,
			'localUserExists' => true,
			'localUserRegistered' => false,
		];
		yield 'user is unlocked (isLocked=false)' => [
			'isLocked' => false,
			'isExtensionEnabled' => true,
			'localUserExists' => true,
			'localUserRegistered' => true,
		];
	}

	/**
	 * @dataProvider provideEarlyReturnCases
	 */
	public function testEarlyReturn(
		bool $isLocked, bool $isExtensionEnabled, bool $localUserExists, bool $localUserRegistered
	): void {
		$this->caseLookup->expects( $this->never() )
			->method( 'getOpenCaseIdsForUser' );
		$this->jobQueueGroup->expects( $this->never() )
			->method( 'lazyPush' );

		if ( $isLocked ) {
			$this->mockSuggestedInvestigationEnabled( $isExtensionEnabled );

			if ( $isExtensionEnabled ) {
				$localUser = null;
				if ( $localUserExists ) {
					$localUser = new UserIdentityValue( $localUserRegistered ? 1 : 0, 'TestUser' );
				}
				$this->mockGetUserIdentityByName( $localUser );
			} else {
				$this->userIdentityLookup->expects( $this->never() )
					->method( 'getUserIdentityByName' );
			}
		} else {
			$this->caseLookup->expects( $this->never() )
				->method( 'areSuggestedInvestigationsEnabled' );
			$this->userIdentityLookup->expects( $this->never() )
				->method( 'getUserIdentityByName' );
		}

		$this->handler->onCentralAuthGlobalUserLockStatusChanged(
			$this->getCentralAuthUserMock( 'TestUser' ),
			$isLocked
		);
	}

	private function getCentralAuthUserMock( string $name ): CentralAuthUser {
		$centralAuthUser = $this->createMock( CentralAuthUser::class );
		$centralAuthUser->expects( $this->atMost( 1 ) )
			->method( 'getName' )
			->willReturn( $name );

		return $centralAuthUser;
	}

	private function mockSuggestedInvestigationEnabled( bool $enabled ): void {
		$this->caseLookup->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( $enabled );
	}
}
