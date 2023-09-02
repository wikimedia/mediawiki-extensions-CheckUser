<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use ApiMain;
use ApiQuery;
use ApiQueryTokens;
use ApiTestCase;
use HashConfig;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Session\SessionManager;
use Wikimedia\TestingAccessWrapper;

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
				'curequest' => 'edits',
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
				'curequest' => 'edits',
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
				'curequest' => 'edits',
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
			$this->overrideMwServices(
				new HashConfig(
					[ 'GroupPermissions' =>
						[ 'checkuser-right' => [ 'checkuser' => true, 'read' => true ] ]
					]
				)
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
}
