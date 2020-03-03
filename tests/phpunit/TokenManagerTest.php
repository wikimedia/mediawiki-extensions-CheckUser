<?php

use Firebase\JWT\JWT;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\Session\SessionManager;

/**
 * Test class for TokenManager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\TokenManager
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

	public function testGetDataFromRequest() {
		$token = implode( '.', [
			'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
			'eyJleHAiOjg2NDAwLCJkYXRhIjoiSWNIMFFzUWdYMEJZ'
				. 'QlJYWG9HMnRoM0p0aXV6OWhmMVZRZEJkV2RPRnYxRFM3dzdEIn0',
			'JLeReeDltxD205gB_N1veuYmHVo1oMXLUA3UCX6l3OA',
		] );
		$request = new \FauxRequest(
			[
				'token' => $token,
			],
			false,
			[
				'CheckUserTokenKey' => base64_encode( 'test' ),
			]
		);

		$tokenManager = new TokenManager( 'abcdef' );
		$data = $tokenManager->getDataFromRequest( $request );
		$this->assertSame( [
			'targets' => [
				'Example',
				'10.0.0.0/8'
			],
		], $data );
	}

	public function testEncodeDecode() {
		$tokenManager = new TokenManager( 'abcdef' );
		$targets = [ 'Example', '10.0.0.0/8' ];
		$request = new \FauxRequest( [], false, [
			'CheckUserTokenKey' => base64_encode( 'test' ),
		] );

		$encoded = $tokenManager->encode( $request->getSession(), [
			'targets' => $targets
		] );

		$decoded = $tokenManager->decode( $request->getSession(), $encoded );
		$this->assertIsArray( $decoded );
		$this->assertCount( 1, $decoded );
		$this->arrayHasKey( 'targets', $decoded );
		$this->assertSame( $targets, $decoded['targets'] );
	}

	public function testDecodeSecretFailure() {
		$this->expectExceptionMessage( 'Signature verification failed' );

		$tokenManager = new TokenManager( 'abcdef' );
		$session = SessionManager::singleton()->getEmptySession();
		$encoded = $tokenManager->encode( $session, [] );

		$tokenManager = new TokenManager( 'abcdef2' );
		$decoded = $tokenManager->decode( $session, $encoded );
	}

	public function testDecodeSessionFailure() {
		$this->expectExceptionMessage( 'Signature verification failed' );

		$tokenManager = new TokenManager( 'abcdef' );
		$encoded = $tokenManager->encode( SessionManager::singleton()->getEmptySession(), [] );
		$decoded = $tokenManager->decode( SessionManager::singleton()->getEmptySession(), $encoded );
	}
}
