<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoIconsHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserInfoIconsHandler
 * @group Database
 */
class UserInfoIconsHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockAuthorityTrait;
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->enableAutoCreateTempUser();
	}

	private function getObjectUnderTest(): UserInfoIconsHandler {
		$services = $this->getServiceContainer();
		return new UserInfoIconsHandler(
			$services->get( 'CheckUserUserInfoCardButtonRenderer' ),
			$services->getUserNameUtils()
		);
	}

	/**
	 * @param string[] $users Names to ask about
	 * @param Authority|null $caller The calling authority
	 * @return string The raw response body
	 */
	private function getIcons( array $users, ?Authority $caller = null ): string {
		$response = $this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData( [
				'method' => 'POST',
				'headers' => [ 'Content-Type' => 'application/json' ],
				'bodyContents' => json_encode( [ 'users' => $users ] ),
			] ),
			[],
			[],
			[],
			[],
			$caller ?? $this->mockRegisteredNullAuthority()
		);

		$this->assertSame( 200, $response->getStatusCode() );
		return $response->getBody()->getContents();
	}

	private function createUser( string $name ): User {
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $name );
		$this->assertNotNull( $user );
		$user->addToDatabase();
		return $user;
	}

	/**
	 * @param User $user User to block
	 */
	private function blockUserIndefinitely( User $user ): void {
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$user,
				$this->getTestUser( [ 'suppress', 'sysop' ] )->getAuthority(),
				'infinity',
				'block for UserInfoIconHandlerTest'
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
	}

	public function testIconsForMixedBatchOfUsers(): void {
		$blockedUser = $this->createUser( 'UserInfoIconBlocked' );
		$this->blockUserIndefinitely( $blockedUser );
		$this->createUser( 'UserInfoIconPlain' );
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();

		$payload = json_decode( $this->getIcons( [
			'UserInfoIconBlocked',
			'UserInfoIconPlain',
			$tempUser->getName(),
		] ), true );

		$this->assertSame( [
			'UserInfoIconBlocked' => 'userBlocked',
			'UserInfoIconPlain' => 'userAvatar',
			$tempUser->getName() => 'userTemporary',
		], $payload['icons'] );
	}

	public function testUnknownNamesGetTheDefaultIcon(): void {
		$payload = json_decode( $this->getIcons( [
			'UserInfoIconDoesNotExist',
			'192.0.2.1',
			'Invalid|name',
		] ), true );

		$this->assertSame( [
			'UserInfoIconDoesNotExist' => 'userAvatar',
			'192.0.2.1' => 'userAvatar',
			'Invalid|name' => 'userAvatar',
		], $payload['icons'] );
	}

	public function testNamesAreLookedUpUnderTheirCanonicalForm(): void {
		$blockedUser = $this->createUser( 'UserInfoIcon canonical' );
		$this->blockUserIndefinitely( $blockedUser );

		$payload = json_decode( $this->getIcons( [ 'userInfoIcon_canonical' ] ), true );

		// The answer uses the name as given, not the canonical name
		$this->assertSame( [ 'userInfoIcon_canonical' => 'userBlocked' ], $payload['icons'] );
	}

	public function testNumericNamesStayAMap(): void {
		$body = $this->getIcons( [ '1234' ] );

		$this->assertSame( '{"icons":{"1234":"userAvatar"}}', $body );
	}

	public function testTooManyUsersAreRejected(): void {
		$users = [];
		for ( $i = 0; $i < 501; $i++ ) {
			$users[] = "UserInfoIconUser$i";
		}

		try {
			$this->getIcons( $users );
			$this->fail( 'Expected the request to be rejected' );
		} catch ( HttpException $e ) {
			$this->assertSame( 400, $e->getCode() );
		}
	}

	public function test500UsersAreAccepted(): void {
		$users = [];
		for ( $i = 0; $i < 500; $i++ ) {
			$users[] = "UserInfoIconUser$i";
		}

		$payload = json_decode( $this->getIcons( $users ), true );
		$this->assertCount( 500, $payload['icons'] );
	}

	public function testAnonViewerIsRejected(): void {
		try {
			$this->getIcons( [ 'User' ], $this->mockAnonNullAuthority() );
			$this->fail( 'Expected the request to be rejected' );
		} catch ( HttpException $e ) {
			$this->assertSame( 401, $e->getCode() );
		}
	}
}
