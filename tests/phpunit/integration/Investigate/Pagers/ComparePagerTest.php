<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use LoggedServiceOptions;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePager;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RequestContext;
use TestAllServiceOptionsUsed;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\ComparePager
 */
class ComparePagerTest extends MediaWikiIntegrationTestCase {
	use TestAllServiceOptionsUsed;
	use TempUserTestTrait;
	use MockAuthorityTrait;

	private static UserIdentity $hiddenUser;

	private function getObjectUnderTest( array $overrides = [] ): ComparePager {
		$services = $this->getServiceContainer();
		return new ComparePager(
			RequestContext::getMain(),
			$overrides['linkRenderer'] ?? $services->getLinkRenderer(),
			$overrides['tokenQueryManager'] ?? $services->get( 'CheckUserTokenQueryManager' ),
			$overrides['durationManager'] ?? $services->get( 'CheckUserDurationManager' ),
			$overrides['compareService'] ?? $services->get( 'CheckUserCompareService' ),
			$overrides['userFactory'] ?? $services->getUserFactory()
		);
	}

	/** @dataProvider provideFormatValue */
	public function testFormatValue( array $row, $name, $expectedFormattedValue ) {
		// Set the user language to qqx so that we can compare against the message keys and not the english version of
		// the message key (which may change and then break the tests).
		$this->setUserLang( 'qqx' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $this->getObjectUnderTest() );
		// Set the $row as an object to mCurrentRow for access by ::formatValue.
		$objectUnderTest->mCurrentRow = (object)$row;
		/** @var $objectUnderTest ComparePager */
		$this->assertSame(
			$expectedFormattedValue,
			$objectUnderTest->formatValue( $name, $row[$name] ?? null ),
			'::formatRow did not return the expected HTML'
		);
	}

