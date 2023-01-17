<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use ApiMain;
use ApiQuery;
use ApiTestCase;
use HashConfig;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\Permissions\Authority;
use Wikimedia\TestingAccessWrapper;

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
		return TestingAccessWrapper::newFromObject( new ApiQueryCheckUser(
			$query, $moduleName, $services->getUserIdentityLookup(),
			$services->getRevisionLookup(), $services->get( 'CheckUserLogService' )
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
}
