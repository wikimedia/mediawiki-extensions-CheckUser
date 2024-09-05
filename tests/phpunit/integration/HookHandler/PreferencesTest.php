<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\Preferences
 */
class PreferencesTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideOnGetPreferencesTemporaryAccount
	 */
	public function testOnGetPreferencesTemporaryAccount( $options ) {
		$user = $this->createMock( User::class );
		$prefs = [];

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $user, $right ) use ( $options ) {
				if ( $right === 'checkuser-temporary-account' ) {
					return $options['hasRight'];
				}
				if ( $right === 'checkuser-temporary-account-no-preference' ) {
					return $options['hasNoPreferenceRight'];
				}
				if ( $right === 'checkuser' ) {
					return false;
				}
				return true;
			} );

		$loggerFactory = $this->createMock( TemporaryAccountLoggerFactory::class );

		( new Preferences(
			$permissionManager,
			$loggerFactory,
			$this->getServiceContainer()->getMainConfig()
		) )->onGetPreferences( $user, $prefs );

		$this->assertSame(
			$options['expected'],
			isset( $prefs['checkuser-temporary-account-enable'] )
		);
		$this->assertSame(
			$options['expected'],
			isset( $prefs['checkuser-temporary-account-enable-description'] )
		);
	}

	public static function provideOnGetPreferencesTemporaryAccount() {
		return [
			'User has right' => [
				[
					'expected' => true,
					'hasRight' => true,
					'hasNoPreferenceRight' => false,
				],
			],
			'User has no-preference right' => [
				[
					'expected' => false,
					'hasRight' => false,
					'hasNoPreferenceRight' => true,
				],
			],
			'User does not have right' => [
				[
					'expected' => false,
					'hasRight' => false,
					'hasNoPreferenceRight' => false,
				],
			],
		];
	}

	/** @dataProvider provideOnGetPreferencesForCheckUserRight */
	public function testGetOnPreferencesForCheckUserRight( $siteConfigValue, $expectedSiteConfigValue ) {
		$this->overrideConfigValue( 'CheckUserCollapseCheckUserHelperByDefault', $siteConfigValue );
		$user = $this->createMock( User::class );
		$prefs = [];

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $user, $right ) {
				return $right === 'checkuser';
			} );

		$loggerFactory = $this->createMock( TemporaryAccountLoggerFactory::class );

		$this->setUserLang( 'qqx' );
		( new Preferences(
			$permissionManager,
			$loggerFactory,
			$this->getServiceContainer()->getMainConfig()
		) )->onGetPreferences( $user, $prefs );

		$this->assertArrayHasKey( 'checkuser-helper-table-collapse-by-default', $prefs );
		$actualOptions = $prefs['checkuser-helper-table-collapse-by-default']['options'];
		// Check that the site config option looks correct.
		$actualSiteConfigLabel = array_search(
			Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, $actualOptions
		);
		$this->assertSame(
			"(checkuser-helper-table-collapse-by-default-preference-default: $expectedSiteConfigValue)",
			$actualSiteConfigLabel
		);
		// Now check the other options than the site config option
		unset( $actualOptions[$actualSiteConfigLabel] );
		$expectedOptions = [
			'(checkuser-helper-table-collapse-by-default-preference-never)' =>
				Preferences::CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT,
			'(checkuser-helper-table-collapse-by-default-preference-always)' =>
				Preferences::CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT,
		];
		$expectedNumberOptions = [ 200, 500, 1000, 2500, 5000 ];
		$language = RequestContext::getMain()->getLanguage();
		foreach ( $expectedNumberOptions as $numberOption ) {
			$expectedOptions[$language->formatNum( $numberOption )] = $numberOption;
		}
		$this->assertArrayEquals(
			$expectedOptions,
			$actualOptions,
			false,
			true
		);
	}

	public static function provideOnGetPreferencesForCheckUserRight() {
		return [
			'Site config set to false' => [ false, '(checkuser-helper-table-collapse-by-default-preference-never)' ],
			'Site config set to true' => [ true, '(checkuser-helper-table-collapse-by-default-preference-always)' ],
			'Site config set to 200' => [ 200, '200' ],
		];
	}
}
