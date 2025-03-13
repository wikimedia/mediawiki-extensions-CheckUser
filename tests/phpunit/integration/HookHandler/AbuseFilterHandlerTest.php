<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use Generator;
use MediaWiki\CheckUser\HookHandler\AbuseFilterHandler;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\AbuseFilterHandler
 * @group CheckUser
 * @group Database
 */
class AbuseFilterHandlerTest extends MediaWikiIntegrationTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
	}

	public function testMakesUserUnnamedIpAsAlwaysProtected() {
		// Tests that the hook is called by AbuseFilter and adds the variables to the return
		// value of the protected variables service.
		$this->overrideConfigValue( 'AbuseFilterProtectedVariables', [] );
		$this->assertContains(
			'user_unnamed_ip',
			AbuseFilterServices::getProtectedVariablesLookup()->getAllProtectedVariables()
		);
	}

	public function testOnAbuseFilterCustomProtectedVariables() {
		// Tests the hook handler works without testing that the AbuseFilter part works, to make
		// it easier to diagnose issues if ::testMakesUserUnnamedIpAsAlwaysProtected fails.
		$variables = [];
		( new AbuseFilterHandler(
			$this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' )
		) )->onAbuseFilterCustomProtectedVariables( $variables );
		$this->assertArrayEquals( [ 'user_unnamed_ip' ], $variables );
	}

	public function provideProtectedVarsLogTypes(): Generator {
		yield 'enable access to protected vars values' => [
			[
				'logAction' => 'logAccessEnabled',
				'params' => [],
			],
			[
				'expectedCULogType' => 'af-change-access-enable',
				'expectedAFLogType' => 'change-access-enable',
			]
		];

		yield 'disable access to protected vars values' => [
			[
				'logAction' => 'logAccessDisabled',
				'params' => []
			],
			[
				'expectedCULogType' => 'af-change-access-disable',
				'expectedAFLogType' => 'change-access-disable'
			]
		];
	}

	/**
	 * @dataProvider provideProtectedVarsLogTypes
	 */
	public function testProtectedVarsAccessLogging( $options, $expected ) {
		$performer = $this->getTestSysop();
		$logAction = $options['logAction'];
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->$logAction( $performer->getUserIdentity(), ...$options['params'] );

		// Assert that the action was inserted into CheckUsers' temp account logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => $expected['expectedCULogType'],
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );

		// and also that it wasn't inserted into abusefilter's protected vars logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => $expected['expectedAFLogType'],
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );
	}

	public function testProtectedVarsAccessDebouncedLogging() {
		// Run the same action twice
		$performer = $this->getTestSysop();
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );
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
}
