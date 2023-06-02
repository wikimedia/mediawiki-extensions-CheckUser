<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use HashConfig;
use MediaWiki\CheckUser\HookHandler\ClientHints;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWikiUnitTestCase;
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

	public function testClientHintsRequested() {
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

}
