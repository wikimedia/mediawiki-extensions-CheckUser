<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Block\Restriction\NamespaceRestriction;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoSharedBlockedAccountsHandler;
use MediaWiki\Extension\CheckUser\Services\CheckUserSharedBrowserVersionAccountsLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserSharedIPAccountsLookup;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardInstrumentation;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Session\Session;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoSharedBlockedAccountsHandler
 * @group CheckUser
 * @group Database
 */
class UserInfoSharedBlockedAccountsHandlerTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private WANObjectCache $wanCache;

	protected function setUp(): void {
		parent::setUp();
		$this->wanCache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	/**
	 * @param UserIdentity[] $usersOnSharedIPs
	 * @param UserIdentity[] $usersWithSharedBrowserVersion
	 * @param bool $ipComplete
	 * @param bool $browserVersionComplete
	 * @param int $targetEditCount
	 */
	private function getObjectUnderTest(
		array $usersOnSharedIPs = [],
		array $usersWithSharedBrowserVersion = [],
		bool $ipComplete = true,
		bool $browserVersionComplete = true,
		int $targetEditCount = 0
	): UserInfoSharedBlockedAccountsHandler {
		$sharedIPAccountsLookup = $this->createMock( CheckUserSharedIPAccountsLookup::class );
		$sharedIPAccountsLookup->method( 'getUsersOnSharedIPs' )
			->willReturn( [ 'users' => $usersOnSharedIPs, 'complete' => $ipComplete ] );

		$sharedBrowserVersionAccountsLookup = $this->createMock(
			CheckUserSharedBrowserVersionAccountsLookup::class
		);
		$sharedBrowserVersionAccountsLookup->method( 'getUsersWithSharedBrowserVersion' )
			->willReturn( [
				'users' => $usersWithSharedBrowserVersion,
				'complete' => $browserVersionComplete,
			] );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )->willReturn( $targetEditCount );

		$services = $this->getServiceContainer();
		return new UserInfoSharedBlockedAccountsHandler(
			$services->getUserFactory(),
			$userEditTracker,
			$services->getUserIdentityLookup(),
			$sharedIPAccountsLookup,
			$sharedBrowserVersionAccountsLookup,
			$services->get( 'CheckUserCompositeBlockChecker' ),
			$this->wanCache,
			$services->get( 'CheckUserUserInfoCardInstrumentation' )
		);
	}

	private function executeWithTarget(
		UserInfoSharedBlockedAccountsHandler $handler,
		string $targetName,
		Authority $authority,
		?Session $session = null
	): array {
		$response = $this->executeHandler(
			$handler,
			new RequestData( [
				'method' => 'POST',
				'headers' => [ 'Content-Type' => 'application/json' ],
				'pathParams' => [ 'name' => $targetName ],
				'bodyContents' => json_encode( [] ),
			] ),
			[],
			[],
			[],
			[],
			$authority,
			$session
		);
		return json_decode( $response->getBody()->getContents(), true );
	}

	private function getCheckUserAuthority(): Authority {
		return $this->getTestUser( [ 'checkuser' ] )->getAuthority();
	}

	private function blockUser(
		User $user,
		string $expiry = 'infinity',
		array $options = [],
		array $restrictions = []
	): void {
		$status = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$user,
				$this->getTestUser( [ 'sysop', 'suppress' ] )->getAuthority(),
				$expiry,
				'block for UserInfoSharedBlockedAccountsHandlerTest',
				$options,
				$restrictions
			)->placeBlock();
		$this->assertStatusGood( $status );
	}

	public function testBadToken() {
		try {
			$this->executeWithTarget(
				$this->getObjectUnderTest(),
				$this->getTestUser()->getUser()->getName(),
				$this->getCheckUserAuthority(),
				$this->getSession( false )
			);
			$this->fail( 'Expected LocalizedHttpException to be thrown' );
		} catch ( LocalizedHttpException $e ) {
			$this->assertSame( 403, $e->getCode() );
			$this->assertSame( 'rest-badtoken-missing', $e->getMessageValue()->getKey() );
		}
	}

	public function testLoggedOutAccessIsDenied() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-access-denied' ), 401 )
		);

		$this->executeWithTarget(
			$this->getObjectUnderTest(),
			$this->getTestUser()->getUser()->getName(),
			$this->getServiceContainer()->getUserFactory()->newAnonymous()
		);
	}

	public function testAccessDeniedWithoutCheckUserRight() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-access-denied' ), 403 )
		);

		$this->executeWithTarget(
			$this->getObjectUnderTest(),
			$this->getTestUser()->getUser()->getName(),
			$this->getTestUser()->getAuthority()
		);
	}

	public function testTargetDoesNotExist() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-userinfo-user-not-found' ), 404 )
		);

		$this->executeWithTarget(
			$this->getObjectUnderTest(),
			'UserThatDefinitelyDoesNotExist',
			$this->getCheckUserAuthority()
		);
	}

	public function testHiddenTargetIsNotFoundWithoutHideUserRight() {
		$target = $this->getMutableTestUser()->getUser();
		$this->blockUser( $target, 'infinity', [ 'isHideUser' => true ] );

		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-rest-userinfo-user-not-found' ), 404 )
		);

		$this->executeWithTarget(
			$this->getObjectUnderTest(),
			$target->getName(),
			$this->getCheckUserAuthority()
		);
	}

	public function testTargetWithTooManyEdits() {
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-userinfo-sharedblocked-too-many-edits', [ 100 ] ),
				403
			)
		);

		$this->executeWithTarget(
			$this->getObjectUnderTest( [], [], true, true, 100 ),
			$this->getTestUser()->getUser()->getName(),
			$this->getCheckUserAuthority()
		);
	}

	public function testReturnsZeroCountsWhenNoSharedAccounts() {
		$payload = $this->executeWithTarget(
			$this->getObjectUnderTest( [], [], true, false, 99 ),
			$this->getTestUser()->getUser()->getName(),
			$this->getCheckUserAuthority()
		);

		$this->assertSame( [
			'ip' => [ 'count' => 0, 'complete' => true ],
			'browserVersion' => [ 'count' => 0, 'complete' => false ],
		], $payload );
	}

	public function testCountsBlockedAccounts() {
		$indefinitelyBlocked = $this->getMutableTestUser()->getUser();
		$this->blockUser( $indefinitelyBlocked );
		$temporarilyBlocked = $this->getMutableTestUser()->getUser();
		$this->blockUser( $temporarilyBlocked, '1 day' );
		$partiallyBlocked = $this->getMutableTestUser()->getUser();
		$this->blockUser(
			$partiallyBlocked,
			'infinity',
			[ 'isPartial' => true ],
			[ new NamespaceRestriction( 0, NS_TALK ) ]
		);
		$notBlocked = $this->getMutableTestUser()->getUser();

		$payload = $this->executeWithTarget(
			$this->getObjectUnderTest(
				[ $indefinitelyBlocked, $temporarilyBlocked, $partiallyBlocked, $notBlocked ],
				[ $temporarilyBlocked, $notBlocked ],
				false,
				true
			),
			$this->getTestUser()->getUser()->getName(),
			$this->getCheckUserAuthority()
		);

		$this->assertSame( [
			'ip' => [ 'count' => 3, 'complete' => false ],
			'browserVersion' => [ 'count' => 1, 'complete' => true ],
		], $payload );
	}

	public static function provideHiddenSharedAccounts(): array {
		return [
			'Viewer without hideuser right' => [ [ 'checkuser' ], 1 ],
			'Viewer with hideuser right' => [ [ 'checkuser', 'suppress' ], 2 ],
		];
	}

	/** @dataProvider provideHiddenSharedAccounts */
	public function testHiddenSharedAccounts( array $viewerGroups, int $expectedCount ) {
		$hidden = $this->getMutableTestUser()->getUser();
		$this->blockUser( $hidden, 'infinity', [ 'isHideUser' => true ] );
		$blocked = $this->getMutableTestUser()->getUser();
		$this->blockUser( $blocked );

		$payload = $this->executeWithTarget(
			$this->getObjectUnderTest( [ $hidden, $blocked ], [ $hidden, $blocked ] ),
			$this->getTestUser()->getUser()->getName(),
			$this->getTestUser( $viewerGroups )->getAuthority()
		);

		$this->assertSame( $expectedCount, $payload['ip']['count'] );
		$this->assertSame( $expectedCount, $payload['browserVersion']['count'] );
	}

	public function testCachesResultForEachHiddenUserVisibility() {
		$sharedIPAccountsLookup = $this->createMock( CheckUserSharedIPAccountsLookup::class );
		$sharedIPAccountsLookup->expects( $this->exactly( 2 ) )
			->method( 'getUsersOnSharedIPs' )
			->willReturn( [ 'users' => [], 'complete' => true ] );
		$sharedBrowserVersionAccountsLookup = $this->createMock(
			CheckUserSharedBrowserVersionAccountsLookup::class
		);
		$sharedBrowserVersionAccountsLookup->expects( $this->exactly( 2 ) )
			->method( 'getUsersWithSharedBrowserVersion' )
			->willReturn( [ 'users' => [], 'complete' => true ] );

		$services = $this->getServiceContainer();
		$newHandler = fn () => new UserInfoSharedBlockedAccountsHandler(
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->getUserIdentityLookup(),
			$sharedIPAccountsLookup,
			$sharedBrowserVersionAccountsLookup,
			$services->get( 'CheckUserCompositeBlockChecker' ),
			$this->wanCache,
			$services->get( 'CheckUserUserInfoCardInstrumentation' )
		);

		$targetName = $this->getTestUser()->getUser()->getName();
		$viewer = $this->getCheckUserAuthority();
		$viewerWithHideUser = $this->getTestUser( [ 'checkuser', 'suppress' ] )->getAuthority();
		foreach ( [ $viewer, $viewer, $viewerWithHideUser, $viewerWithHideUser ] as $authority ) {
			$this->executeWithTarget( $newHandler(), $targetName, $authority );
		}
	}

	public function testRateLimiting() {
		$this->mergeMwGlobalArrayValue( 'wgRateLimits', [ 'checkuser-userinfo' => [
			'user' => [ 1, 86400 ],
		] ] );
		$instrumentation = $this->createMock( UserInfoCardInstrumentation::class );
		$instrumentation->expects( $this->once() )
			->method( 'onRateLimited' );
		$this->setService( 'CheckUserUserInfoCardInstrumentation', $instrumentation );

		$viewer = $this->getMutableTestUser( [ 'checkuser' ] )->getAuthority();
		$targetName = $this->getTestUser()->getUser()->getName();
		$this->executeWithTarget( $this->getObjectUnderTest(), $targetName, $viewer );

		$this->expectExceptionObject(
			new HttpException( 'Too many requests to user info data', 429 )
		);
		$this->executeWithTarget( $this->getObjectUnderTest(), $targetName, $viewer );
	}
}
