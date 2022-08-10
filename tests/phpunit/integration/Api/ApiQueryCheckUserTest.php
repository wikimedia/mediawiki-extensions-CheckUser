<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use ApiQueryTokens;
use ApiTestCase;
use HashConfig;
use MediaWiki\Permissions\Authority;
use MediaWiki\Session\SessionManager;

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

	public function provideTestInvalidTimeCond() {
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

	public function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser-right', true ],
		];
	}
}
