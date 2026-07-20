<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Block\Block;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\HookHandler\PageDisplay;
use MediaWiki\Extension\CheckUser\HookHandler\ParserFunctionsHandler;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\ISuggestedInvestigationsInstrumentationClient;
use MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\ArrayUtils\ArrayUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\PageDisplay
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserIPRevealManager
 */
class PageDisplayTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	/**
	 * @dataProvider provideOnBeforePageDisplayCases
	 *
	 * @param string|null $specialPageName The name of the special page being viewed,
	 * or `null` if not a special page
	 * @param string|null $actionName The action being performed, or `null` if no action should be set
	 * @param bool $tempAccountsKnown Whether temporary accounts are known
	 * @param bool $hasSeenOnboardingDialog Whether the user has seen the onboarding dialog
	 * @param bool $hasEnabledIpReveal Whether the user has enabled the IP reveal preference
	 * @param bool $hasEnabledIPInfo Whether the user has enabled the IPInfo use agreement
	 * @param bool $hasIpRevealPermission Whether the user has the permission to reveal IPs
	 * @param bool $hasIpInfoPermission Whether the user has the permission to access IP information
	 * @param bool $isBlockedSitewide Whether the user is sitewide blocked
	 * @param bool $isIpInfoAvailable Whether the IPInfo extension is loaded
	 * @param bool $isGlobalPreferencesAvailable Whether the GlobalPreferences extension is loaded
	 */
	public function testOnBeforePageDisplay(
		?string $specialPageName,
		?string $actionName,
		bool $tempAccountsKnown,
		bool $hasSeenOnboardingDialog,
		bool $hasEnabledIpReveal,
		bool $hasEnabledIPInfo,
		bool $hasIpRevealPermission,
		bool $hasIpInfoPermission,
		bool $isBlockedSitewide,
		bool $isIpInfoAvailable,
		bool $isGlobalPreferencesAvailable
	): void {
		if ( $isIpInfoAvailable ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'IPInfo' );
		}
		if ( $isGlobalPreferencesAvailable ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		}

		if ( $tempAccountsKnown ) {
			$this->enableAutoCreateTempUser();
		} else {
			$this->disableAutoCreateTempUser();
		}

		$context = new DerivativeContext( RequestContext::getMain() );

		// Set up either a special page or a main namespace article as the page being viewed.
		if ( $specialPageName !== null ) {
			$context->setTitle( SpecialPage::getTitleFor( $specialPageName ) );
		} else {
			$context->setTitle( Title::makeTitle( NS_MAIN, 'Test' ) );
			$context->getRequest()->setVal( 'action', $actionName );
		}

		$permissions = [];

		if ( $hasIpRevealPermission ) {
			$permissions[] = 'checkuser-temporary-account';
		}

		if ( $hasIpInfoPermission ) {
			$permissions[] = 'ipinfo';
		}

		if ( $isBlockedSitewide ) {
			$block = $this->createMock( Block::class );
			$block->method( 'isSitewide' )
				->willReturn( true );

			$testAuthority = $this->mockUserAuthorityWithBlock(
				new UserIdentityValue( 123, 'Test' ),
				$block,
				$permissions
			);
		} else {
			$testAuthority = $this->mockRegisteredAuthorityWithPermissions( $permissions );
		}

		$options = [
			Preferences::TEMPORARY_ACCOUNTS_ONBOARDING_DIALOG_SEEN => (int)$hasSeenOnboardingDialog,
			Preferences::ENABLE_IP_REVEAL => (int)$hasEnabledIpReveal,
		];

		if ( $isIpInfoAvailable ) {
			$options[AbstractPreferencesHandler::IPINFO_USE_AGREEMENT] = $hasEnabledIPInfo;
		}

		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup( [], $options ) );

		if ( $isGlobalPreferencesAvailable ) {
			// The GlobalPreferencesFactory::getGlobalPreferencesValues method will cause a read from the database,
			// so we mock it to avoid accessing the database and slowing these tests.
			$mockGlobalPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
			$mockGlobalPreferencesFactory->method( 'getGlobalPreferencesValues' )
				->willReturnCallback( function ( $actualUser ) use ( $testAuthority, $options ) {
					$this->assertTrue( $testAuthority->getUser()->equals( $actualUser ) );

					return $options;
				} );

			$this->setService( 'PreferencesFactory', $mockGlobalPreferencesFactory );
		}

		$context->setAuthority( $testAuthority );
		$output = $context->getOutput();
		$output->setContext( $context );

		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturnCallback( static fn ( $name ) => match ( $name ) {
				'IPInfo' => $isIpInfoAvailable,
				'GlobalPreferences' => $isGlobalPreferencesAvailable,
				default => false
			} );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
				'CUDMaxAge' => 12345,
				'CheckUserAutoRevealMaximumExpiry' => 1,
				'CheckUserSuggestedInvestigationsEnabled' => false,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$extensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$this->getBlockStatusCacheMock()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$expectedModules = [];
		$expectedModuleStyles = [];
		$expectedConfigVars = [];

		// Temporary account-related configuration and modules should only be added to the output
		// only on special pages and selected action pages (incl. default), and only if temporary accounts
		// are known on this wiki and the acting user has appropriate permissions.
		// Action 'render' serves here as an example of action where we don't want IP Reveal
		if (
			$tempAccountsKnown &&
			$actionName !== 'render' &&
			$specialPageName !== 'BlockList' &&
			$hasIpRevealPermission
		) {
			if ( $hasEnabledIpReveal ) {
				$expectedModules[] = 'ext.checkUser.tempAccounts';
				$expectedModuleStyles[] = 'ext.checkUser.styles';
				$expectedConfigVars += [
					'wgCheckUserTemporaryAccountMaxAge' => 1234,
					'wgCheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
					'wgCheckUserIsPerformerBlocked' => $isBlockedSitewide,
				];

				if ( $specialPageName === 'Block' ) {
					$expectedConfigVars['wgCUDMaxAge'] = 12345;
				}
			}

			if ( !$hasSeenOnboardingDialog && ( $actionName === 'history' || $specialPageName === 'Watchlist' ) ) {
				$expectedConfigVars += [
					'wgCheckUserIPInfoExtensionLoaded' => $isIpInfoAvailable,
					'wgCheckUserUserHasIPInfoRight' => $isIpInfoAvailable && $hasIpInfoPermission,
					'wgCheckUserIPInfoPreferenceChecked' => $isIpInfoAvailable && $hasEnabledIPInfo,
					'wgCheckUserIPRevealPreferenceGloballyChecked' => $hasEnabledIpReveal,
					'wgCheckUserIPRevealPreferenceLocallyChecked' => $hasEnabledIpReveal,
					'wgCheckUserGlobalPreferencesExtensionLoaded' => $isGlobalPreferencesAvailable,
				];
				$expectedModules[] = 'ext.checkUser.tempAccountOnboarding';
				$expectedModuleStyles[] = 'ext.checkUser.images';
				$expectedModuleStyles[] = 'ext.checkUser.styles';
			}
		}

		$this->assertArrayEquals( $expectedModules, $output->getModules() );
		$this->assertArrayEquals(
			array_unique( $expectedModuleStyles ),
			$output->getModuleStyles()
		);
		$this->assertArrayContains(
			$expectedConfigVars,
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public static function provideOnBeforePageDisplayCases(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// special pages
			[ 'Watchlist', 'Block', 'BlockList', null ],
			// actions
			[ 'info', 'history', 'render', null ],
			// whether temporary accounts are known
			[ true, false ],
			// whether the user has seen the onboarding dialog
			[ true, false ],
			// whether the user has enabled the IP reveal preference
			[ true, false ],
			// whether the user has enabled the IPInfo use agreement preference
			[ true, false ],
			// whether the user has the permission to reveal IPs
			[ true, false ],
			// whether the user has the permission to access IP information
			[ true, false ],
			// whether the user is sitewide blocked
			[ true, false ],
			// whether the IPInfo extension is loaded
			[ true, false ],
			// whether the GlobalPreferences extension is loaded
			[ true, false ]
		);

		foreach ( $testCases as $params ) {
			[
				$specialPageName,
				$actionName,
				$tempAccountsKnown,
				$hasSeenOnboardingDialog,
				$hasEnabledIpReveal,
				$hasEnabledIPInfo,
				$hasIpRevealPermission,
				$hasIpInfoPermission,
				$isBlockedSitewide,
				$isIpInfoAvailable,
				$isGlobalPreferencesAvailable,
			] = $params;

			// Special pages can't have actions.
			if ( $specialPageName !== null && $actionName !== null ) {
				continue;
			}

			// The presence of IPInfo and related permissions, and GlobalPreferences only influences config variables
			// related to the onboarding dialog. So don't generate permutations involving these
			// if we do not expect to show the dialog.
			if ( $hasSeenOnboardingDialog ) {
				if ( $isIpInfoAvailable || $hasIpInfoPermission || $isGlobalPreferencesAvailable ) {
					continue;
				}
			}

			if ( $isBlockedSitewide && !$hasIpRevealPermission ) {
				continue;
			}

			if (
				( !$tempAccountsKnown || ( $actionName === null && $specialPageName === null ) ) &&
				(
					$isBlockedSitewide ||
					!$hasIpRevealPermission ||
					$hasSeenOnboardingDialog
				)
			) {
				continue;
			}

			$description = sprintf(
				'%s%s temporary accounts %s, onboarding dialog %s, IP reveal %s, IPInfo %s, ' .
				'%s IP reveal permission, %s IP info permission, %s, IPInfo extension %s, ' .
				'GlobalPreferences extension %s',
				$specialPageName ? "Special:$specialPageName, " : '',
				$actionName ? "action=$actionName," : '',
				$tempAccountsKnown ? 'known' : 'not known',
				$hasSeenOnboardingDialog ? 'seen' : 'not seen',
				$hasEnabledIpReveal ? 'enabled' : 'disabled',
				$hasEnabledIPInfo ? 'enabled' : 'disabled',
				$hasIpRevealPermission ? 'with' : 'no',
				$hasIpInfoPermission ? 'with' : 'no',
				$isBlockedSitewide ? 'blocked sitewide' : 'not blocked',
				$isIpInfoAvailable ? 'loaded' : 'not loaded',
				$isGlobalPreferencesAvailable ? 'loaded' : 'not loaded'
			);

			yield $description => $params;
		}
	}

	public static function provideTestOnBeforePageDisplayLoadSpecialContributionsStyles() {
		return [
			'canAccessTemporaryAccountIPAddresses true, valid page' => [
				'canAccessTemporaryAccountIPAddresses' => true,
				'pageTitle' => 'Contributions',
				'targetUser' => '~2026-1',
				'isLoaded' => true,
			],
			'invalid page' => [
				'canAccessTemporaryAccountIPAddresses' => true,
				'pageTitle' => 'Recentchanges',
				'targetUser' => '~2026-1',
				'isLoaded' => false,
			],
			'invalid user' => [
				'canAccessTemporaryAccountIPAddresses' => true,
				'pageTitle' => 'Contributions',
				'targetUser' => 'Foo',
				'isLoaded' => false,
			],
			'canAccessTemporaryAccountIPAddresses false' => [
				'canAccessTemporaryAccountIPAddresses' => false,
				'pageTitle' => 'Contributions',
				'targetUser' => '~2026-1',
				'isLoaded' => false,
			],
		];
	}

	/** @dataProvider provideOnBeforePageDisplayForUserInfoCard */
	public function testOnBeforePageDisplayForUserInfoCard(
		bool $isEnabled,
		bool $performerIsNamed,
		array $expected
	) {
		$this->disableAutoCreateTempUser();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Contributions' ) );
		$performer = $performerIsNamed ?
			$this->mockRegisteredUltimateAuthority() :
			$this->mockAnonUltimateAuthority();
		$context->setAuthority( $performer );
		$output = $context->getOutput();
		$output->setContext( $context );

		$options = [ Preferences::ENABLE_USER_INFO_CARD => (int)$isEnabled ];
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup( [], $options ) );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserSuggestedInvestigationsEnabled' => false,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$this->getBlockStatusCacheMock()
		);
		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$this->assertArrayEquals(
			$expected,
			$output->getJsConfigVars(),
			false,
			true
		);
	}

	public static function provideOnBeforePageDisplayForUserInfoCard() {
		return [
			'UserInfoCard is enabled, performer is a non-temp user' => [
				'isEnabled' => true,
				'performerIsNamed' => true,
				'expected' => [
					'wgCheckUserCanAccessTemporaryAccountLog' => true,
					'wgCheckUserCanBlock' => true,
					'wgCheckUserCanPerformCheckUser' => true,
					'wgCheckUserCanViewCheckUserLog' => true,
					'wgCheckUserCanViewSuggestedInvestigations' => false,
				],
			],
			'UserInfoCard is enabled, performer is a temp user' => [
				'isEnabled' => true,
				'performerIsNamed' => false,
				'expected' => [],
			],
			'UserInfoCard is disabled' => [
				'isEnabled' => false,
				'performerIsNamed' => false,
				'expected' => [],
			],
		];
	}

	/** @dataProvider provideOnOutputPageParserOutputForContentUserInfoCard */
	public function testOnOutputPageParserOutputForContentUserInfoCard(
		bool $isEnabled,
		bool $hasContentButtons,
		bool $performerIsNamed,
		bool $expectRevealed
	) {
		$this->disableAutoCreateTempUser();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority(
			$performerIsNamed ?
			$this->mockRegisteredUltimateAuthority() :
			$this->mockAnonUltimateAuthority()
		);
		$output = $context->getOutput();
		$output->setContext( $context );

		$options = [ Preferences::ENABLE_USER_INFO_CARD => (int)$isEnabled ];
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup( [], $options ) );

		$this->getPageDisplayHookHandlerForUserInfoCard()->onOutputPageParserOutput(
			$output,
			$this->makeParserOutputWithTargets( $hasContentButtons ? [ 'Some user' ] : [] )
		);

		// The two go together: revealing the buttons without loading the card would leave a
		// visible trigger that does nothing.
		$bodyClasses = TestingAccessWrapper::newFromObject( $output )->mAdditionalBodyClasses;
		$this->assertSame(
			$expectRevealed,
			in_array( 'ext-checkuser-userinfocard-enabled', $bodyClasses, true ),
			'body class'
		);
		$this->assertSame(
			$expectRevealed,
			in_array( 'ext.checkUser.userInfoCard', $output->getModules(), true ),
			'card module'
		);
	}

	public static function provideOnOutputPageParserOutputForContentUserInfoCard() {
		return [
			'enabled, page has content buttons' => [
				'isEnabled' => true,
				'hasContentButtons' => true,
				'performerIsNamed' => true,
				'expectRevealed' => true,
			],
			// Nothing to reveal, and the card would be dead weight: it depends on Vue and d3.
			'enabled, page has no content buttons' => [
				'isEnabled' => true,
				'hasContentButtons' => false,
				'performerIsNamed' => true,
				'expectRevealed' => false,
			],
			'disabled, page has content buttons' => [
				'isEnabled' => false,
				'hasContentButtons' => true,
				'performerIsNamed' => true,
				'expectRevealed' => false,
			],
			// The highest-volume audience, and the one that must never see the buttons.
			'anonymous viewer, page has content buttons' => [
				'isEnabled' => true,
				'hasContentButtons' => true,
				'performerIsNamed' => false,
				'expectRevealed' => false,
			],
		];
	}

	/**
	 * @param string[] $blockedTargets Targets the lookup should report as blocked
	 */
	private function getBlockStatusCacheMock( array $blockedTargets = [] ): UserInfoCardBlockStatusCache {
		$blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
		$blockStatusCache->method( 'getIndefinitelyBlockedOrLockedUsers' )
			->willReturnCallback(
				static fn ( array $usernames ) => array_values(
					array_intersect( $usernames, $blockedTargets )
				)
			);

		return $blockStatusCache;
	}

	private function getPageDisplayHookHandlerForUserInfoCard(
		?UserInfoCardBlockStatusCache $blockStatusCache = null
	): PageDisplay {
		return new PageDisplay(
			new HashConfig( [
				'CheckUserSuggestedInvestigationsEnabled' => false,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$blockStatusCache ?? $this->getBlockStatusCacheMock()
		);
	}

	/**
	 * Build the parser output that {{#uic:}} would have produced for the given targets, which is
	 * the only channel the handler reads them from.
	 *
	 * @param list<string> $targets
	 */
	private function makeParserOutputWithTargets( array $targets ): ParserOutput {
		$parserOutput = new ParserOutput();
		foreach ( $targets as $target ) {
			$parserOutput->appendExtensionData(
				ParserFunctionsHandler::TARGETS_EXTENSION_DATA_KEY,
				(string)$target
			);
		}

		return $parserOutput;
	}

	private function getOutputWithCardEnabled(): OutputPage {
		$this->disableAutoCreateTempUser();

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$output = $context->getOutput();
		$output->setContext( $context );

		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[],
			[ Preferences::ENABLE_USER_INFO_CARD => 1 ]
		) );

		return $output;
	}

	public function testExportsBlockedContentTargets(): void {
		$output = $this->getOutputWithCardEnabled();

		$this->getPageDisplayHookHandlerForUserInfoCard(
			$this->getBlockStatusCacheMock( [ 'Blocked user' ] )
		)->onOutputPageParserOutput(
			$output,
			$this->makeParserOutputWithTargets( [ 'Blocked user', 'Other user' ] )
		);

		// Only the blocked targets are shipped; the client leaves every other icon alone.
		$this->assertSame(
			[ 'Blocked user' => 'userBlocked' ],
			$output->getJsConfigVars()['wgCheckUserUserInfoCardCustomIcons'] ?? null
		);
	}

	public function testDoesNotExportBlockedTargetsWhenNoneAreBlocked(): void {
		$output = $this->getOutputWithCardEnabled();

		$this->getPageDisplayHookHandlerForUserInfoCard()->onOutputPageParserOutput(
			$output,
			$this->makeParserOutputWithTargets( [ 'Some user' ] )
		);

		$this->assertArrayNotHasKey(
			'wgCheckUserUserInfoCardCustomIcons',
			$output->getJsConfigVars(),
			'The variable should be omitted entirely rather than shipped empty'
		);
	}

	public function testMergesBlockedTargetsAcrossParserOutputs(): void {
		// A page can be built from more than one parser output, and each one is reported on its
		// own, so a later one must not drop what an earlier one already found.
		$output = $this->getOutputWithCardEnabled();
		$handler = $this->getPageDisplayHookHandlerForUserInfoCard(
			$this->getBlockStatusCacheMock( [ 'First blocked', 'Second blocked' ] )
		);

		$handler->onOutputPageParserOutput(
			$output,
			$this->makeParserOutputWithTargets( [ 'First blocked' ] )
		);
		$handler->onOutputPageParserOutput(
			$output,
			$this->makeParserOutputWithTargets( [ 'Second blocked', 'First blocked' ] )
		);

		$blockedTargets = $output->getJsConfigVars()['wgCheckUserUserInfoCardCustomIcons'];
		$this->assertArrayEquals(
			[ 'First blocked' => 'userBlocked', 'Second blocked' => 'userBlocked' ],
			$blockedTargets
		);
	}

	public function testOnBeforePageDisplayForUserInfoCardWithSuggestedInvestigationsEnabled() {
		$context = RequestContext::getMain();
		$context->setTitle( $this->createMock( Title::class ) );
		$performer = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $performer );
		$output = $context->getOutput();
		$output->setContext( $context );

		$options = [ Preferences::ENABLE_USER_INFO_CARD => 1 ];
		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup( [], $options ) );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserSuggestedInvestigationsEnabled' => true,
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserAutoRevealMaximumExpiry' => 1,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$this->getBlockStatusCacheMock()
		);
		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$configVars = $output->getJsConfigVars();
		$this->assertArrayHasKey( 'wgCheckUserCanViewSuggestedInvestigations', $configVars );
		$this->assertTrue(
			$configVars['wgCheckUserCanViewSuggestedInvestigations'],
			'wgCheckUserCanViewSuggestedInvestigations is true when feature is enabled and performer has permission'
		);
	}

	/** @dataProvider provideOnBeforePageDisplayForSuggestedInvestigations */
	public function testOnBeforePageDisplayForSuggestedInvestigations(
		string $specialPageName,
		bool $isFeatureEnabled,
		bool $canViewFeature,
		bool $shouldLoadModule,
		array $expectedJsConfigVars,
	): void {
		$this->disableAutoCreateTempUser();
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( $specialPageName ) );
		$performer = $canViewFeature ?
			$this->mockRegisteredUltimateAuthority() :
			$this->mockRegisteredNullAuthority();
		$context->setAuthority( $performer );
		$output = $context->getOutput();
		$output->setContext( $context );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CUDMaxAge' => 12345,
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserSuggestedInvestigationsEnabled' => $isFeatureEnabled,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$this->getBlockStatusCacheMock()
		);
		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$this->assertArrayEquals(
			$expectedJsConfigVars,
			$output->getJsConfigVars(),
		);
		if ( $shouldLoadModule ) {
			$this->assertContains( 'ext.checkUser.suggestedInvestigations', $output->getModules() );
		} else {
			$this->assertNotContains( 'ext.checkUser.suggestedInvestigations', $output->getModules() );
		}
	}

	public static function provideOnBeforePageDisplayForSuggestedInvestigations(): array {
		return [
			'Should show - all checks pass' => [
				'specialPageName' => 'Block',
				'isFeatureEnabled' => true,
				'canViewFeature' => true,
				'shouldLoadModule' => true,
				'expectedJsConfigVars' => [
					'wgCheckUserSuggestedInvestigationsEnabled' => true,
					'wgCheckUserCanViewSuggestedInvestigations' => true,
				],
			],
			'Shouldn\'t load on pages other than Special:Block' => [
				'specialPageName' => 'Version',
				'isFeatureEnabled' => true,
				'canViewFeature' => true,
				'shouldLoadModule' => false,
				'expectedJsConfigVars' => [],
			],
			'Shouldn\'t load if suggested investigations is disabled' => [
				'specialPageName' => 'Block',
				'isFeatureEnabled' => false,
				'canViewFeature' => true,
				'shouldLoadModule' => false,
				'expectedJsConfigVars' => [],
			],
			'Shouldn\'t load if user cannot view suggested investigations' => [
				'specialPageName' => 'Block',
				'isFeatureEnabled' => true,
				'canViewFeature' => false,
				'shouldLoadModule' => false,
				'expectedJsConfigVars' => [],
			],
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
		if ( $ipInfoLoaded ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'IPInfo' );
		}

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
			->willReturnCallback( static function ( $name ) use ( $ipInfoLoaded ) {
				if ( $name === 'IPInfo' ) {
					return $ipInfoLoaded;
				}
				return false;
			} );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserAutoRevealMaximumExpiry' => 1,
				'CheckUserSuggestedInvestigationsEnabled' => false,
			] ),
			$cuPermissionManager,
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$mockExtensionRegistry,
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsInstrumentationClient' ),
			$this->getServiceContainer()->get( 'CheckUserLogger' ),
			$this->getBlockStatusCacheMock()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$skin
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

	public static function provideOnBeforePageDisplayHook_instrumentSuggestedInvestigations(): array {
		return [
			'Do nothing if feature is disabled' => [
				false,
				[],
				false,
				false,
				null,
			],
			'Do nothing if subtype is missing despite other relevant params' => [
				true,
				[
					'si_actionsource' => 'foo',
					'si_targetuser' => 'bar',
					'si_caseid' => 1,
				],
				false,
				false,
				null,
			],
			'Warn if the case id cannot be found' => [
				true,
				[
					'si_subtype' => 'baz',
					'si_actionsource' => 'foo',
					'si_targetuser' => 'bar',
				],
				true,
				false,
				'/wiki/Special:Contributions',
			],
			'Preserve all other query parameters' => [
				true,
				[
					'si_subtype' => 'baz',
					'si_actionsource' => 'foo',
					'si_targetuser' => 'bar',
					'si_caseid' => 1,
					'foo' => 'bar',
				],
				false,
				true,
				'/index.php?title=Special:Contributions&foo=bar',
			],
		];
	}

	/** @dataProvider provideOnBeforePageDisplayHook_instrumentSuggestedInvestigations */
	public function testOnBeforePageDisplayHook_instrumentSuggestedInvestigations(
		bool $isFeatureEnabled,
		array $queryParams,
		bool $shouldWarn,
		bool $shouldInstrument,
		?string $expectRedirect
	): void {
		$request = new FauxRequest( $queryParams );
		$request->setRequestURL( SpecialPage::getTitleFor( 'Contributions' )->getLocalURL( $queryParams ) );
		RequestContext::getMain()->setRequest( $request );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Contributions' ) );
		$context->setRequest( $request );
		foreach ( $queryParams as $queryParam => $value ) {
			$context->getRequest()->setVal( $queryParam, $value );
		}

		$testAuthority = $this->mockRegisteredUltimateAuthority();
		$context->setAuthority( $testAuthority );

		$output = $context->getOutput();
		$output->setContext( $context );

		$skin = $this->createMock( Skin::class );

		$mockCheckUserLogger = $this->createMock( LoggerInterface::class );
		$mockCheckUserSuggestedInvestigationsInstrumentationClient = $this
			->createMock( ISuggestedInvestigationsInstrumentationClient::class );

		if ( $shouldWarn ) {
			$mockCheckUserLogger
				->expects( $this->once() )
				->method( 'warning' );
		} else {
			$mockCheckUserLogger
				->expects( $this->never() )
				->method( 'warning' );
		}

		if ( $shouldInstrument ) {
			$mockCheckUserSuggestedInvestigationsInstrumentationClient
				->expects( $this->once() )
				->method( 'submitInteraction' )
				->willReturnCallback( function ( $context, $action, $interactionData ) use ( $output, $queryParams ) {
					$this->assertSame( 'link_click', $action );
					$this->assertSame( $queryParams[ 'si_subtype' ], $interactionData[ 'action_subtype' ] );
					$this->assertSame( $queryParams[ 'si_actionsource' ], $interactionData[ 'action_source' ] );
					$this->assertSame( $queryParams[ 'si_targetuser' ], $interactionData[ 'action_context' ] );
					$this->assertSame( $queryParams[ 'si_caseid' ], $interactionData[ 'case_id' ] );
					$this->assertSame( $output->getUser()->getId(), $interactionData[ 'performer' ][ 'id' ] );
				} );
		} else {
			$mockCheckUserSuggestedInvestigationsInstrumentationClient
				->expects( $this->never() )
				->method( 'submitInteraction' );
		}

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CheckUserAutoRevealMaximumExpiry' => 1,
				'CheckUserSuggestedInvestigationsEnabled' => $isFeatureEnabled,
			] ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->get( 'CheckUserIPRevealManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getUserIdentityUtils(),
			$this->getServiceContainer()->getPreferencesFactory(),
			$mockCheckUserSuggestedInvestigationsInstrumentationClient,
			$mockCheckUserLogger,
			$this->getBlockStatusCacheMock()
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$skin
		);

		$expectedResponse = $output->getRequest()->response();
		if ( $expectRedirect ) {
			$this->assertSame( $expectRedirect, $expectedResponse->getHeader( 'Location' ) );
			$this->assertSame( 302, $expectedResponse->getStatusCode() );
		} else {
			$this->assertNull( $expectedResponse->getHeader( 'Location' ) );
			$this->assertNull( $expectedResponse->getStatusCode() );
		}
	}
}
