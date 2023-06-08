<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use HashConfig;
use MediaWiki\CheckUser\HookHandler\ClientHints;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\Request\WebResponse;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use OutputPage;
use Skin;
use SpecialPage;
use WebRequest;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\ClientHints
 */
class ClientHintsTest extends MediaWikiUnitTestCase {

	public function testClientHintsEnabledConfigSwitch() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$webResponse->expects( $this->never() )->method( 'header' );
		$special->method( 'getRequest' )->willReturn( $webRequest );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders()
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageNotInAllowList() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$webResponse->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$special->method( 'getRequest' )->willReturn( $webRequest );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageRequested() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$requestMock = $this->getMockBuilder( FauxRequest::class )
			->onlyMethods( [ 'response' ] )->getMock();
		$fauxResponse = new FauxResponse();
		$requestMock->method( 'response' )->willReturn( $fauxResponse );
		$special->method( 'getRequest' )->willReturn( $requestMock );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', $this->getDefaultClientHintHeaders() ),
			$fauxResponse->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsBeforePageDisplayDisabled() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecialPage' )->willReturn( true );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		// ::header() should never be called, because global config flag is off.
		$webResponse->expects( $this->never() )->method( 'header' );
		$outputPage->method( 'getRequest' )->willReturn( $webRequest );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayInvalidAction() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback' ],
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'foo' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayValidAction() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback', 'undo' ],
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' . implode( ', ', $this->getDefaultClientHintHeaders() ) );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	private function getDefaultClientHintHeaders(): array {
		return [
			'Sec-CH-UA',
			'Sec-CH-UA-Arch',
			'Sec-CH-UA-Bitness',
			'Sec-CH-UA-Form-Factor',
			'Sec-CH-UA-Full-Version-List',
			'Sec-CH-UA-Mobile',
			'Sec-CH-UA-Model',
			'Sec-CH-UA-Platform',
			'Sec-CH-UA-Platform-Version',
			'Sec-CH-UA-WoW64'
		];
	}

}
