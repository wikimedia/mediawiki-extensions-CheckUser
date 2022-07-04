<?php

namespace MediaWiki\CheckUser\Tests;

use ApiTestCase;
use HashConfig;
use MediaWiki\Permissions\Authority;

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUser
 */
class ApiQueryCheckUserLogTest extends ApiTestCase {

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuserlog',
	];

	public function doCheckUserLogApiRequest( array $params = [], array $session = null, Authority $performer = null ) {
		return $this->doApiRequest( self::INITIAL_API_PARAMS + $params, $session, false, $performer );
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
}
