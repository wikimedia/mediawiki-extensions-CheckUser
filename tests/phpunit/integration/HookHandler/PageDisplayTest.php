<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use Skin;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

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
			$this->createMock( TempUserConfig::class ),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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
			$this->createMock( TempUserConfig::class ),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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

	public function testOnBeforePageDisplayWhenTemporaryAccountsNotKnown() {
		$this->disableAutoCreateTempUser();
		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig(),
			$this->createMock( CheckUserPermissionManager::class ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
		);

		$output = RequestContext::getMain()->getOutput();
		$pageDisplayHookHandler->onBeforePageDisplay( $output, $this->createMock( Skin::class ) );

		// Check that the JS code was not added to the output, as it does nothing when temporary accounts are not
		// known on the wiki.
		$this->assertCount( 0, $output->getModules() );
		$this->assertCount( 0, $output->getModuleStyles() );
		$this->assertCount( 0, $output->getJsConfigVars() );
	}

	public function testOnBeforePageDisplayWhenLoadingArticle() {
		$this->enableAutoCreateTempUser();
		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig(),
			$this->createMock( CheckUserPermissionManager::class ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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
		$this->enableAutoCreateTempUser();
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
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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
		$this->enableAutoCreateTempUser();
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
				'CheckUserEnableTempAccountsOnboardingDialog' => true,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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
		$this->enableAutoCreateTempUser();
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
				'CheckUserEnableTempAccountsOnboardingDialog' => true,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
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

	public function testOnBeforePageDisplayForSpecialWatchlistWhenOnboardingDialogDisabled() {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is Special:Watchlist
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Watchlist' ) );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserEnableTempAccountsOnboardingDialog' => false,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createNoOpMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the temporary accounts onboarding dialog modules are not added to the output if
		// the dialog is disabled
		$this->assertNotContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
	}

	public function testOnBeforePageDisplayForHistoryActionWhenUserHasSeenOnboardingDialog() {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is the history page for an article
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( Title::newFromText( 'Test' ) );
		$context->getRequest()->setVal( 'action', 'history' );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		// Mock all users having seen the onboarding dialog, so that the dialog JS module should not be added to
		// the output for anyone.
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[], [ Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => '1' ]
		) );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserEnableTempAccountsOnboardingDialog' => true,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the temporary accounts onboarding dialog modules are not added to the output if
		// dialog has been seen before.
		$this->assertNotContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
	}

	/** @dataProvider provideIPInfoLoadedStates */
	public function testOnBeforePageDisplayForSpecialRecentChangesWhenUserHasNotSeenOnboardingDialog(
		$isIPInfoLoaded
	) {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is Special:RecentChanges
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Recentchanges' ) );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		// Mock all users having not seen the onboarding dialog, so that the module should be added if they
		// have the necessary rights and are on the right page.
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[], [ Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => 0 ]
		) );

		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'IPInfo' )
			->willReturn( $isIPInfoLoaded );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserEnableTempAccountsOnboardingDialog' => true,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$mockExtensionRegistry
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the onboarding dialog has been added to the output if the user has not seen it before,
		// is on one of the defined pages and has the rights to see it.
		$this->assertContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
		$this->assertArrayEquals( [ 'ext.checkUser.styles', 'ext.checkUser.images' ], $output->getModuleStyles() );
		$this->assertArrayEquals(
			[
				'wgCheckUserIPInfoExtensionLoaded' => $isIPInfoLoaded,
				'wgCheckUserTemporaryAccountMaxAge' => 1234,
				'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [],
			],
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public static function provideIPInfoLoadedStates() {
		return [
			'IPInfo is loaded' => [ true ],
			'IPInfo is not loaded' => [ true ],
		];
	}
}
