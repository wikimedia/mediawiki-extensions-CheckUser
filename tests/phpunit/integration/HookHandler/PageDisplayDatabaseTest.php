<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\PageDisplay;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\IPInfo\HookHandler\AbstractPreferencesHandler;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWikiIntegrationTestCase;
use Wikimedia\ArrayUtils\ArrayUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\PageDisplay
 * @group Database
 */
class PageDisplayDatabaseTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;
	use TempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	/** @dataProvider provideOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal */
	public function testOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal(
		bool $globalPreferenceValue,
		bool $localPreferenceValue
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->markTestSkippedIfExtensionNotLoaded( 'IPInfo' );

		// Set up pre-requisites for seeing the onboarding dialog
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setTitle( SpecialPage::getTitleFor( 'Watchlist' ) );
		$this->enableAutoCreateTempUser();

		$user = $this->getTestUser()->getUser();
		$authority = $this->mockUserAuthorityWithPermissions(
			$user,
			[ 'checkuser-temporary-account-no-preference', 'ipinfo' ]
		);

		$context->setAuthority( $authority );
		$output = $context->getOutput();
		$output->setContext( $context );

		// Set the global value and local override
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$userOptionsManager->setOption(
			$user,
			AbstractPreferencesHandler::IPINFO_USE_AGREEMENT,
			$globalPreferenceValue,
			UserOptionsManager::GLOBAL_CREATE
		);
		$userOptionsManager->saveOptions( $user );
		$userOptionsManager->setOption(
			$user,
			AbstractPreferencesHandler::IPINFO_USE_AGREEMENT,
			$localPreferenceValue,
			UserOptionsManager::GLOBAL_OVERRIDE
		);
		$userOptionsManager->saveOptions( $user );

		$pageDisplayHookHandler = new PageDisplay(
			new HashConfig( [
				'CheckUserTemporaryAccountMaxAge' => 1234,
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [],
				'CUDMaxAge' => 12345,
				'CheckUserAutoRevealMaximumExpiry' => 1,
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
			$this->getServiceContainer()->get( 'CheckUserLogger' )
		);

		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$expectedConfigVars = [
			'wgCheckUserIPInfoExtensionLoaded' => true,
			'wgCheckUserUserHasIPInfoRight' => true,
			'wgCheckUserIPInfoPreferenceChecked' => $globalPreferenceValue,
		];

		$this->assertContains( 'ext.checkUser.tempAccountOnboarding', $output->getModules() );
		$this->assertArrayContains( [ 'ext.checkUser.images', 'ext.checkUser.styles' ], $output->getModuleStyles() );
		$this->assertArrayContains( $expectedConfigVars, $output->getJsConfigVars() );
	}

	public function testOnBeforePageDisplayForContentUserInfoCardWithRealParserOutput() {
		$this->disableAutoCreateTempUser();

		$title = Title::makeTitle( NS_PROJECT, 'Administrator intervention against vandalism' );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );
		$context->setTitle( $title );
		$output = $context->getOutput();
		$output->setContext( $context );

		$parserOptions = ParserOptions::newFromAnon();
		$output->addParserOutput(
			$this->getServiceContainer()->getParserFactory()->getInstance()->parse(
				'{{#uic:Foo}}',
				$title,
				$parserOptions
			),
			$parserOptions
		);

		$this->setService( 'UserOptionsLookup', new StaticUserOptionsLookup(
			[],
			[ Preferences::ENABLE_USER_INFO_CARD => 1 ]
		) );

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
			$this->getServiceContainer()->get( 'CheckUserLogger' )
		);
		$pageDisplayHookHandler->onBeforePageDisplay(
			$output,
			$this->createMock( Skin::class )
		);

		$bodyClasses = TestingAccessWrapper::newFromObject( $output )->mAdditionalBodyClasses;
		$this->assertContains( 'ext-checkuser-userinfocard-enabled', $bodyClasses );
		$this->assertContains( 'ext.checkUser.userInfoCard', $output->getModules() );
	}

	public static function provideOnBeforePageDisplayForOnboardingWhenIPInfoPreferenceIsGlobal() {
		$testCases = ArrayUtils::cartesianProduct(
			// global preference value
			[ false, true ],
			// local preference value
			[ false, true ]
		);

		foreach ( $testCases as $params ) {
			[ $globalPreferenceValue, $localPreferenceValue ] = $params;

			$description = sprintf(
				'IPInfo use agreement preference %s globally and %s locally',
				$globalPreferenceValue ? 'enabled' : 'disabled',
				$localPreferenceValue ? 'enabled' : 'disabled'
			);

			yield $description => $params;
		}
	}
}
