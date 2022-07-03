<?php

namespace MediaWiki\CheckUser\Tests;

use FormOptions;
use HashConfig;
use MediaWiki\CheckUser\Specials\SpecialCheckUser;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser
 */
class SpecialCheckUserTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	/**
	 * @var int
	 */
	private $lowerThanLimitIPv4;

	/**
	 * @var int
	 */
	private $lowerThanLimitIPv6;

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'page',
				'revision',
				'ip_changes',
				'text',
				'archive',
				'recentchanges',
				'logging',
				'page_props',
				'cu_changes',
			]
		);

		$this->setMwGlobals( [
			'wgCheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			]
		] );

		$CIDRLimit = \RequestContext::getMain()->getConfig()->get( 'CheckUserCIDRLimit' );
		$this->lowerThanLimitIPv4 = $CIDRLimit['IPv4'] - 1;
		$this->lowerThanLimitIPv6 = $CIDRLimit['IPv6'] - 1;
	}

	/**
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject() {
		$object = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
		$testingWrapper = TestingAccessWrapper::newFromObject( $object );
		$testingWrapper->opts = new FormOptions();
		return $testingWrapper;
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser::getIpConds
	 * @dataProvider provideGetIpConds
	 */
	public function testGetIpConds( $target, $expected ) {
		$dbr = wfGetDB( DB_REPLICA );

		$this->assertEquals(
			$expected,
			SpecialCheckUser::getIpConds( $dbr, $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::getIpConds
	 * @return array
	 */
	public function provideGetIpConds() {
		return [
			[
				'212.35.31.121',
				[ 'cuc_ip_hex' => 'D4231F79' ],
			],
			[
				'212.35.31.121/32',
				[ 0 => 'cuc_ip_hex BETWEEN \'D4231F79\' AND \'D4231F79\'' ],
			],
			[
				'::e:f:2001',
				[ 'cuc_ip_hex' => 'v6-00000000000000000000000E000F2001' ],
			],
			[
				'::e:f:2001/96',
				[ 0 => 'cuc_ip_hex BETWEEN \'v6-00000000000000000000000E00000000\'' .
					' AND \'v6-00000000000000000000000EFFFFFFFF\'' ],
			],
			[ "0.17.184.5/{$this->lowerThanLimitIPv4}", false ],
			[ "2000::/{$this->lowerThanLimitIPv6}", false ],
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser::isValidRange
	 * @dataProvider provideIsValidRange
	 */
	public function testIsValidRange( $target, $expected ) {
		$this->assertSame(
			$expected,
			SpecialCheckUser::isValidRange( $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::isValid
	 * @return array
	 */
	public function provideIsValidRange() {
		return [
			[ '212.35.31.121', true ],
			[ '212.35.31.121/32', true ],
			[ '::e:f:2001', true ],
			[ '::e:f:2001/96', true ],
			[ "0.17.184.5/{$this->lowerThanLimitIPv4}", false ],
			[ "2000::/{$this->lowerThanLimitIPv6}", false ]
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser::checkReason
	 * @dataProvider provideCheckReason
	 */
	public function testCheckReason( $config, $reason, $expected ) {
		$this->setMwGlobals( 'wgCheckUserForceSummary', $config );
		$object = $this->setUpObject();
		$object->opts->add( 'reason', $expected );
		$this->assertSame(
			$expected,
			$object->checkReason()
		);
	}

	/**
	 * Test cases for SpecialCheckUser::checkReason
	 * @return array
	 */
	public function provideCheckReason() {
		return [
			[ false, '', true ],
			[ false, 'Test Reason', true ],
			[ true, '', false ],
			[ true, 'Test Reason', true ]
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser::getTimeConds
	 * @dataProvider provideGetTimeConds
	 */
	public function testGetTimeConds( $period, $fakeTime, $expected ) {
		ConvertibleTimestamp::setFakeTime( $fakeTime );
		$object = $this->setUpObject();
		$this->assertSame(
			$expected,
			$object->getTimeConds( $period )
		);
	}

	public function provideGetTimeConds() {
		return [
			'Empty period' => [ '', '1653047635', false ],
			'Period value for all' => [ 0, '1653047635', false ],
			'Period value for 7 days' => [ 7, '1653077137', "cuc_timestamp > '20220513000000'" ],
			'Period value for 30 days' => [ 30, '1653047635', "cuc_timestamp > '20220420000000'" ],
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Specials\SpecialCheckUser::userWasBlocked
	 * @dataProvider provideUserWasBlocked
	 */
	public function testUserWasBlocked( $block ) {
		$testUser = $this->getTestUser()->getUser();
		if ( $block ) {
			$userAuthority = $this->mockRegisteredUltimateAuthority();
			$this->getServiceContainer()->getBlockUserFactory()->newBlockUser(
				$testUser,
				$userAuthority,
				'1 second'
			)->placeBlock();
		}
		$object = $this->setUpObject();
		$this->assertSame(
			$block,
			$object->userWasBlocked( $testUser->getName() )
		);
	}

	public function provideUserWasBlocked() {
		return [
			'User was previously blocked' => [ true ],
			'User never previously blocked' => [ false ]
		];
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->getServiceContainer()->getSpecialPageFactory()
			->getPage( 'CheckUser' );
		if ( $checkUserLog === null ) {
			$this->fail( 'CheckUser special page does not exist' );
		}
		$requiredRight = $checkUserLog->getRestriction();
		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}
		$rightsGivenInGroups = $this->getServiceContainer()->getGroupPermissionsLookup()
			->getGroupPermissions( $groups );
		if ( $allowed ) {
			$this->assertContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should allow it to access CheckUser.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to CheckUser.'
			);
		}
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
		if ( ( is_array( $groups ) && isset( $groups['checkuser-log'] ) ) || $groups === "checkuser-log" ) {
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
			'checkuser right only' => [ 'checkuser', true ],
		];
	}
}
