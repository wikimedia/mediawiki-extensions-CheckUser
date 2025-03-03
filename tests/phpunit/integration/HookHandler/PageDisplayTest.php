<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\PageDisplay;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiIntegrationTestCase;
use Skin;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PageDisplay
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	public function testOnBeforePageDisplayWhenTemporaryAccountsNotKnown() {
		$this->disableAutoCreateTempUser();
		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig(),
			$this->createMock( CheckUserPermissionManager::class ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
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
			new HashConfig( [
				'CheckUserEnableTempAccountsOnboardingDialog' => true,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->createMock( UserOptionsLookup::class ),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was added to the output
		$this->assertArrayEquals( [ 'ext.checkUser.tempAccounts' ], $output->getModules() );
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the JS code was added to the output, including the extra JS config
		// just for Special:Block.
		$this->assertArrayEquals( [ 'ext.checkUser.tempAccounts' ], $output->getModules() );
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the temporary accounts onboarding dialog modules are not added to the output if
		// dialog has been seen before.
		$this->assertNotContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
	}

	public function testOnBeforePageDisplayForSpecialWatchlistWhenUserIsSitewideBlocked() {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is Special:Watchlist and the authority is blocked.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Watchlist' ) );
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( true );
		$testAuthority = $this->mockUserAuthorityWithBlock(
			new UserIdentityValue( 123, 'Test' ),
			$block,
			[ 'checkuser-temporary-account' ]
		);
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[], [ Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => 0 ]
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Check that the temporary accounts onboarding dialog modules are not added to the output if
		// the authority is blocked.
		$this->assertNotContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
	}

	public function testOnBeforePageDisplayForSpecialWatchlistWhenUserHasNotEnabledPreference() {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is Special:Watchlist
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Watchlist' ) );
		$testAuthority = $this->mockRegisteredAuthorityWithPermissions( [ 'checkuser-temporary-account' ] );
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		// Mock all users have not seen the dialog and not enabled the IP reveal preference
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[],
			[
				Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => 0,
				Preferences::ENABLE_IP_REVEAL => 0,
			]
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
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $this->createMock( Skin::class )
		);

		// Users who cannot see IP reveal only because they have not enabled the preference should be shown
		// the onboarding dialog but not have IP reveal tools loaded.
		$this->assertContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
		$this->assertNotContains( 'ext.checkUser.tempAccounts', $output->getModules() );
	}

	/** @dataProvider provideOnBeforePageDisplayForSpecialRecentChangesWhenUserHasNotSeenOnboardingDialog */
	public function testOnBeforePageDisplayForSpecialRecentChangesWhenUserHasNotSeenOnboardingDialog(
		$isIPInfoLoaded, $userHasIPInfoRight
	) {
		$this->enableAutoCreateTempUser();

		// Set up a IContextSource where the title is Special:RecentChanges, and the Authority has all rights
		// except IPInfo when $userHasIPInfoRight is false.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Recentchanges' ) );
		$testAuthority = $this->mockRegisteredAuthority( static function ( $permission ) use ( $userHasIPInfoRight ) {
			return $userHasIPInfoRight || $permission !== 'ipinfo';
		} );
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
			$mockExtensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils()
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
				'wgCheckUserUserHasIPInfoRight' => $isIPInfoLoaded && $userHasIPInfoRight,
				'wgCheckUserTemporaryAccountMaxAge' => 1234,
				'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [],
			],
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public static function provideOnBeforePageDisplayForSpecialRecentChangesWhenUserHasNotSeenOnboardingDialog() {
		return [
			'IPInfo is loaded, user has ipinfo right' => [ true, true ],
			'IPInfo is loaded, user is missing ipinfo right' => [ true, false ],
			'IPInfo is not loaded' => [ false, true ],
		];
	}

	/** @dataProvider provideOnBeforePageDisplayForIPInfoHookCases */
	public function testOnBeforePageDisplayForIPInfoHook(
		string $pageTitle,
		UserIdentityValue $target,
		bool $canViewSpecialGC,
		bool $ipInfoLoaded,
		bool $shouldLoadModule
	) {
		// Set up a IContextSource where the title is $pageTitle
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( $pageTitle ) );
		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getRelevantUser' )
			->willReturn( $target );

		$cuPermissionManagerGCAccessCheck = $this->createMock( CheckUserPermissionStatus::class );
		$cuPermissionManagerGCAccessCheck->method( 'isGood' )
			->willReturn( $canViewSpecialGC );
		$cuPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$cuPermissionManager->method( 'canAccessUserGlobalContributions' )
			->willReturn( $cuPermissionManagerGCAccessCheck );

		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'IPInfo' )
			->willReturn( $ipInfoLoaded );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserEnableTempAccountsOnboardingDialog' => false,
			] ),
			$cuPermissionManager,
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$mockExtensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output, $skin
		);

		// Assert that the module is loaded as necessary
		if ( $shouldLoadModule ) {
			$this->assertContains( 'ext.checkUser.ipInfo.hooks', $output->getModules() );
		} else {
			$this->assertNotContains( 'ext.checkUser.ipInfo.hooks', $output->getModules() );
		}
	}

	/**
	 * Parameters:
	 * - Name of special page (string)
	 * - Relevant user (UserIdentityValue)
	 * - Whether the accessor can view Special:GC (bool)
	 * - Whether the Special:GC link module is loaded or not (bool)
	 */
	public static function provideOnBeforePageDisplayForIPInfoHookCases() {
		return [
			'module should load on Special:Contributions with user' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => true,
			],
			'module shouldn\'t load on Special:Contributions with user' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newRegistered( 1, 'Registered User' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load on Special:RecentChanges' => [
				'pageTitle' => 'Recentchanges',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load if user has no view permissions for Special:GlobalContributions' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => false,
				'ipInfoLoaded' => true,
				'shouldLoadModule' => false,
			],
			'module shouldn\'t load if IPInfo isn\t loaded' => [
				'pageTitle' => 'Contributions',
				'target' => UserIdentityValue::newAnonymous( '1.2.3.4' ),
				'canViewSpecialGC' => true,
				'ipInfoLoaded' => false,
				'shouldLoadModule' => false,
			],
		];
	}
}
