<?php

namespace MediaWiki\CheckUser\Tests\Unit\Api\Rest\Handler;

use HashConfig;
use MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler
 */
class UserAgentClientHintsHandlerTest extends MediaWikiUnitTestCase {
	use HandlerTestTrait;

	public function testRunWithClientHintsDisabled() {
		$config = new HashConfig( [ 'CheckUserClientHintsEnabled' => false ] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$handler = new UserAgentClientHintsHandler( $config, $revisionStore );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-no-match' ), 404 )
		);
		$this->executeHandler( $handler, new RequestData(), [], [], [ 'revision' => 1 ] );
	}

	public function testMissingRevision() {
		$config = new HashConfig( [ 'CheckUserClientHintsEnabled' => true ] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revisionStore->method( 'getRevisionById' )->willReturn( null );
		$handler = new UserAgentClientHintsHandler( $config, $revisionStore );
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'rest-nonexistent-revision' ), 404 )
		);
		$this->executeHandler( $handler, new RequestData(), [], [], [ 'revision' => 1 ] );
	}

	public function testMissingUser() {
		$config = new HashConfig( [ 'CheckUserClientHintsEnabled' => true ] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$revision->method( 'getUser' )->willReturn( null );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-revision-user-mismatch' ),
				401
			) );
		$handler = new UserAgentClientHintsHandler( $config, $revisionStore );
		$this->executeHandler( $handler, new RequestData(), [], [], [ 'revision' => 1 ] );
	}

	public function testUserDoesntMatchRevisionOwner() {
		$config = new HashConfig( [ 'CheckUserClientHintsEnabled' => true ] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 123 );
		$revision->method( 'getUser' )->willReturn( $user );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( new UserIdentityValue( 456, 'Foo' ) );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-revision-user-mismatch' ), 401
			) );
		$handler = new UserAgentClientHintsHandler( $config, $revisionStore );
		$this->executeHandler( $handler, new RequestData(), [], [], [ 'revision' => 1 ], [], $authority );
	}

	public function testUserMatchesRevisionOwner() {
		$config = new HashConfig( [ 'CheckUserClientHintsEnabled' => true ] );
		$revisionStore = $this->createMock( RevisionStore::class );
		$revision = $this->createMock( RevisionRecord::class );
		$user = new UserIdentityValue( 123, 'Foo' );
		$revision->method( 'getUser' )->willReturn( $user );
		$revisionStore->method( 'getRevisionById' )->willReturn( $revision );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( $user );
		$handler = new UserAgentClientHintsHandler( $config, $revisionStore );
		$response = $this->executeHandler(
			$handler, new RequestData(), [], [], [ 'revision' => 1 ], [ 'test' => 1 ], $authority
		);
		$this->assertSame( json_encode( [ "value" => true ] ), $response->getBody()->getContents() );
	}

}
