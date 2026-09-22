<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiEditPage;
use MediaWiki\Api\ApiLogout;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\HookHandler\ClientHints;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Storage\EditResult;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StatusValue;
use TypeError;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\ClientHints
 */
class ClientHintsTest extends MediaWikiUnitTestCase {

	public function testClientHintsSpecialPageConfigDisabled() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$webRequest = $this->createMock( WebRequest::class );
		$webResponse = $this->createNoOpMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$special->method( 'getRequest' )->willReturn( $webRequest );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
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
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
	}

	public function testClientHintsSpecialPageRequested() {
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )->willReturn( 'Foo' );
		$request = new FauxRequest();
		$request->setHeader( 'Sec-Ch-Ua', '"Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"' );
		$special->method( 'getRequest' )->willReturn( $request );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ),
			$request->response()->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsSpecialPageRequestedWhenConfigSpecifiesHeaderString() {
		$mockRequest = new FauxRequest();
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$special->method( 'getRequest' )
			->willReturn( $mockRequest );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => 'header' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onSpecialPageBeforeExecute( $special, null );
		$this->assertSame(
			implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ),
			$mockRequest->response()->getHeader( 'ACCEPT-CH' )
		);
	}

	public function testClientHintsSpecialPageRequestedWhenConfigSpecifiesJsAPI() {
		// We load ext.checkUser.clientHints on all pages, regardless of whether
		// CheckUserClientHintsSpecialPages says "js" or "header".
		$mockRequest = new FauxRequest();
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$special->method( 'getRequest' )
			->willReturn( $mockRequest );
		// Expect that the ext.checkUser.clientHints module gets added to the special page
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => 'js' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( WebRequest::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithClientHintsSpecialPage() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( WebRequest::class );
		$webResponseMock = $this->createNoOpMock( WebResponse::class );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js', 'header' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithClientHintsSpecialPageJsOnly() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( WebRequest::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Foo' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testBeforePageDisplayDoesNotOverrideHeaderInSpecialPageExecuteWithNonClientHintsSpecialPage() {
		$special = $this->createMock( SpecialPage::class );
		$skinMock = $this->createMock( Skin::class );
		$requestMock = $this->createMock( WebRequest::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$special->method( 'getOutput' )
			->willReturn( $outputPage );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$titleMock = $this->createMock( Title::class );
		$titleMock->method( 'isSpecialPage' )->willReturn( true );
		$outputPage->method( 'getTitle' )->willReturn( $titleMock );
		$special = $this->createMock( SpecialPage::class );
		$special->method( 'getName' )
			->willReturn( 'Bar' );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Foo' => [ 'js', 'header' ] ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$hookHandler->onBeforePageDisplay( $outputPage, $skinMock );
	}

	public function testClientHintsBeforePageDisplayDisabled() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => false,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$title = $this->createMock( Title::class );
		$title->method( 'isSpecialPage' )->willReturn( true );
		// We should not add ext.checkUser.clientHints to page if feature flag is off.
		$outputPage = $this->createNoOpMock( OutputPage::class, [ 'getTitle', 'getRequest' ] );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$webRequest = $this->createMock( WebRequest::class );
		// WebResponse::header should never be called, because global config flag is off.
		$webResponse = $this->createNoOpMock( WebResponse::class );
		$webRequest->method( 'response' )->willReturn( $webResponse );
		$outputPage->method( 'getRequest' )->willReturn( $webRequest );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayUnsetsHeader() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should add ext.checkUser.clientHints to page
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( WebRequest::class );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayAlwaysSetHeaders() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' . implode( ', ', array_keys( $this->getDefaultClientHintHeaders() ) ) );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We should add ext.checkUser.clientHints to page
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( WebRequest::class );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	public function testClientHintsBeforePageDisplayPOSTRequest() {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createMock( WebResponse::class );
		$webResponseMock->expects( $this->once() )->method( 'header' )
			->with( 'Accept-CH: ' );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		// We add ext.checkUser.clientHints to page on POST, because rollback action
		// using the JS confirmable (T215020) does a POST.
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( WebRequest::class );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );

		// Repeat the scenario, but with CheckUserClientHintsUnsetHeaderWhenPossible set to false.
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => false,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => false,
				'CheckUserAlwaysSetClientHintHeaders' => false,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$title = $this->createMock( Title::class );
		$webResponseMock = $this->createNoOpMock( WebResponse::class );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$outputPage->expects( $this->once() )->method( 'addModules' )
			->with( 'ext.checkUser.clientHints' );
		$requestMock = $this->createMock( WebRequest::class );
		$requestMock->method( 'getRawVal' )->with( 'action' )->willReturn( 'edit' );
		$requestMock->method( 'response' )->willReturn( $webResponseMock );
		$requestMock->method( 'wasPosted' )->willReturn( true );
		$outputPage->method( 'getRequest' )->willReturn( $requestMock );
		$skin = $this->createMock( Skin::class );
		$hookHandler->onBeforePageDisplay( $outputPage, $skin );
	}

	/** @dataProvider provideApiGetAllowedParams */
	public function testApiGetAllowedParams(
		string $apiModuleClass,
		string $moduleName,
		bool $clientHintsEnabled,
		bool $clientHintsInEditRequest,
		bool $shouldAddClientHintsParam
	) {
		$special = $this->createMock( SpecialPage::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getPage' )->willReturn( $special );
		/** @var ApiBase|MockObject $module */
		$module = $this->createMock( $apiModuleClass );
		$module->method( 'getModuleName' )->willReturn( $moduleName );
		$hookHandler = $this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => $clientHintsEnabled,
				'CheckUserClientHintsInEditRequest' => $clientHintsInEditRequest,
				'CheckUserClientHintsSpecialPages' => [ 'Bar' ],
				'CheckUserClientHintsHeaders' => $this->getDefaultClientHintHeaders(),
				'CheckUserClientHintsUnsetHeaderWhenPossible' => true,
			] ),
			'specialPageFactory' => $specialPageFactoryMock,
		] );
		$params = [];
		$hookHandler->onAPIGetAllowedParams( $module, $params, 0 );
		if ( $shouldAddClientHintsParam ) {
			$this->assertSame( 'checkuserclienthints', array_key_first( $params ) );
		} else {
			$this->assertSame( [], $params );
		}
	}

	public static function provideApiGetAllowedParams(): array {
		return [
			'ApiLogout module with Client Hints enabled' => [
				'apiModuleClass' => ApiLogout::class,
				'moduleName' => 'logout',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => true,
			],
			'ApiLogout module without Client Hints enabled' => [
				'apiModuleClass' => ApiLogout::class,
				'moduleName' => 'logout',
				'clientHintsEnabled' => false,
				'clientHintsInEditRequest' => false,
				'shouldAddClientHintsParam' => false,
			],
			'ApiQuery module' => [
				'apiModuleClass' => ApiQuery::class,
				'moduleName' => 'query',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => false,
			],
			'Edit module' => [
				'apiModuleClass' => ApiEditPage::class,
				'moduleName' => 'edit',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => true,
			],
			'VisualEditor save module' => [
				'apiModuleClass' => ApiBase::class,
				'moduleName' => 'visualeditoredit',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => true,
			],
			'DiscussionTools save module' => [
				'apiModuleClass' => ApiBase::class,
				'moduleName' => 'discussiontoolsedit',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => true,
			],
			'Edit module, without Client Hints enabled' => [
				'apiModuleClass' => ApiEditPage::class,
				'moduleName' => 'edit',
				'clientHintsEnabled' => false,
				'clientHintsInEditRequest' => false,
				'shouldAddClientHintsParam' => false,
			],
			'Edit module, without the edit request flag' => [
				'apiModuleClass' => ApiEditPage::class,
				'moduleName' => 'edit',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => false,
				'shouldAddClientHintsParam' => false,
			],
			'Another write module' => [
				'apiModuleClass' => ApiBase::class,
				'moduleName' => 'delete',
				'clientHintsEnabled' => true,
				'clientHintsInEditRequest' => true,
				'shouldAddClientHintsParam' => false,
			],
		];
	}

	public function testPageSaveCompleteForSuccessfulHeaderStorage(): void {
		$actualClientHintsData = null;

		$userAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$userAgentClientHintsManager->expects( $this->once() )
			->method( 'insertClientHintValues' )
			->with( $this->anything(), 123, 'revision' )
			->willReturnCallback( static function ( $clientHintsData ) use ( &$actualClientHintsData ) {
				$actualClientHintsData = $clientHintsData;
				return StatusValue::newGood();
			} );

		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )
			->willReturn( 123 );

		$editResult = $this->createMock( EditResult::class );
		$editResult->method( 'isNullEdit' )
			->willReturn( false );

		RequestContext::getMain()->getRequest()->setHeaders( [
			'x-is-browser' => '30',
			'x-ja3n' => 'testingabc',
			'x-ja4h' => 'abc',
			'architecture' => 'should-be-ignored',
		] );

		$objectUnderTest = $this->getObjectUnderTest( [
			'userAgentClientHintsManager' => $userAgentClientHintsManager,
			'logger' => $this->createNoOpMock( LoggerInterface::class ),
		] );
		$objectUnderTest->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( User::class ),
			'test',
			0,
			$revisionRecord,
			$editResult
		);

		$this->assertInstanceOf( ClientHintsData::class, $actualClientHintsData );
		$this->assertArrayEquals(
			[
				'architecture' => null,
				'isBrowser' => 30,
				'ja3n' => 'testingabc',
				'ja4h' => 'abc',
				'bitness' => null,
				'brands' => null,
				'formFactor' => null,
				'fullVersionList' => null,
				'mobile' => null,
				'model' => null,
				'platform' => null,
				'platformVersion' => null,
				'woW64' => null,
			],
			$actualClientHintsData->jsonSerialize(),
			false,
			true,
			'Client Hints data being stored was not as expected'
		);
	}

	public function testPageSaveCompleteOnTypeError(): void {
		// Throw a TypeError from ::insertClientHintsValues. We would have mocked it being thrown from
		// ClientHintsData::newFromRequestHeaders, but it's not possible to mock static methods in PHPUnit
		$typeError = new TypeError();
		$userAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$userAgentClientHintsManager->expects( $this->once() )
			->method( 'insertClientHintValues' )
			->willThrowException( $typeError );

		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )
			->willReturn( 123 );

		$editResult = $this->createMock( EditResult::class );
		$editResult->method( 'isNullEdit' )
			->willReturn( false );

		$mockLogger = $this->createMock( LoggerInterface::class );
		$mockLogger->expects( $this->once() )
			->method( 'warning' )
			->with(
				'Invalid data present in Client Hints headers when storing Client Hints data for {eventType} ID ' .
				'{eventId}. Not storing this data. Client Hints headers: {clientHintsHeaders}',
				[
					'eventType' => 'revision',
					'eventId' => 123,
					'clientHintsHeaders' => [
						'x-is-browser' => '30',
						'x-ja3n' => 'testingabc',
						'x-ja4h' => 'abc',
					],
					'exception' => $typeError,
				]
			);

		RequestContext::getMain()->getRequest()->setHeaders( [
			'x-is-browser' => '30',
			'x-ja3n' => 'testingabc',
			'x-ja4h' => 'abc',
			'architecture' => 'should-be-ignored',
		] );

		$objectUnderTest = $this->getObjectUnderTest( [
			'userAgentClientHintsManager' => $userAgentClientHintsManager,
			'logger' => $mockLogger,
		] );
		$objectUnderTest->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( User::class ),
			'test',
			0,
			$revisionRecord,
			$editResult
		);
	}

	/** @dataProvider provideScriptSuppliedDataOnSave */
	public function testPageSaveCompleteStoresRequestFieldData(
		bool $scriptSentData,
		array $expectedStoredArchitectures,
		bool $expectsSignal,
		bool $clientHintsEnabled = true,
		bool $inEditRequest = true,
		?string $rawField = null,
		bool $savedByRequestUser = true
	): void {
		$editResult = $this->createMock( EditResult::class );
		$editResult->method( 'isNullEdit' )->willReturn( false );
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )->willReturn( 123 );
		$storedValues = [];
		$storedReferences = [];

		$userAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$userAgentClientHintsManager->method( 'insertClientHintValues' )
			->willReturnCallback( static function (
				ClientHintsData $data,
				int $referenceId,
				string $type
			) use ( &$storedValues, &$storedReferences ) {
				$storedValues[] = $data->jsonSerialize();
				$storedReferences[] = [ $referenceId, $type ];
				return StatusValue::newGood();
			} );

		$user = $this->createMock( User::class );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'equals' )->willReturn( $savedByRequestUser );

		$suggestedInvestigationsTrigger = $this->createMock( SuggestedInvestigationsTrigger::class );
		$suggestedInvestigationsTrigger->expects( $expectsSignal ? $this->once() : $this->never() )
			->method( 'matchSignalsAgainstUserInJob' )
			->with(
				$user,
				SuggestedInvestigationsSignalMatchService::EVENT_CLIENT_HINTS_SAVED,
				$this->callback( static fn ( array $data ) => $data['revId'] === 123 &&
					$data['clientHints']['architecture'] === 'arm' )
			);

		$params = [];
		if ( $scriptSentData ) {
			$params[ClientHintsData::REQUEST_FIELD] = $rawField ?? json_encode( [ 'architecture' => 'arm' ] );
		}
		// This value can only arrive in the headers.
		$request = new FauxRequest( $params, true );
		$request->setHeaders( [ 'x-ja3n' => 'abc' ] );
		RequestContext::getMain()->setRequest( $request );

		$this->getObjectUnderTest( [
			'config' => new HashConfig( [
				'CheckUserClientHintsEnabled' => $clientHintsEnabled,
				'CheckUserClientHintsInEditRequest' => $inEditRequest,
			] ),
			'userAgentClientHintsManager' => $userAgentClientHintsManager,
			'suggestedInvestigationsTrigger' => $suggestedInvestigationsTrigger,
		] )->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$user,
			'test',
			0,
			$revisionRecord,
			$editResult
		);

		$this->assertSame(
			$expectedStoredArchitectures,
			array_column( $storedValues, 'architecture' )
		);
		// The header-only value is stored either way.
		$this->assertSame( [ 'ja3n' ], array_keys( array_filter( $storedValues[0] ) ) );
		$this->assertSame(
			array_fill( 0, count( $expectedStoredArchitectures ), [ 123, 'revision' ] ),
			$storedReferences,
			'Everything is stored against the revision the hook reports'
		);
	}

	public static function provideScriptSuppliedDataOnSave() {
		// The first stored value always comes from the headers, which hold no
		// architecture. A second value means the request field was stored too.
		return [
			'Client side code sent Client Hints' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null, 'arm' ],
				'expectsSignal' => true,
			],
			'Client side code sent nothing' => [
				'scriptSentData' => false,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
			],
			'Client side code sent Client Hints, feature off' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
				'clientHintsEnabled' => false,
			],
			'Client side code sent Client Hints, edit request flag off' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
				'clientHintsEnabled' => true,
				'inEditRequest' => false,
			],
			// A client can send a shape that has no rows, or one that can't make rows.
			'Client side code sent an empty object' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
				'clientHintsEnabled' => true,
				'inEditRequest' => true,
				'rawField' => '{}',
			],
			'Client side code sent a shape that cannot be stored' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
				'clientHintsEnabled' => true,
				'inEditRequest' => true,
				'rawField' => '{"brands":[[["x"]]]}',
			],
			'A second save in the request, by another user' => [
				'scriptSentData' => true,
				'expectedStoredArchitectures' => [ null ],
				'expectsSignal' => false,
				'clientHintsEnabled' => true,
				'inEditRequest' => true,
				'rawField' => null,
				'savedByRequestUser' => false,
			],
		];
	}

	public function testPageSaveCompleteForNullEdit(): void {
		$editResult = $this->createMock( EditResult::class );
		$editResult->method( 'isNullEdit' )
			->willReturn( true );

		$objectUnderTest = $this->getObjectUnderTest( [
			'userAgentClientHintsManager' => $this->createNoOpMock( UserAgentClientHintsManager::class ),
			'logger' => $this->createNoOpMock( LoggerInterface::class ),
		] );
		$objectUnderTest->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( User::class ),
			'test',
			0,
			$this->createMock( RevisionRecord::class ),
			$editResult
		);
	}

	private function getObjectUnderTest( array $overrides = [] ): ClientHints {
		return new ClientHints(
			$overrides['config'] ?? new HashConfig( [
				'CheckUserClientHintsEnabled' => true,
				'CheckUserClientHintsInEditRequest' => true,
			] ),
			$overrides['specialPageFactory'] ?? $this->createMock( SpecialPageFactory::class ),
			$overrides['userAgentClientHintsManager'] ?? $this->createMock( UserAgentClientHintsManager::class ),
			$overrides['jobQueueGroup'] ?? $this->createMock( JobQueueGroup::class ),
			$overrides['logger'] ?? new NullLogger(),
			$overrides['suggestedInvestigationsTrigger']
				?? $this->createMock( SuggestedInvestigationsTrigger::class )
		);
	}

	private function getDefaultClientHintHeaders(): array {
		return [
			'Sec-CH-UA' => '',
			'Sec-CH-UA-Arch' => 'architecture',
			'Sec-CH-UA-Bitness' => 'bitness',
			'Sec-CH-UA-Form-Factors' => '',
			'Sec-CH-UA-Full-Version-List' => 'fullVersionList',
			'Sec-CH-UA-Mobile' => 'mobile',
			'Sec-CH-UA-Model' => 'model',
			'Sec-CH-UA-Platform' => 'platform',
			'Sec-CH-UA-Platform-Version' => 'platformVersion',
			'Sec-CH-UA-WoW64' => '',
		];
	}

}
