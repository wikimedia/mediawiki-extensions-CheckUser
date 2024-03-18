<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use ApiMain;
use ApiQuery;
use ApiQueryTokens;
use ApiTestCase;
use ManualLogEntry;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Permissions\Authority;
use MediaWiki\Session\SessionManager;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use TestUser;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUser
 */
class ApiQueryCheckUserTest extends ApiTestCase {

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuser',
	];

	protected function setUp(): void {
		parent::setUp();
		// Set a fake time to avoid the tests breaking due to 'cutimecond' being a relative time.
		ConvertibleTimestamp::setFakeTime( '20230406060708' );
	}

	/**
	 * Modified version of doApiRequestWithToken
	 * that appends 'cutoken' instead of 'token'
	 * as the token type. Does not accept the
	 * auto token type. Should not need to use
	 * any token type than the csrf token type
	 * for this purpose, but does accept any
	 * named token type that doApiRequestWith
	 * Token would take.
	 *
	 * @inheritDoc
	 */
	public function doApiRequestWithToken(
		array $params, array $session = null,
		Authority $performer = null, $tokenType = 'csrf', $paramPrefix = null
	) {
		// From ApiTestCase::doApiRequest() but modified
		global $wgRequest;
		$session = $wgRequest->getSessionArray();
		$sessionObj = SessionManager::singleton()->getEmptySession();

		if ( $session !== null ) {
			foreach ( $session as $key => $value ) {
				$sessionObj->set( $key, $value );
			}
		}

		// set up global environment
		if ( $performer ) {
			$legacyUser = $this->getServiceContainer()->getUserFactory()->newFromAuthority( $performer );
			$contextUser = $legacyUser;
		} else {
			$contextUser = $this->getTestUser( 'checkuser' )->getUser();
			$performer = $contextUser;
		}

		$sessionObj->setUser( $contextUser );

		$params['cutoken'] = ApiQueryTokens::getToken(
			$contextUser,
			$sessionObj,
			ApiQueryTokens::getTokenTypeSalts()[$tokenType]
		)->toString();
		return parent::doApiRequestWithToken( $params, $session, $performer, null, $paramPrefix );
	}

	public function doCheckUserApiRequest( array $params = [], array $session = null, Authority $performer = null ) {
		return $this->doApiRequestWithToken( self::INITIAL_API_PARAMS + $params, $session, $performer );
	}

	/**
	 * @param string $action
	 * @param string $moduleName
	 * @return TestingAccessWrapper
	 */
	public function setUpObject( string $action = '', string $moduleName = '' ) {
		$services = $this->getServiceContainer();
		$main = new ApiMain( $this->apiContext, true );
		/** @var ApiQuery $query */
		$query = $main->getModuleManager()->getModule( 'query' );
		return TestingAccessWrapper::newFromObject( new ApiQueryCheckUser(
			$query, $moduleName, $services->getUserIdentityLookup(),
			$services->getRevisionLookup(), $services->getArchivedRevisionLookup(),
			$services->get( 'CheckUserLogService' ), $services->getCommentStore()
		) );
	}

	/**
	 * @dataProvider provideTestInvalidTimeCond
	 */
	public function testInvalidTimeCond( $timeCond ) {
		$this->setExpectedApiException( 'apierror-checkuser-timelimit', 'invalidtime' );
		$this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName(),
				'cutimecond' => $timeCond,
			]
		);
	}

	public static function provideTestInvalidTimeCond() {
		return [
			[ '-2000000000 years' ],
			[ '1 week' ],
			[ '2000000000 weeks' ],
			[ '-45 weeks ago' ],
		];
	}

	public function testMissingRequiredReason() {
		// enable required reason
		$this->setMwGlobals( 'wgCheckUserForceSummary', true );
		$this->setExpectedApiException( 'apierror-checkuser-missingsummary', 'missingdata' );
		$this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName()
			]
		);
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$testUser = $this->getTestUser( $groups );
		if ( !$allowed ) {
			$this->setExpectedApiException( [ 'apierror-permissiondenied', wfMessage( 'action-checkuser' )->text() ] );
		}
		$result = $this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $this->getTestUser()->getUserIdentity()->getName(),
			],
			null,
			$testUser->getUser()
		);
		$this->assertNotNull( $result );
	}

	public static function provideRequiredGroupAccess() {
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
		if ( $groups === "checkuser-right" ) {
			$this->setGroupPermissions(
				[ 'checkuser-right' => [ 'checkuser' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser-right', true ],
		];
	}

	/** @dataProvider provideExpectedApiResponses */
	public function testResponseFromApi(
		$requestType, $expectedRequestTypeInResponse, $target, $timeCond, $xff, $expectedData
	) {
		ConvertibleTimestamp::setFakeTime( '20230406060708' );
		$result = $this->doCheckUserApiRequest(
			[ 'curequest' => $requestType, 'cutarget' => $target, 'cutimecond' => $timeCond, 'cuxff' => $xff ],
			null,
			$this->getTestUser( [ 'checkuser' ] )->getAuthority()
		);
		$this->assertArrayHasKey( 'query', $result[0] );
		$this->assertArrayHasKey( 'checkuser', $result[0]['query'] );
		$this->assertArrayHasKey( $expectedRequestTypeInResponse, $result[0]['query']['checkuser'] );
		$this->assertArrayEquals(
			$expectedData,
			$result[0]['query']['checkuser'][$expectedRequestTypeInResponse],
			false,
			true,
			"The result of the $requestType checkuser query is not as expected."
		);
	}

	public static function provideExpectedApiResponses() {
		return [
			'userips check on CheckUserAPITestUser1' => [
				// The value provided as curequest
				'userips',
				// The expected key name for the array which is the response (usually the same
				// as the curequest but not for 'actions')
				'userips',
				// The target of the checkuser query
				'CheckUserAPITestUser1',
				// The value used as cutimecond, representing a relative time used as the cutoff for results
				'-3 months',
				// The value used as cuxff, representing whether the provided IP target should be searched for as an
				// XFF header.
				null,
				// The expected result of the checkuser query
				[
					[
						'end' => '2023-04-05T06:07:12Z',
						'editcount' => 2,
						'start' => '2023-04-05T06:07:11Z',
						'address' => '1.2.3.4',
					],
					[
						'end' => '2023-04-05T06:07:09Z',
						'editcount' => 1,
						'address' => '127.0.0.2',
					],
					[
						'end' => '2023-04-05T06:07:07Z',
						'editcount' => 1,
						'address' => '127.0.0.1',
					],
				]
			],
			'ipusers check on 127.0.0.1/24' => [
				'ipusers', 'ipusers', '127.0.0.1/24', '-3 months', null,
				[
					[
						'name' => 'CheckUserAPITestUser2',
						'end' => '2023-04-05T06:07:10Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-edits', 'user-agent-for-logs' ],
						'ips' => [ '127.0.0.2', '127.0.0.1' ],
						'start' => '2023-04-05T06:07:08Z'
					],
					[
						'name' => 'CheckUserAPITestUser1',
						'end' => '2023-04-05T06:07:09Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-edits', 'user-agent-for-logs' ],
						'ips' => [ '127.0.0.2', '127.0.0.1' ],
						'start' => '2023-04-05T06:07:07Z'
					],
				]
			],
			'ipusers XFF check on 127.2.3.4' => [
				'ipusers', 'ipusers', '127.2.3.4', '-3 months', true,
				[
					[
						'name' => 'CheckUserAPITestUser1',
						'end' => '2023-04-05T06:07:12Z',
						'editcount' => 2,
						'agents' => [ 'user-agent-for-logout', 'user-agent-for-edits' ],
						'ips' => [ '1.2.3.4' ],
						'start' => '2023-04-05T06:07:11Z'
					],
				]
			],
			'actions XFF check on 127.2.3.4' => [
				'actions', 'edits', '127.2.3.4', '-3 months', true,
				[
					[
						'timestamp' => '2023-04-05T06:07:12Z',
						'ns' => 2,
						'title' => 'CheckUserAPITestUser1',
						'user' => 'CheckUserAPITestUser1',
						'ip' => '1.2.3.4',
						'agent' => 'user-agent-for-logout',
						'summary' => wfMessage( 'checkuser-logout' )->text(),
						'xff' => '127.2.3.4',
					],
					[
						'timestamp' => '2023-04-05T06:07:11Z',
						'ns' => 0,
						'title' => 'CheckUserTestPage',
						'user' => 'CheckUserAPITestUser1',
						'ip' => '1.2.3.4',
						'agent' => 'user-agent-for-edits',
						'summary' => 'Test1233',
						'xff' => '127.2.3.4',
					],
				]
			],
		];
	}

	/** @dataProvider provideCuRequestTypesThatAcceptAUsernameTarget */
	public function testApiForNonExistentUserAsTarget( $requestType ) {
		$this->expectApiErrorCode( 'nosuchuser' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => $requestType, 'cutarget' => 'NonExistentUser', 'cutimecond' => '-3 months' ]
		);
	}

	public static function provideCuRequestTypesThatAcceptAUsernameTarget() {
		return [
			'userips' => [ 'userips' ],
			'actions' => [ 'actions' ],
		];
	}

	public function testIpUsersForInvalidIP() {
		$this->expectApiErrorCode( 'invalidip' );
		$this->doCheckUserApiRequest(
			[ 'curequest' => 'ipusers', 'cutarget' => 'Username', 'cutimecond' => '-3 months' ]
		);
	}

	public function testIsWriteMode() {
		$this->assertTrue(
			$this->setUpObject()->isWriteMode(),
			'The checkuser API writes to the cu_log table so write mode is needed.'
		);
	}

	public function testMustBePosted() {
		$this->assertTrue(
			$this->setUpObject()->mustBePosted(),
			'The checkuser API, like Special:CheckUser, must be posted.'
		);
	}

	public function testNeedsToken() {
		$this->assertSame(
			'csrf',
			$this->setUpObject()->needsToken(),
			'The checkuser API requires the csrf token.'
		);
	}

	/**
	 * Tests that the function returns valid URLs.
	 * Does not test that the URL is correct as if
	 * the URL is changed in a proposed commit the
	 * reviewer should check the URL points to the
	 * right place.
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

	private function createLogEntry( UserIdentity $performer, Title $page ) {
		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $page );
		$logEntry->setComment( 'A very good reason' );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}

	public function addDBDataOnce() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		// Add some testing entries to the CheckUser result tables to test the API
		// Get two testing users with pre-defined usernames and a test page with a pre-defined name
		// so that we can use them in the tests without having to store the name.
		$firstTestUser = ( new TestUser( 'CheckUserAPITestUser1' ) )->getUser();
		$secondTestUser = ( new TestUser( 'CheckUserAPITestUser2' ) )->getUser();
		$testPage = $this->getExistingTestPage( 'CheckUserTestPage' )->getTitle();
		// Clear the cu_changes and cu_log_event tables to avoid log entries created by the test users being created
		// or the page being created affecting the tests.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event' ] );

		// Insert two testing log entries with each performed where one is performed by each test user
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'user-agent-for-logs' );
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		$this->createLogEntry( $firstTestUser, $testPage );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->createLogEntry( $secondTestUser, $testPage );

		// Insert two testing edits to cu_changes with a IP as 127.0.0.2 and have one performed by each test user
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'user-agent-for-edits' );
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			Title::newFromDBkey( 'CheckUserTestPage' ),
			'Testing1231',
			'Test1231',
			NS_MAIN,
			$firstTestUser
		);
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		$this->editPage(
			Title::newFromDBkey( 'CheckUserTestPage' ),
			'Testing1232',
			'Test1232',
			NS_MAIN,
			$secondTestUser
		);

		// Insert one edit with a different IP and a defined XFF header
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		RequestContext::getMain()->getRequest()->setHeader( 'X-Forwarded-For', '127.2.3.4' );
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		$this->editPage(
			Title::newFromDBkey( 'CheckUserTestPage' ),
			'Testing1233',
			'Test1233',
			NS_MAIN,
			$firstTestUser
		);

		// Simulate a logout event for the first user
		$hookRunner = new HookRunner( $this->getServiceContainer()->getHookContainer() );
		ConvertibleTimestamp::setFakeTime( '20230405060712' );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'user-agent-for-logout' );
		$injectHtml = '';
		$hookRunner->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '127.0.0.1' ) ),
			$injectHtml,
			$firstTestUser->getName()
		);
		// Reset the fake time to avoid any issues with other test classes. A fake time will be set before each
		// test in ::setUp.
		ConvertibleTimestamp::setFakeTime( false );
	}
}
