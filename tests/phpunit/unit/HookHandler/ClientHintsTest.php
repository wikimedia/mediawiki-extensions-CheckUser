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

/**
 * @covers \MediaWiki\CheckUser\HookHandler\ClientHints
 */
class ClientHintsTest extends MediaWikiUnitTestCase {

	public function testClientHintsEnabledConfigSwitch() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		// SpecialPage::getRequest() should never be called, because the global config
		// flag is off.
		$special->expects( $this->never() )->method( 'getRequest' );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageNotInAllowList() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		// SpecialPage::getRequest() should never be called, because the
		// SpecialPage name is not in the allow list.
		$special->expects( $this->never() )->method( 'getRequest' );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
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
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', ClientHints::CLIENT_HINT_HEADERS ),
			$fauxResponse->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsBeforePageDisplayDisabled() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback', 'undo' ],
			] )
		);
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecialPage' )->willReturn( true );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$skin = $this->createMock( Skin::class );
		// OutputPage::getRequest() should never be called, because the global config
		// flag is off.
		$outputPage->expects( $this->never() )->method( 'getRequest' );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayInvalidAction() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback', 'undo' ],
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ]
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		// ::header() is never called, because the action isn't in the allow list.
		$webResponseMock->expects( $this->never() )->method( 'header' );
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
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ]
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		// ::header() is called once, because the action is in the allow list.
		$webResponseMock->expects( $this->once() )->method( 'header' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

}
