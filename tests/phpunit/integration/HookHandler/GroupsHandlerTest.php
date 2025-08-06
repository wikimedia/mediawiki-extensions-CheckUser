<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\GroupsHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupMembership;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\GroupsHandler
 */
class GroupsHandlerTest extends MediaWikiIntegrationTestCase {
	private static string $timestampNow = '20230406060708';

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( self::$timestampNow );

		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	private function getHandler( $overrideServices ) {
		$services = $this->getServiceContainer();

		$arguments = array_merge( [
			'config' => $services->getMainConfig(),
			'lbFactory' => $services->getDBLoadBalancerFactory(),
			'extensionRegistry' => $services->getExtensionRegistry(),
			'userGroupManager' => $services->getUserGroupManager(),
			'userEditTracker' => $services->getUserEditTracker(),
			'userRegistrationLookup' => $services->getUserRegistrationLookup(),
			'centralIdLookup' => $services->get( 'CentralIdLookup' ),
			'wanCache' => $services->getMainWANObjectCache(),
			'specialPageFactory' => $services->getSpecialPageFactory()
		], $overrideServices );

		return new GroupsHandler( ...array_values( $arguments ) );
	}

	/** @dataProvider provideOnSpecialUserRightsChangeableGroups */
	public function testOnSpecialUserRightsChangeableGroups(
		array $config,
		array $expected
	) {
		$now = ConvertibleTimestamp::convert( TS_UNIX, self::$timestampNow );

		$this->overrideConfigValue( 'CheckUserGroupRequirements', $config );

		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'getUserGroups' )
			->willReturn( [ 'sysop' ] );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( 300 );

		$userRegistrationLookup = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookup->method( 'getRegistration' )
			->willReturn( $now - ( 86400 * 30 * 6 ) );

		$handler = $this->getHandler( [
			'userGroupManager' => $userGroupManager,
			'userEditTracker' => $userEditTracker,
			'userRegistrationLookup' => $userRegistrationLookup,
		] );

		$addableGroups = [ 'temporary-account-viewer' ];
		$unaddableGroups = [];
		$handler->onSpecialUserRightsChangeableGroups(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser(),
			$addableGroups,
			$unaddableGroups
		);

