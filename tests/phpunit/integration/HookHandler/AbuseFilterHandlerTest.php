<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\Block;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\CheckUser\HookHandler\AbuseFilterHandler;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\AbuseFilterHandler
 * @group CheckUser
 * @group Database
 */
class AbuseFilterHandlerTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;
	use MockAuthorityTrait;

	private const array CHECKUSER_VARIABLES = [
		'checkuser_user_ip',
		'checkuser_user_agent',
	];

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		$this->overrideConfigValue( 'CheckUserAbuseFilterVariablesEnabled', true );
	}

	private function getHookHandler(): AbuseFilterHandler {
		return new AbuseFilterHandler(
			$this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->getMainConfig()
		);
	}

	public function testMakesDefinedVariablesAlwaysProtected(): void {
		// Tests that the hook is called by AbuseFilter and adds the variables to the return
		// value of the protected variables service.
		$this->overrideConfigValue( 'AbuseFilterProtectedVariables', [] );

		$actualProtectedVariables = AbuseFilterServices::getProtectedVariablesLookup()->getAllProtectedVariables();
		$this->assertArrayContains(
			array_merge( [ 'user_unnamed_ip' ], self::CHECKUSER_VARIABLES ),
			$actualProtectedVariables
		);
	}

	public function testOnAbuseFilterCustomProtectedVariables(): void {
		// Tests the hook handler works without testing that the AbuseFilter part works, to make
		// it easier to diagnose issues if ::testMakesDefinedVariablesAlwaysProtected fails.
		$variables = [];
		$this->getHookHandler()->onAbuseFilterCustomProtectedVariables( $variables );
		$this->assertArrayEquals(
			array_merge( [ 'user_unnamed_ip' ], self::CHECKUSER_VARIABLES ),
			$variables
		);
	}

	public function testProtectedVarsLogGoesToAbuseFilterLogIfVariablesMissingUserUnnamedIp() {
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(),
			'~2024-01',
			[ 'other_protected_var' ],
			(int)wfTimestamp()
		);
		DeferredUpdates::doUpdates();

		// Assert that the log was created by AbuseFilter and did not go to CheckUser, as CheckUser is only interested
		// in logs where the user viewed user_unnamed_ip.
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'af-view-protected-var-value',
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );
	}

	public function testProtectedVarsLogSplitIfVariablesContainUserUnnamedIp() {
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(),
			'~2024-01',
			[ 'user_unnamed_ip', 'other_protected_var' ],
			(int)wfTimestamp()
		);
		DeferredUpdates::doUpdates();

		// Assert that the log was created by AbuseFilter and did not go in full to CheckUser, as CheckUser
		// is only interested in user_unnamed_ip variable.
		$cuLogs = DatabaseLogEntry::newSelectQueryBuilder( $this->getDb() )
			->where( [
				'log_action' => 'af-view-protected-var-value',
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->fetchResultSet();
		$this->assertSame( 1, $cuLogs->numRows() );
		$cuLogEntry = DatabaseLogEntry::newFromRow( $cuLogs->current() );
		$this->assertSame(
			[ 'user_unnamed_ip' ],
			$cuLogEntry->getParameters()['variables']
		);

		$afLogs = DatabaseLogEntry::newSelectQueryBuilder( $this->getDb() )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->fetchResultSet();
		$this->assertSame( 1, $afLogs->numRows() );
		$afLogEntry = DatabaseLogEntry::newFromRow( $afLogs->current() );
		$this->assertSame(
			[ 'other_protected_var' ],
			$afLogEntry->getParameters()['variables']
		);
	}

	public function testProtectedVarsAccessDebouncedLogging() {
		// Run the same action twice
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(),
			'~2024-01',
			[ 'user_unnamed_ip' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(),
			'~2024-01',
			[ 'user_unnamed_ip' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that the action only inserted once into CheckUsers' temp account logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'af-view-protected-var-value',
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );

		// and also that it wasn't inserted into abusefilter's protected vars logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );
	}

	private function mockAllUsersHaveAcceptedIPRevealPreference(): void {
		$this->setService(
			'UserOptionsLookup',
			new StaticUserOptionsLookup( [], [ 'checkuser-temporary-account-enable' => 1 ] )
		);
	}

	public function testViewingUserUnnamedIPWhenUserLacksIPRevealRight(): void {
		$this->enableAutoCreateTempUser();
		$this->mockAllUsersHaveAcceptedIPRevealPreference();
		// Get a mock test Authority that has the rights and preferences needed to see protected variables,
		// but not the rights to see user_unnamed_ip.
		$authority = $this->mockRegisteredAuthorityWithoutPermissions(
			[ 'checkuser-temporary-account', 'checkuser-temporary-account-no-preference' ]
		);

		// Check that AbuseFilterPermissionManager::canViewProtectedVariables returns a fatal status with
		// the checkuser-temporary-account permission specified.
		$actualStatus = AbuseFilterServices::getPermissionManager()
			->canViewProtectedVariables( $authority, [ 'user_unnamed_ip' ] );
		$this->assertStatusNotGood( $actualStatus );
		$this->assertSame( 'checkuser-temporary-account', $actualStatus->getPermission() );
	}

	/** @dataProvider provideCanViewCheckUserProtectedVariables */
	public function testCanViewCheckUserProtectedVariables(
		array $rights,
		array $variables,
		?string $expectedMissingPermission
	): void {
		$this->enableAutoCreateTempUser();
		$this->mockAllUsersHaveAcceptedIPRevealPreference();

		$actualStatus = AbuseFilterServices::getPermissionManager()->canViewProtectedVariables(
			$this->mockRegisteredAuthorityWithPermissions( $rights ),
			$variables
		);

		if ( $expectedMissingPermission === null ) {
			$this->assertStatusGood( $actualStatus );
		} else {
			$this->assertStatusNotGood( $actualStatus );
			$this->assertSame(
				$expectedMissingPermission,
				$actualStatus->getPermission(),
				'Missing permission in the returned status is not as expected'
			);
		}
	}

	public static function provideCanViewCheckUserProtectedVariables(): array {
		return [
			'Uses CheckUser protected variable, user lacks checkuser right' => [
				'rights' => [ 'abusefilter-access-protected-vars' ],
				'variables' => [ 'checkuser_user_ip' ],
				'expectedMissingPermission' => 'checkuser',
			],
			'Uses CheckUser protected variable, user has checkuser right' => [
				'rights' => [ 'abusefilter-access-protected-vars', 'checkuser' ],
				'variables' => [ 'checkuser_user_ip' ],
				'expectedMissingPermission' => null,
			],
			'Uses user_unnamed_ip, has IP reveal right' => [
				'rights' => [ 'abusefilter-access-protected-vars', 'checkuser-temporary-account' ],
				'variables' => [ 'user_unnamed_ip' ],
				'expectedMissingPermission' => null,
			],
			'Uses user_unnamed_ip, lacks IP reveal right' => [
				'rights' => [ 'abusefilter-access-protected-vars' ],
				'variables' => [ 'user_unnamed_ip' ],
				'expectedMissingPermission' => 'checkuser-temporary-account',
			],
			'Uses user_unnamed_ip and checkuser_user_agent when user has neither needed rights' => [
				'rights' => [ 'abusefilter-access-protected-vars' ],
				'variables' => [ 'user_unnamed_ip', 'checkuser_user_agent' ],
				'expectedMissingPermission' => 'checkuser',
			],
			'Uses user_unnamed_ip and checkuser_user_ip when user has checkuser but lacks IP reveal right' => [
				'rights' => [ 'abusefilter-access-protected-vars', 'checkuser' ],
				'variables' => [ 'user_unnamed_ip', 'checkuser_user_ip' ],
				'expectedMissingPermission' => 'checkuser-temporary-account',
			],
		];
	}

	/** @dataProvider provideWhenAdditionalRestrictionsNotApplied */
	public function testCanViewProtectedVarsWhenNotApplyingAdditionalRestrictions(
		bool $temporaryAccountsKnown,
		array $variables
	): void {
		$this->disableAutoCreateTempUser( [ 'known' => $temporaryAccountsKnown ] );
		$this->mockAllUsersHaveAcceptedIPRevealPreference();

		$this->setService(
			'CheckUserPermissionManager',
			$this->createNoOpMock( CheckUserPermissionManager::class )
		);
		$canViewProtectedVariablesStatus = AbuseFilterServices::getPermissionManager()
			->canViewProtectedVariables( $this->mockRegisteredUltimateAuthority(), $variables );
		$this->assertStatusGood( $canViewProtectedVariablesStatus );
	}

	public static function provideWhenAdditionalRestrictionsNotApplied(): array {
		return [
			'Variables include user_unnamed_ip, but temporary accounts are not known' => [
				'temporaryAccountsKnown' => false,
				'variables' => [ 'user_unnamed_ip' ],
			],
			'Protected filter does not include any variable protected by CheckUser' => [
				'temporaryAccountsKnown' => true,
				'variables' => [ 'some_other_variable' ],
			],
		];
	}

	/** @dataProvider provideVariablesThatDenyAccessToSitewideBlockedUsers */
	public function testCanViewProtectedVariablesWhenUserSitewideBlocked( array $variables ): void {
		$this->enableAutoCreateTempUser();

		// Get an Authority which has all needed permissions to access IP reveal but is sitewide blocked.
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( true );
		$testAuthority = $this->mockUserAuthorityWithBlock(
			$this->mockRegisteredUltimateAuthority()->getUser(),
			$block,
			[ 'checkuser-temporary-account-no-preference', 'checkuser' ]
		);

		$actualStatus = AbuseFilterPermissionStatus::newGood();
		$this->getHookHandler()->onAbuseFilterCanViewProtectedVariables( $testAuthority, $variables, $actualStatus );

		$this->assertStatusNotGood( $actualStatus );
		$this->assertSame( $block, $actualStatus->getBlock() );
	}

	public static function provideVariablesThatDenyAccessToSitewideBlockedUsers(): array {
		return [
			'Variables include user_unnamed_ip' => [ 'variables' => [ 'user_unnamed_ip' ] ],
			'Variables include checkuser_user_ip' => [ 'variables' => [ 'checkuser_user_ip' ] ],
			'Variables include checkuser_user_ip and user_unnamed_ip' => [
				'variables' => [ 'checkuser_user_ip', 'user_unnamed_ip' ],
			],
		];
	}

	/** @dataProvider provideCheckUserProtectedVariables */
	public function testCanViewCheckUserProtectedVariablesWhenDisabled( string $variable ): void {
		// Filters which use the variables must stay restricted if the variables are later disabled
		$this->overrideConfigValue( 'CheckUserAbuseFilterVariablesEnabled', false );

		$actualStatus = AbuseFilterServices::getPermissionManager()->canViewProtectedVariables(
			$this->mockRegisteredAuthorityWithPermissions( [ 'abusefilter-access-protected-vars' ] ),
			[ $variable ]
		);

		$this->assertStatusNotGood( $actualStatus );
		$this->assertSame(
			'checkuser',
			$actualStatus->getPermission(),
			'Missing permission in the returned status is not as expected'
		);
	}

	public static function provideCheckUserProtectedVariables(): iterable {
		foreach ( self::CHECKUSER_VARIABLES as $variable ) {
			yield "Variable $variable" => [
				'variable' => $variable,
			];
		}
	}

	/** @dataProvider provideCheckUserProtectedVariables */
	public function testOnAbuseFilterBuilder( string $variable ): void {
		$keywordsManager = AbuseFilterServices::getKeywordsManager();
		$this->assertTrue(
			$keywordsManager->isVarInUse( $variable ),
			"$variable should be a valid AbuseFilter variable"
		);
		$this->assertSame(
			'abusefilter-edit-builder-vars-' . str_replace( '_', '-', $variable ),
			$keywordsManager->getMessageKeyForVar( $variable ),
			"Message key for $variable is not as expected"
		);
	}

	/** @dataProvider provideCheckUserProtectedVariables */
	public function testOnAbuseFilterBuilderWhenVariablesDisabled( string $variable ): void {
		$this->overrideConfigValue( 'CheckUserAbuseFilterVariablesEnabled', false );

		$keywordsManager = AbuseFilterServices::getKeywordsManager();
		$this->assertFalse(
			$keywordsManager->isVarInUse( $variable ),
			"$variable should not be a valid AbuseFilter variable"
		);
	}

	/** @dataProvider provideCheckUserAbuseFilterVariables */
	public function testCheckUserAbuseFilterVariables(
		string $variable,
		bool $isAccountCreation,
		bool $abuseFilterVariablesEnabled,
		bool $recentChangeSet,
		bool $shouldVarBeSet,
		?string $expectedValue
	): void {
		$this->overrideConfigValue( 'CheckUserAbuseFilterVariablesEnabled', $abuseFilterVariablesEnabled );

		$request = new FauxRequest();
		$request->setIP( '1.2.3.4' );
		$request->setHeader( 'User-Agent', 'Mozilla/5.0' );
		RequestContext::getMain()->setRequest( $request );

		$vars = new VariableHolder();
		$user = $this->getTestUser()->getUser();
		if ( $isAccountCreation ) {
			$this->getHookHandler()->onAbuseFilterGenerateAccountCreationVars(
				$vars,
				$this->getTestSysop()->getUserIdentity(),
				$user,
				false,
				$recentChangeSet ? new RecentChange() : null
			);
		} else {
			$this->getHookHandler()->onAbuseFilter_generateUserVars(
				$vars,
				$user,
				$recentChangeSet ? new RecentChange() : null
			);
		}

		if ( $shouldVarBeSet ) {
			$this->assertSame(
				$expectedValue,
				AbuseFilterServices::getVariablesManager()->getVar( $vars, $variable )->toNative()
			);
		} else {
			$this->assertFalse( $vars->varIsSet( $variable ) );
		}
	}

	public static function provideCheckUserAbuseFilterVariables(): iterable {
		foreach ( self::CHECKUSER_VARIABLES as $variable ) {
			$expectedValue = match ( $variable ) {
				'checkuser_user_ip' => '1.2.3.4',
				'checkuser_user_agent' => 'Mozilla/5.0',
			};

			yield from [
				"$variable when filtering current account creation" => [
					'variable' => $variable,
					'isAccountCreation' => true,
					'abuseFilterVariablesEnabled' => true,
					'recentChangeSet' => false,
					'shouldVarBeSet' => true,
					'expectedValue' => $expectedValue,
				],
				"$variable when filtering current action" => [
					'variable' => $variable,
					'isAccountCreation' => false,
					'abuseFilterVariablesEnabled' => true,
					'recentChangeSet' => false,
					'shouldVarBeSet' => true,
					'expectedValue' => $expectedValue,
				],
				"$variable when filtering previous account creation" => [
					'variable' => $variable,
					'isAccountCreation' => true,
					'abuseFilterVariablesEnabled' => true,
					'recentChangeSet' => true,
					'shouldVarBeSet' => true,
					'expectedValue' => null,
				],
				"$variable when filtering previous action" => [
					'variable' => $variable,
					'isAccountCreation' => false,
					'abuseFilterVariablesEnabled' => true,
					'recentChangeSet' => true,
					'shouldVarBeSet' => true,
					'expectedValue' => null,
				],
				"$variable when CheckUserAbuseFilterVariablesEnabled is false" => [
					'variable' => $variable,
					'isAccountCreation' => true,
					'abuseFilterVariablesEnabled' => false,
					'recentChangeSet' => false,
					'shouldVarBeSet' => false,
					'expectedValue' => null,
				],
			];
		}
	}

	public function testOnAbuseFilterInterceptVariableForUnrelatedMethod(): void {
		$result = 'unchanged';
		$this->assertTrue(
			$this->getHookHandler()->onAbuseFilter_interceptVariable(
				'user-unnamed-ip',
				new VariableHolder(),
				[ 'rc' => null ],
				$result
			),
			'The hook should not abort for methods not defined by CheckUser'
		);
		$this->assertSame( 'unchanged', $result, 'The result should not be modified' );
	}
}
