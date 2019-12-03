<?php

use Firebase\JWT\JWT;
use MediaWiki\CheckUser\TokenManager;

/**
 * Test class for TokenManager class
 *
 * @group CheckUser
 *
 * @coversDefaultClass \MediaWiki\CheckUser\TokenManager
 */
class TokenManagerTest extends MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();
		\MWTimestamp::setFakeTime( 0 );
		JWT::$timestamp = 60;
	}

	public function tearDown() : void {
		parent::tearDown();
		\MWTimestamp::setFakeTime( null );
		JWT::$timestamp = null;
	}

	/**
	 * @covers \MediaWiki\CheckUser\TokenManager::getDataFromContext
	 * @covers \MediaWiki\CheckUser\TokenManager::decode
	 */
	public function testGetDataFromContext() {
		$token = implode( '.', [
			'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
			'eyJpc3MiOiJ0ZXN0Iiwic3ViIjoiQWRtaW4iLCJleHAiOjg2NDAwLCJkYXRhIjoiYU1lZVwv'
			. 'TFpTQkpFZ2tJcGMzdEJ6aDlqbTFtRlU2eGxsV1RDWHluU2wyUmpjWTRTNSJ9',
			'oMorADQiUXO6R-XO7K39bDcupJGirVgtC7IcCzUrtjQ',
		] );
		$request = new \FauxRequest(
			[
				'token' => $token,
			]
		);
		$context = $this->createMock( \IContextSource::class );
		$context->method( 'getRequest' )
			->willReturn( $request );
		$context->method( 'getUser' )
			->willReturn( User::newFromName( 'admin' ) );

		$tokenManager = new TokenManager( 'test', 'abcdef' );
		$data = $tokenManager->getDataFromContext( $context );
		$this->assertSame( [
			'targets' => [
				'Example',
				'10.0.0.0/8'
			],
		], $data );
	}

	/**
	 * @covers \MediaWiki\CheckUser\TokenManager::encode
	 * @covers \MediaWiki\CheckUser\TokenManager::decode
	 */
	public function testEncodeDecode() {
		$tokenManager = new TokenManager( 'test', 'abcdef' );
		$currentUser = User::newFromName( 'Admin' );
		$user = User::newFromName( 'Example' );
		$range = '10.0.0.0/8';
		$targets = [ $user->getName(), $range ];
		$encoded = $tokenManager->encode( $currentUser, [
			'targets' => $targets
		] );
		$this->assertSame(
			implode( '.', [
				'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
				'eyJpc3MiOiJ0ZXN0Iiwic3ViIjoiQWRtaW4iLCJleHAiOjg2NDAwLCJkYXRhIjoiYU1lZVwv'
				. 'TFpTQkpFZ2tJcGMzdEJ6aDlqbTFtRlU2eGxsV1RDWHluU2wyUmpjWTRTNSJ9',
				'oMorADQiUXO6R-XO7K39bDcupJGirVgtC7IcCzUrtjQ',
			] ),
			$encoded
		);

		$decoded = $tokenManager->decode( $currentUser, $encoded );
		$this->assertIsArray( $decoded );
		$this->assertCount( 1, $decoded );
		$this->arrayHasKey( 'targets', $decoded );
		$this->assertCount( 2, $decoded['targets'] );

		[ $decodedUser, $decodedRange ] = $decoded['targets'];

		$this->assertSame( $user->getName(), $decodedUser );
		$this->assertSame( $range, $decodedRange );
	}

	/**
	 * @covers \MediaWiki\CheckUser\TokenManager::decode
	 */
	public function testDecodeWikiFailure() {
		$this->expectExceptionMessage( 'Invalid Token' );

		$tokenManager = new TokenManager( 'test', 'abcdef' );
		$currentUser = User::newFromName( 'admin' );
		$encoded = $tokenManager->encode( $currentUser, [] );

		$tokenManager = new TokenManager( 'test2', 'abcdef' );
		$decoded = $tokenManager->decode( $currentUser, $encoded );
	}

	/**
	 * @covers \MediaWiki\CheckUser\TokenManager::decode
	 */
	public function testDecodeUserFailure() {
		$this->expectExceptionMessage( 'Invalid Token' );

		$tokenManager = new TokenManager( 'test', 'abcdef' );
		$currentUser = User::newFromName( 'admin' );
		$encoded = $tokenManager->encode( $currentUser, [] );

		$currentUser = User::newFromName( 'admin2' );
		$decoded = $tokenManager->decode( $currentUser, $encoded );
	}
}