		$this->assertSame( $expected, $unaddableGroups );
	}

	public static function provideOnSpecialUserRightsChangeableGroups() {
		return [
			'Group not set in config' => [
				'config' => [],
				'expected' => [],
			],
			'Group set in config but not configured' => [
				'config' => [ 'temporary-account-viewer' => [] ],
				'expected' => [],
			],
			'Target does not meet requirements, reason not configured' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 400,
						'age' => 86400 * 30 * 12,
						'exemptGroups' => [],
					],
				],
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Target does not meet requirements, reason is configured' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 400,
						'age' => 86400 * 30 * 12,
						'exemptGroups' => [],
						'reason' => 'checkuser-group-requirements-temporary-account-viewer'
					],
				],
				'expected' => [ 'temporary-account-viewer' =>
					'checkuser-group-requirements-temporary-account-viewer' ],
			],
			'Target does not meet edit requirement, but does meet age requirement' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 301,
						'age' => 86400 * 30 * 4,
						'exemptGroups' => [],
					],
				],
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Target does not meet account age requirement, but meets edit count requirement' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 200,
						'age' => 86400 * 30 * 6 + 1,
						'exemptGroups' => [],
					],
				],
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Target does not meet requirements, but performer is exempt' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 400,
						'age' => 86400 * 30 * 12,
						'exemptGroups' => [ 'sysop' ],
					],
				],
				'expected' => [],
			],
			'Target meets requirements' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 299,
						'age' => 86400 * 30 * 6 - 1,
						'exemptGroups' => [],
					],
				],
				'expected' => [],
			],
			'Target meets requirements exactly' => [
				'config' => [
					'temporary-account-viewer' => [
						'edits' => 300,
						'age' => 86400 * 30 * 6,
						'exemptGroups' => [],
					],
				],
				'expected' => [],
			],
		];
	}

	/**
	 * @dataProvider provideOnSpecialUserRightsChangeableGroupsDataNotFound
	 */
	public function testOnSpecialUserRightsChangeableGroupsDataNotFound(
		?int $editCount,
		?int $registration,
		array $expected
	) {
		$this->overrideConfigValue( 'CheckUserGroupRequirements', [
			'temporary-account-viewer' => [
				'edits' => 300,
				'age' => 86400 * 30 * 6,
				'exemptGroups' => [ 'sysop' ],
			],
		] );

		$userGroupManager = $this->createMock( UserGroupManager::class );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( $editCount );

		$userRegistrationLookup = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookup->method( 'getRegistration' )
			->willReturn( $registration );

		$handler = $this->getHandler( [
			'userGroupManager' => $userGroupManager,
			'userEditTracker' => $userEditTracker,
			'userRegistrationLookup' => $userRegistrationLookup,
		] );

		$addableGroups = [ 'temporary-account-viewer' ];
		$unaddableGroups = [];
		$handler->onSpecialUserRightsChangeableGroups(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser(),
			$addableGroups,
			$unaddableGroups
		);

		$this->assertSame( $expected, $unaddableGroups );
	}

	public static function provideOnSpecialUserRightsChangeableGroupsDataNotFound() {
		$now = ConvertibleTimestamp::convert( TS_UNIX, self::$timestampNow );

		return [
			'Not allowed if target edit count not found, even though meets age requirement' => [
				'editCount' => null,
				'registration' => $now - ( 86400 * 30 * 60 ),
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			// Registration date may be unavailable for very old accounts
			'Allowed if target account age not found and meets edit count requirement' => [
				'editCount' => 1000,
				'registration' => null,
				'expected' => [],
			],
		];
	}

	/**
	 * @dataProvider provideOnSpecialUserRightsChangeableGroupsGlobalGroupExempt
	 */
	public function testOnSpecialUserRightsChangeableGroupsGlobalGroupExempt(
		bool $exists,
		bool $attached,
		bool $exempt,
		array $expected
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$now = ConvertibleTimestamp::convert( TS_UNIX, self::$timestampNow );

		$this->overrideConfigValue( 'CheckUserGroupRequirements', [
			'temporary-account-viewer' => [
				'edits' => 300,
				'age' => 86400 * 30 * 6,
				'exemptGroups' => [ 'steward' ],
			],
		] );

		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'getUserGroups' )
			->willReturn( [] );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( 1 );

		$userRegistrationLookup = $this->createMock( UserRegistrationLookup::class );
		$userRegistrationLookup->method( 'getRegistration' )
			->willReturn( $now - 1 );

		$handler = $this->getHandler( [
			'userGroupManager' => $userGroupManager,
			'userEditTracker' => $userEditTracker,
			'userRegistrationLookup' => $userRegistrationLookup,
		] );

		$testUser = $this->getTestUser();
		$performer = $testUser->getUser();
		if ( $exists ) {
			$centralUser = CentralAuthUser::getInstanceByName( $performer );

			if ( $attached ) {
				$centralUser->register( $testUser->getPassword(), null );
				$centralUser->attach( WikiMap::getCurrentWikiId() );

				if ( $exempt ) {
					$centralUser->addToGlobalGroup( 'steward' );
				}
			}
		}

		$addableGroups = [ 'temporary-account-viewer' ];
		$unaddableGroups = [];
		$handler->onSpecialUserRightsChangeableGroups(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser(),
			$addableGroups,
			$unaddableGroups
		);

		$this->assertSame( $expected, $unaddableGroups );
	}

	public static function provideOnSpecialUserRightsChangeableGroupsGlobalGroupExempt() {
		return [
			'No central user exists' => [
				'exists' => false,
				'attached' => false,
				'exempt' => false,
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Central user not attached' => [
				'exists' => true,
				'attached' => false,
				'exempt' => false,
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Central user has no exempt groups' => [
				'exists' => true,
				'attached' => true,
				'exempt' => false,
				'expected' => [ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			],
			'Central user has exempt global group' => [
				'exists' => true,
				'attached' => true,
				'exempt' => true,
				'expected' => [],
			],
		];
	}

	public function testOnSpecialUserRightsChangeableGroupsExternalUser() {
		$now = ConvertibleTimestamp::convert( TS_UNIX, self::$timestampNow );

		$this->overrideConfigValue( 'CheckUserGroupRequirements', [
			'temporary-account-viewer' => [
				'edits' => 300,
				'age' => 86400 * 30 * 6,
			],
		] );

		$target = $this->createMock( UserIdentity::class );
		$target->method( 'getWikiId' )
			->willReturn( 'otherwiki' );

		// In the absence of looking up a user at a different wiki, mock the lookup
		// and test that the results are interpreted correctly.

		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchRow' ) ) )
			->willReturnSelf();
		$queryBuilder->method( 'fetchRow' )
			->willReturn( [
				'user_editcount' => 200,
				'user_registration' => $now - ( 86400 * 30 * 12 ),
			] );

		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'newSelectQueryBuilder' )
			->willReturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturn( $database );

		$handler = $this->getHandler( [
			'lbFactory' => $dbProvider,
		] );

		$addableGroups = [ 'temporary-account-viewer' ];
		$unaddableGroups = [];
		$handler->onSpecialUserRightsChangeableGroups(
			$this->getTestUser()->getAuthority(),
			$this->getTestUser()->getUser(),
			$addableGroups,
			$unaddableGroups
		);

		$this->assertSame(
			[ 'temporary-account-viewer' => 'checkuser-group-requirements' ],
			$unaddableGroups
		);
	}

	/** @dataProvider provideOnUserGroupsChanged */
	public function testOnUserGroupsChanged(
		bool $globalContributionsExists,
		array $addedGroups,
		int $centralId,
		int $expected
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		$user = $this->getTestUser()->getUser();

		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( $centralId );

		$wanCache = $this->createMock( WANObjectCache::class );
		$wanCache->method( 'makeGlobalKey' )
			->with( 'globalcontributions-ext-permissions', $centralId )
			->willReturn( 'checkKey' );
		$wanCache->expects( $this->exactly( $expected ) )
			->method( 'touchCheckKey' )
			->with( 'checkKey' );

		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->method( 'exists' )
			->with( 'GlobalContributions' )
			->willReturn( $globalContributionsExists );

		$handler = $this->getHandler( [
			'centralIdLookup' => $centralIdLookup,
			'wanCache' => $wanCache,
			'specialPageFactory' => $specialPageFactory,
		] );

		$handler->onUserGroupsChanged(
			$user,
			$addedGroups,
			[],
			false,
			false,
			[],
			array_fill_keys(
				$addedGroups,
				$this->createMock( UserGroupMembership::class )
			)
		);
	}

	public static function provideOnUserGroupsChanged() {
		return [
			'Early return when GlobalContributions does not exist' => [
				'globalContributionsexists' => false,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 1,
				'expected' => 0,
			],
			'Early return when groups not changed' => [
				'globalContributionsexists' => true,
				'addedGroups' => [],
				'centralUserId' => 1,
				'expected' => 0,
			],
			'Early return when central user does not exist' => [
				'globalContributionsexists' => true,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 0,
				'expected' => 0,
			],
			'Cache invalidated when GlobalContributions exists' => [
				'globalContributionsexists' => true,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 1,
				'expected' => 1,
			],
		];
	}
}
