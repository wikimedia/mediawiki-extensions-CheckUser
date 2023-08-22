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

	public function testClientHintsSpecialPageConfigDisabled() {
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
			->onlyMethods( [ 'response', 'getHeader' ] )->getMock();
		$requestMock->method( 'getHeader' )->with( 'Sec-Ch-Ua' )
			->willReturn( '"Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"' );
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
			implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ),
			$fauxResponse->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsBeforePageDisplayDisabled() {
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'history' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecialPage' )->willReturn( true );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should not add ext.checkUser.clientHints to page if feature flag is off.
		$outputPage->expects( $this->never() )->method( 'addModules' );
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
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'history' ],
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
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'history' ],
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' . implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ) );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should add ext.checkUser.clientHints to page on GET with ?action=edit.
		$outputPage->expects( $this->once() )->method( 'addModules' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayExcludePOSTRequest() {
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
		// We should not add ext.checkUser.clientHints to page on POST.
		$outputPage->expects( $this->never() )->method( 'addModules' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );

		// Repeat the scenario, but with CheckUserClientHintsUnsetHeaderWhenPossible set to false.
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsActionQueryParameter' => [ 'edit', 'rollback' ],
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => false,
			] )
		);
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->never() )->method( 'header' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should not add ext.checkUser.clientHints to page on POST.
		$outputPage->expects( $this->never() )->method( 'addModules' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsSpecialPageBeforeExecuteExcludePOSTRequest() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$fauxResponse = new FauxResponse();
		$requestMock->method( 'response' )->willReturn( $fauxResponse );
		$requestMock->method( 'wasPosted' )->willReturn( true );
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

		// Repeat the scenario, but with CheckUserClientHintsUnsetHeaderWhenPossible set to false.
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->never() )->method( 'header' );
		$requestMock = $this->createMock( 'WebRequest' );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$fauxResponse = new FauxResponse();
		$requestMock->method( 'response' )->willReturn( $fauxResponse );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$special->method( 'getRequest' )->willReturn( $requestMock );
		$hookHandler = new ClientHints(
			new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => false,
			] )
		);
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	private function getDefaultClientHintHeaders(): array {
		return [
			'Sec-CH-UA' => '',
			'Sec-CH-UA-Arch' => 'architecture',
			'Sec-CH-UA-Bitness' => 'bitness',
			'Sec-CH-UA-Form-Factor' => '',
			'Sec-CH-UA-Full-Version-List' => 'fullVersionList',
			'Sec-CH-UA-Mobile' => 'mobile',
			'Sec-CH-UA-Model' => 'model',
			'Sec-CH-UA-Platform' => 'platform',
			'Sec-CH-UA-Platform-Version' => 'platformVersion',
			'Sec-CH-UA-WoW64' => ''
		];
	}

}