	public static function provideFormatValue() {
		return [
			'activity as $name' => [
				// The row set as $this->mCurrentRow in the object under test, provided as an array
				[ 'first_edit' => '20240405060708', 'last_edit' => '20240406060708' ],
				// The $name argument to ::formatValue
				'activity',
				// The expected formatted value
				'5 (april) 2024 - 6 (april) 2024'
			],
			'user agent is not null' => [ [ 'agent' => 'test' ], 'agent', 'test' ],
			'user agent is null' => [ [ 'agent' => null ], 'agent', '' ],
			'user agent contains unescaped HTML' => [
				[ 'agent' => '<b>test</b>' ], 'agent', '&lt;b&gt;test&lt;/b&gt;',
			],
			'ip is 1.2.3.4' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_edits' => 1 ],
				'ip',
				'<span class="ext-checkuser-compare-table-cell-ip">1.2.3.4</span>' .
				'<div>(checkuser-investigate-compare-table-cell-actions: 1) ' .
				'<span>(checkuser-investigate-compare-table-cell-other-actions: 5)</span></div>',
			],
			'unrecognised $name' => [ [], 'foo', '' ],
			'user_text as 1.2.3.5' => [
				[ 'user_text' => '1.2.3.5', 'user' => 0 ], 'user_text',
				'(checkuser-investigate-compare-table-cell-unregistered)',
			],
		];
	}

	public function testFormatValueForHiddenUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );
		$this->testFormatValue(
			// The $hiddenUser static property may not be set when the data providers are called, so this needs to be
			// accessed in a test method.
			[ 'user_text' => self::$hiddenUser->getName(), 'user' => self::$hiddenUser->getId() ],
			'user_text',
			'(rev-deleted-user)'
		);
	}

	public function testFormatValueForUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority(
			$this->mockRegisteredAuthorityWithPermissions( [ 'hideuser', 'checkuser' ] )
		);
		$this->testFormatValue(
			[ 'user_text' => self::$hiddenUser->getName(), 'user' => self::$hiddenUser->getId() ],
			'user_text',
			// We cannot mock a static method, so we have to use the real method here.
			// This also means this cannot be in a data provider.
			Linker::userLink( self::$hiddenUser->getId(), self::$hiddenUser->getName() )
		);
	}

	/** @dataProvider provideGetCellAttrs */
	public function testGetCellAttrs(
		array $row, array $filteredTargets, array $ipTotalEdits, $name, $expectedClasses, $otherExpectedAttributes
	) {
		// Set the user language to qqx so that we can compare against the message keys and not the english version of
		// the message key (which may change and then break the tests).
		$this->setUserLang( 'qqx' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $this->getObjectUnderTest() );
		// Set the $row, $filteredTargets, and $ipTotalEdits in the relevant properties of the object under test so that
		// ::getCellAttrs can access them.
		$objectUnderTest->mCurrentRow = (object)$row;
		$objectUnderTest->filteredTargets = $filteredTargets;
		$objectUnderTest->ipTotalEdits = $ipTotalEdits;
		/** @var $objectUnderTest ComparePager */
		$actualCellAttrs = $objectUnderTest->getCellAttrs( $name, $row[$name] ?? null );
		foreach ( $expectedClasses as $class ) {
			$this->assertStringContainsString(
				$class,
				$actualCellAttrs['class'],
				"The class $class was not in the actual classes for the cell"
			);
		}
		// Unset the 'class' so that we can test the other attributes using ::assertArrayEquals
		unset( $actualCellAttrs['class'] );
		// Add 'tabindex' as 0 to the expected attributes, as this is always added by ::getCellAttrs.
		$otherExpectedAttributes['tabindex'] = 0;
		$this->assertArrayEquals(
			$otherExpectedAttributes,
			$actualCellAttrs,
			false,
			true,
			'::getCellAttrs did not return the expected attributes'
		);
	}

	public static function provideGetCellAttrs() {
		return [
			'$name as ip when IP $value is inside a filtered target IP range' => [
				// The row set as $this->mCurrentRow in the object under test, provided as an array
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_edits' => 1 ],
				// The value of the filteredTargets property in the object under test (only used for ip and
				// user_text $name values).
				[ 'TestUser1', '1.2.3.0/24' ],
				// The value of the ipTotalEdits property in the object under test (only used for ip $name values).
				[ IPUtils::toHex( '1.2.3.4' ) => 2 ],
				// The $name argument to ::getCellAttrs
				'ip',
				// The expected classes for the cell
				[
					'ext-checkuser-compare-table-cell-target', 'ext-checkuser-compare-table-cell-ip-target',
					'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				// The expected attributes for the cell (minus the class, as this is tested above).
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-edits' => 1, 'data-all-edits' => 2,
				],
			],
			'$name as ip when IP $value is a filtered target' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_edits' => 1 ],
				[ 'TestUser1', '1.2.3.4' ], [ IPUtils::toHex( '1.2.3.4' ) => 2 ], 'ip',
				[
					'ext-checkuser-compare-table-cell-target', 'ext-checkuser-compare-table-cell-ip-target',
					'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-edits' => 1, 'data-all-edits' => 2,
				],
			],
			'$name as ip when IP is not in filtered targets array' => [
				[ 'ip' => '1.2.3.4', 'ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'total_edits' => 1 ],
				[], [ IPUtils::toHex( '1.2.3.4' ) => 2 ], 'ip',
				[
					'ext-checkuser-compare-table-cell-ip-target', 'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[
					'data-field' => 'ip', 'data-value' => '1.2.3.4',
					'data-sort-value' => IPUtils::toHex( '1.2.3.4' ), 'data-edits' => 1, 'data-all-edits' => 2,
				],
			],
			'$name as user_text for IP address' => [
				[ 'user_text' => '1.2.3.4' ], [], [], 'user_text',
				[ 'ext-checkuser-investigate-table-cell-interactive' ],
				[ 'data-sort-value' => '1.2.3.4' ],
			],
			'$name as user_text for unregistered user that is a target' => [
				[ 'user_text' => 'TestUser1' ], [ 'TestUser1' ], [], 'user_text',
				[ 'ext-checkuser-compare-table-cell-target', 'ext-checkuser-investigate-table-cell-interactive' ],
				[ 'data-field' => 'user_text', 'data-value' => 'TestUser1', 'data-sort-value' => 'TestUser1' ],
			],
			'$name as activity' => [
				[ 'first_edit' => '20240405060708', 'last_edit' => '20240406060708' ], [], [], 'activity',
				[ 'ext-checkuser-compare-table-cell-activity' ], [ 'data-sort-value' => '2024040520240406' ],
			],
			'$name as agent' => [
				[ 'agent' => 'test' ], [], [], 'agent',
				[
					'ext-checkuser-compare-table-cell-user-agent', 'ext-checkuser-investigate-table-cell-pinnable',
					'ext-checkuser-investigate-table-cell-interactive',
				],
				[ 'data-field' => 'agent', 'data-value' => 'test', 'data-sort-value' => 'test' ],
			],
		];
	}

	public function testGetCellAttrsForHiddenUser() {
		// Assign the rights to the main context authority, which will be used by the object under test.
		RequestContext::getMain()->setAuthority( $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ) );
		$this->testGetCellAttrs(
			[ 'user_text' => self::$hiddenUser->getName() ], [], [], 'user_text',
			[ 'ext-checkuser-investigate-table-cell-interactive' ],
			[
				'data-field' => 'user_text',
				'data-value' => '(rev-deleted-user)', 'data-sort-value' => '(rev-deleted-user)',
			]
		);
	}

	/**
	 * @dataProvider provideDoQuery
	 */
	public function testDoQuery( $targets, $excludeTargets, $expected ) {
		$services = MediaWikiServices::getInstance();

		$tokenQueryManager = $this->getMockBuilder( TokenQueryManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getDataFromRequest' ] )
			->getMock();
		$tokenQueryManager->method( 'getDataFromRequest' )
			->willReturn( [
				'targets' => $targets,
				'exclude-targets' => $excludeTargets,
			] );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )
			->willReturn( 11111 );

		$user2 = $this->createMock( UserIdentity::class );
		$user2->method( 'getId' )
			->willReturn( 22222 );

		$user3 = $this->createMock( UserIdentity::class );
		$user3->method( 'getId' )
			->willReturn( 0 );

		$userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$userIdentityLookup->method( 'getUserIdentityByName' )
			->willReturnMap(
				[
					[ 'User1', 0, $user, ],
					[ 'User2', 0, $user2, ],
					[ 'InvalidUser', 0, $user3, ],
					[ '', 0, $user3, ],
					[ '1.2.3.9/120', 0, $user3, ]
				]
			);

		$compareService = new CompareService(
			new LoggedServiceOptions(
				self::$serviceOptionsAccessLog,
				CompareService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getDBLoadBalancerFactory(),
			$userIdentityLookup,
			$services->get( 'CheckUserLookupUtils' )
		);

		$durationManager = $this->createMock( DurationManager::class );

		$pager = $this->getObjectUnderTest( [
			'tokenQueryManager' => $tokenQueryManager,
			'compareService' => $compareService,
			'durationManager' => $durationManager,
		] );
		$pager->doQuery();

		$this->assertSame( $expected, $pager->mResult->numRows() );
	}

	public static function provideDoQuery() {
		// $targets, $excludeTargets, $expected
		return [
			'Valid and invalid targets' => [ [ 'User1', 'InvalidUser', '1.2.3.9/120' ], [], 2 ],
			'Valid and empty targets' => [ [ 'User1', '' ], [], 2 ],
			'Valid user target' => [ [ 'User2' ], [], 1 ],
			'Valid user target with excluded name' => [ [ 'User2' ], [ 'User2' ], 0 ],
			'Valid user target with excluded IP' => [ [ 'User2' ], [ '1.2.3.4' ], 0 ],
			'Valid IP target' => [ [ '1.2.3.4' ], [], 4 ],
			'Valid IP target with users excluded' => [ [ '1.2.3.4' ], [ 'User1', 'User2' ], 2 ],
			'Valid IP range target' => [ [ '1.2.3.0/24' ], [], 7 ],
		];
	}

	public function addDBDataOnce() {
		// Automatic temp user creation cannot be enabled
		// if actor IDs are being created for IPs.
		$this->disableAutoCreateTempUser();
		$actorStore = $this->getServiceContainer()->getActorStore();

		$testActorData = [
			'User1' => [
				'actor_id'   => 0,
				'actor_user' => 11111,
			],
			'User2' => [
				'actor_id'   => 0,
				'actor_user' => 22222,
			],
			'1.2.3.4' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
			'1.2.3.5' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
		];

		foreach ( $testActorData as $name => $actor ) {
			$testActorData[$name]['actor_id'] = $actorStore->acquireActorId(
				new UserIdentityValue( $actor['actor_user'], $name ),
				$this->getDb()
			);
		}

		$testData = [
			[
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'bar user agent',
			], [
				'cuc_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_actor'      => $testActorData['User1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_actor'      => $testActorData['User2']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
			], [
				'cuc_actor'      => $testActorData['User1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
			],
		];

		$commonData = [
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_timestamp'  => $this->db->timestamp(),
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_actiontext' => '',
			'cuc_comment_id' => 0,
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as $row ) {
			$this->db->newInsertQueryBuilder()
				->insertInto( 'cu_changes' )
				->row( $row + $commonData )
				->execute();
		}

		// Get a test user and apply a 'hideuser' block to that test user
		$hiddenUser = $this->getTestUser()->getUser();
		// Place a 'hideuser' block on the test user to hide the user
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser, $this->getTestUser( [ 'sysop', 'suppress' ] )->getUser(),
				'infinity', 'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		self::$hiddenUser = $hiddenUser;
	}
}
