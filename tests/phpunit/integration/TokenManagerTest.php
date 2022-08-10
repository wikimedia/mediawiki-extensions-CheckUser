<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use Firebase\JWT\JWT;
use MediaWiki\CheckUser\TokenManager;
use MediaWiki\Session\SessionManager;
use MediaWikiIntegrationTestCase;
use MWTimestamp;

/**
 * Test class for TokenManager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\TokenManager
 */
class TokenManagerTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		MWTimestamp::setFakeTime( 0 );
		JWT::$timestamp = 60;
	}

	public function tearDown(): void {
		parent::tearDown();
		MWTimestamp::setFakeTime( null );
		JWT::$timestamp = null;
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
		$tokenManager->decode( $session, $encoded );
	}

	public function testDecodeSessionFailure() {
		$this->expectExceptionMessage( 'Signature verification failed' );

		$tokenManager = new TokenManager( 'abcdef' );
		$encoded = $tokenManager->encode( SessionManager::singleton()->getEmptySession(), [] );
		$tokenManager->decode( SessionManager::singleton()->getEmptySession(), $encoded );
	}
}
