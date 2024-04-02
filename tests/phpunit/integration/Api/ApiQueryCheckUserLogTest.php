<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use ApiMain;
use ApiQuery;
use ApiTestCase;
use HashConfig;
use MediaWiki\CheckUser\Api\ApiQueryCheckUserLog;
use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUser
 */
class ApiQueryCheckUserLogTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_log',
				'comment',
				'actor'
			]
		);
	}

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuserlog',
	];

	/**
	 * Does an API request to the checkuserlog API
	 *  and returns the result.
	 *
	 * @param array $params
	 * @param array|null $session
	 * @param Authority|null $performer
	 * @return array
	 * @throws \ApiUsageException
	 */
	public function doCheckUserLogApiRequest( array $params = [], array $session = null, Authority $performer = null ) {
		if ( $performer === null ) {
			$performer = $this->getTestUser( 'checkuser' )->getAuthority();
		}
		return $this->doApiRequest( self::INITIAL_API_PARAMS + $params, $session, false, $performer );
	}

	/**
	 * @param string $action
	 * @param string $moduleName
	 * @return TestingAccessWrapper
	 */
	public function setUpObject( string $action = '', string $moduleName = '' ) {
		$services = $this->getServiceContainer();
		$query = new ApiQuery(
			new ApiMain( $this->apiContext, true ),
			$action,
			$services->getObjectFactory(),
			$services->getDBLoadBalancer(),
			$services->getWikiExporterFactory()
		);
		return TestingAccessWrapper::newFromObject( new ApiQueryCheckUserLog(
			$query, $moduleName, $services->getCommentStore(),
			$services->get( 'CheckUserLogService' ), $services->getUserFactory()
		) );
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$testUser = $this->getTestUser( $groups );
		if ( !$allowed ) {
			$this->setExpectedApiException(
				[ 'apierror-permissiondenied', wfMessage( 'action-checkuser-log' )->text() ]
			);
		}
		$result = $this->doCheckUserLogApiRequest(
			[],
			null,
			$testUser->getUser()
		);
		$this->assertNotNull( $result );
	}

	public function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( $groups === "checkuser-log" ) {
			$this->overrideMwServices(
				new HashConfig(
					[ 'GroupPermissions' =>
						[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
					]
				)
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser-log right only' => [ 'checkuser-log', true ],
		];
	}

	/**
	 * Tests that the function returns valid URLs.
	 * Does not test that the URL is correct as if
	 * the URL is changed in a proposed commit the
	 * reviewer should check the URL points to the
	 * right place.
	 *
	 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUserLog::getHelpUrls
	 */
	public function testGetHelpUrls() {
		$helpUrls = $this->setUpObject()->getHelpUrls();
		if ( !is_string( $helpUrls ) && !is_array( $helpUrls ) ) {
			$this->fail( 'getHelpUrls should return an array of URLs or a URL' );
		}
		if ( is_string( $helpUrls ) ) {
			$helpUrls = [ $helpUrls ];
		}
		foreach ( $helpUrls as $helpUrl ) {
			$this->assertIsArray( parse_url( $helpUrl ) );
		}
	}

	/**
	 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUserLog::execute
	 * @dataProvider provideExampleLogEntryDataForReasonFilterTest
	 */
	public function testReasonFilter(
		$logType, $targetType, $target, $reason, $targetID, $timestamp, $reasonToSearchFor, $shouldSeeEntry
	) {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), $logType, $targetType, $target, $reason, $targetID, $timestamp
		);
		\DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest( [
			'culreason' => $reasonToSearchFor
		] )[0]['query']['checkuserlog']['entries'];
		if ( $shouldSeeEntry ) {
			$this->assertCount( 1, $result, 'A search for the reason should show one entry.' );
		} else {
			$this->assertCount( 0, $result, 'A search for the reason should show no entries.' );
		}
		$result = $this->doCheckUserLogApiRequest( [
			'culreason' => $checkUserLogService->getPlaintextReason( $reasonToSearchFor )
		] )[0]['query']['checkuserlog']['entries'];
		if ( $shouldSeeEntry ) {
			$this->assertCount(
				1, $result, 'A search for the plaintext version of the reason should show one entry.'
			);
		} else {
			$this->assertCount(
				0, $result, 'A search for the plaintext version of the reason should show no entries.'
			);
		}
	}

	public function provideExampleLogEntryDataForReasonFilterTest() {
		$tests = [];
		foreach ( $this->provideExampleLogEntryData() as $name => $values ) {
			$tests[$name . ' with matching reason and log reason migration set to read old'] =
				array_merge( $values, [ $values[3], true, SCHEMA_COMPAT_OLD ] );
			$tests[$name . ' with matching reason and log reason migration set to read new'] =
				array_merge( $values, [ $values[3], true, SCHEMA_COMPAT_NEW ] );
			$tests[$name . ' with non-matching reason and log reason migration set to read old'] =
				array_merge( $values, [ 'Nonexisting reason12345', false, SCHEMA_COMPAT_OLD ] );
			$tests[$name . ' with non-matching reason and log reason migration set to read new'] =
				array_merge( $values, [ 'Nonexisting reason12345', false, SCHEMA_COMPAT_NEW ] );
		}
		return $tests;
	}

	/**
	 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUserLog::execute
	 * @dataProvider provideExampleLogEntryData
	 */
	public function testReturnsCorrectData( $logType, $targetType, $target, $reason, $targetID, $timestamp ) {
		ConvertibleTimestamp::setFakeTime( $timestamp );
		// Set up by the DB by inserting data.
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = MediaWikiServices::getInstance()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), $logType, $targetType, $target, $reason, $targetID
		);
		\DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest()[0]['query']['checkuserlog']['entries'];
		$this->assertCount( 1, $result, 'Should only be one CheckUserLog entry returned.' );
		$this->assertArrayEquals(
			[
				'timestamp' => ConvertibleTimestamp::convert( TS_ISO_8601, $timestamp ),
				'checkuser' => $this->getTestSysop()->getUserIdentity()->getName(),
				'type' => $logType,
				'reason' => $reason,
				'target' => $target
			],
			$result[0],
			'CheckUserLog entry returned was not correct.'
		);
	}

	public function provideExampleLogEntryData() {
		return [
			'IP target' => [ 'ipusers', 'ip', '127.0.0.1', 'testing', 0, '1653047635' ],
			'User target' => [ 'userips', 'user', 'Testing', '1234 - [[test]]', 0, '1653042345' ],
		];
	}
}
