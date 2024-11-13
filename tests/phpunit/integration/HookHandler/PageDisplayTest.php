<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Skin;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	public function setUp(): void {
		parent::setUp();
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [
				'enwiki' => 'https://en.example.org',
				'metawiki' => 'https://meta.example.org',
			],
			'wgArticlePath' => [
				'enwiki' => '/wiki/$1',
				'metawiki' => '/wiki/$1',
			],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );
	}

	public function testBeforeInitializeHookWithoutConfigSet() {
		$pageDisplayHook = new PageDisplay(
			new HashConfig( [
				'CheckUserGlobalContributionsCentralWikiId' => false,
			] ),
			$this->createMock( CheckUserPermissionManager::class ),
		);
		$output = $this->createMock( OutputPage::class );
		$request = new FauxRequest();
		$pageDisplayHook->onBeforeInitialize(
			SpecialPage::getTitleFor( 'GlobalContributions' ),
			null,
			$output,
			$this->createMock( User::class ),
			$request,
			$this->createMock( ActionEntryPoint::class )
		);
		$output->expects( $this->never() )->method( 'redirect' );
	}

	public function testBeforeInitializeHookWithConfigSet() {
		$pageDisplayHook = new PageDisplay(
			new HashConfig( [
				'CheckUserGlobalContributionsCentralWikiId' => 'metawiki',
			] ),
			$this->createMock( CheckUserPermissionManager::class ),
		);
		$title = SpecialPage::getTitleFor( 'GlobalContributions' );

		$output = $this->createMock( OutputPage::class );
		$output->method( 'getTitle' )->willReturn( $title );
		$request = new FauxRequest( [ 'target' => '127.0.0.1', 'title' => 'Special:GlobalContributions' ] );
		$output->method( 'getRequest' )->willReturn( $request );

		$output->expects( $this->once() )->method( 'redirect' )
			->with( 'https://meta.example.org/wiki/Special:GlobalContributions?target=127.0.0.1' );

		$pageDisplayHook->onBeforeInitialize(
			$title,
			null,
			$output,
			$this->createMock( User::class ),
			$request,
			$this->createMock( ActionEntryPoint::class )
		);
	}

	public function testOnBeforePageDisplayWhenLoadingArticle() {
		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig(),
			$this->createMock( CheckUserPermissionManager::class ),
		);

		// Set up a IContextSource where the title is a mainspace article
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'Test' ) );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was not added to the output
		$this->assertCount( 0, $output->getModules() );
		$this->assertCount( 0, $output->getModuleStyles() );
		$this->assertCount( 0, $output->getJsConfigVars() );
	}

	public function testOnBeforePageDisplayWhenUserMissingPermissions() {
		// Set up a IContextSource where the title is the history page for an article
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'Test' ) );
		$context->getRequest()->setVal( 'action', 'history' );
		$testAuthority = $this->mockRegisteredNullAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig(),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was not added to the output
		$this->assertCount( 0, $output->getModules() );
		$this->assertCount( 0, $output->getModuleStyles() );
		$this->assertCount( 0, $output->getJsConfigVars() );
	}

	public function testOnBeforePageDisplayForInfoAction() {
		// Set up a IContextSource where the title is the info page for an article
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'Test' ) );
		$context->getRequest()->setVal( 'action', 'info' );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was added to the output
		$this->assertArrayEquals( [ 'ext.checkUser' ], $output->getModules() );
		$this->assertArrayEquals( [ 'ext.checkUser.styles' ], $output->getModuleStyles() );
		$this->assertArrayEquals(
			[
				'wgCheckUserTemporaryAccountMaxAge' => 1234,
				'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
			],
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public function testOnBeforePageDisplayForSpecialBlock() {
		// Set up a IContextSource where the title is Special:Block
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Block' ) );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
				'CUDMaxAge' => 12345,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was added to the output, including the extra JS config
		// just for Special:Block.
		$this->assertArrayEquals( [ 'ext.checkUser' ], $output->getModules() );
		$this->assertArrayEquals( [ 'ext.checkUser.styles' ], $output->getModuleStyles() );
		$this->assertArrayEquals(
			[
				'wgCUDMaxAge' => 12345,
				'wgCheckUserTemporaryAccountMaxAge' => 1234,
				'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
			],
			$output->getJsConfigVars(),
			false,
			true
		);
	}

}
