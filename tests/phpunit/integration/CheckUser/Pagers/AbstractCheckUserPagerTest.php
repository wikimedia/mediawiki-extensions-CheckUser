<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Html\FormOptions;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for AbstractCheckUserPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager
 */
class AbstractCheckUserPagerTest extends MediaWikiIntegrationTestCase {

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
				'actor',
				'revision',
				'ip_changes',
				'text',
				'archive',
				'recentchanges',
				'logging',
				'page_props',
				'cu_changes',
				'cu_log',
			]
		);

		$this->setMwGlobals( [
			'wgCheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			]
		] );

		$this->lowerThanLimitIPv4 = 15;
		$this->lowerThanLimitIPv6 = 18;
	}

	/**
	 * @param array $params
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject( $params = [] ) {
		$opts = new FormOptions();
		$opts->add( 'reason', $params['reason'] ?? '' );
		$opts->add( 'period', $params['period'] ?? 0 );
		$opts->add( 'limit', $params['limit'] ?? 0 );
		$opts->add( 'dir', $params['dir'] ?? '' );
		$opts->add( 'offset', $params['offset'] ?? '' );
		$services = $this->getServiceContainer();
		$object = new DeAbstractedCheckUserPagerTest(
			$opts,
			UserIdentityValue::newAnonymous( '1.2.3.4' ),
			'userips',
			$services->getService( 'CheckUserTokenQueryManager' ),
			$services->getUserGroupManager(),
			$services->getCentralIdLookup(),
			$services->getDBLoadBalancer(),
			$services->getSpecialPageFactory(),
			$services->getUserIdentityLookup(),
			$services->getActorMigration(),
			$services->getService( 'CheckUserLogService' ),
			$services->getUserFactory(),
			$services->getService( 'CheckUserUnionSelectQueryBuilderFactory' )
		);
		return TestingAccessWrapper::newFromObject( $object );
	}

	/** @dataProvider provideGetIpConds */
	public function testGetIpConds( $target, $expected ) {
		$this->assertEquals(
			$expected,
			AbstractCheckUserPager::getIpConds( $this->db, $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::getIpConds
	 */
	public static function provideGetIpConds() {
		return [
			'Single IPv4 address' => [
				'212.35.31.121',
				[ 'cuc_ip_hex' => 'D4231F79' ],
			],
			'Single IPv4 address notated as a /32' => [
				'212.35.31.121/32',
				[ 0 => 'cuc_ip_hex BETWEEN \'D4231F79\' AND \'D4231F79\'' ],
			],
			'Single IPv6 address' => [
				'::e:f:2001',
				[ 'cuc_ip_hex' => 'v6-00000000000000000000000E000F2001' ],
			],
			'IPv6 /96 range' => [
				'::e:f:2001/96',
				[ 0 => 'cuc_ip_hex BETWEEN \'v6-00000000000000000000000E00000000\'' .
					' AND \'v6-00000000000000000000000EFFFFFFFF\'' ],
			],
			'Invalid IP address' => [ 'abcedf', false ]
		];
	}

	public function testGetIpCondsLowerThanLimit() {
		// Need to not have these in a dataProvider as $this->lowerThanLimit... isn't set when
		// the data is returned.
		$this->testGetIpConds( "0.17.184.5/$this->lowerThanLimitIPv4", false );
		$this->testGetIpConds( "2000::/$this->lowerThanLimitIPv6", false );
	}

	/** @dataProvider provideIsValidRange */
	public function testIsValidRange( $target, $expected ) {
		$object = $this->setUpObject();
		$this->assertSame(
			$expected,
			$object->isValidRange( $target )
		);
	}

	/**
	 * Test cases for AbstractCheckUserPager::isValid
	 */
	public static function provideIsValidRange() {
		return [
			'Single IPv4 address' => [ '212.35.31.121', true ],
			'Single IPv4 address notated as a /32' => [ '212.35.31.121/32', true ],
			'Single IPv6 address' => [ '::e:f:2001', true ],
			'IPv6 /96 range' => [ '::e:f:2001/96', true ],
			'Invalid IP address' => [ 'abcedf', false ]
		];
	}

	public function testIsValidRangeLowerThanLimit() {
		$this->testIsValidRange( "0.17.184.5/{$this->lowerThanLimitIPv4}", false );
		$this->testIsValidRange( "2000::/{$this->lowerThanLimitIPv6}", false );
	}

	/** @dataProvider provideGetPeriodCondition */
	public function testGetPeriodCondition( $period, $fakeTime, $expected ) {
		ConvertibleTimestamp::setFakeTime( $fakeTime );
		$object = $this->setUpObject( [ 'period' => $period ] );
		if ( $expected ) {
			$expected = $this->db->buildComparison( '>=',
				[ 'cuc_timestamp' => $this->db->timestamp( $expected ) ]
			);
			$this->assertArrayEquals(
				[ $expected ],
				$object->getPeriodCondition(),
				false,
				false,
				'A different time condition was generated than expected.'
			);
		} else {
			$this->assertCount(
				0,
				$object->getPeriodCondition(),
				'Conditions were generated when they were not supposed to be.'
			);
		}
	}

	public static function provideGetPeriodCondition(): array {
		return [
			'Empty period' => [ '', '1653047635', false ],
			'Period value for all' => [ 0, '1653047635', false ],
			'Period value for 7 days' => [ 7, '1653077137', '20220513000000' ],
			'Period value for 30 days' => [ 30, '1653047635', '20220420000000' ],
		];
	}

	/** @dataProvider provideUserWasBlocked */
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

	public static function provideUserWasBlocked() {
		return [
			'User was previously blocked' => [ true ],
			'User never previously blocked' => [ false ]
		];
	}

	public function testGetEmptyBodyNoCheckLast() {
		$object = $this->setUpObject();
		$object->target = UserIdentityValue::newRegistered( 1, 'test' );
		$object->xfor = false;
		$this->assertSame(
			wfMessage( 'checkuser-nomatch' )->parseAsBlock() . "\n",
			$object->getEmptyBody(),
			'The checkuser-nomatch message should have been returned.'
		);
	}

	/** @dataProvider provideTestFormOptionsLimitValue */
	public function testFormOptionsLimitValue( $formSubmittedLimit, $maximumLimit, $expectedLimit ) {
		$this->setMwGlobals( 'wgCheckUserMaximumRowCount', $maximumLimit );
		$object = $this->setUpObject( [ 'limit' => $formSubmittedLimit ] );
		$this->assertSame(
			$expectedLimit,
			$object->mLimit,
			'The limit used for running the check was not the expected value given the user defined and maximum limit.'
		);
	}

	public static function provideTestFormOptionsLimitValue() {
		return [
			'Empty limit' => [ 0, 5000, 5000 ],
			'Limit under maximum limit' => [ 200, 5000, 200 ],
			'Limit over maximum limit' => [ 500, 200, 200 ],
		];
	}

	/** @dataProvider provideGetCheckUserHelperFieldset */
	public function testGetCheckUserHelperFieldset(
		$collapseByDefaultConfigValue, $shouldBeByDefaultCollapsed, $resultRowCount
	) {
		$this->setMwGlobals( 'wgCheckUserCollapseCheckUserHelperByDefault', $collapseByDefaultConfigValue );
		$object = $this->setUpObject();
		$object->mResult = $this->createMock( IResultWrapper::class );
		$object->mResult->method( 'numRows' )->willReturn( $resultRowCount );
		$fieldset = TestingAccessWrapper::newFromObject( $object->getCheckUserHelperFieldset() );
		$this->assertSame(
			'mw-checkuser-helper-fieldset',
			$fieldset->outerClass,
			'CheckUser fieldset should have surrounding class.'
		);
		$this->assertSame(
			wfMessage( 'checkuser-helper-label' )->text(),
			$fieldset->mWrapperLegend,
			'Wrapper legend text is incorrect.'
		);
		$this->assertFalse(
			$fieldset->mShowSubmit,
			'The fieldset should not show a submit button.'
		);
		$this->assertTrue(
			$fieldset->mCollapsible,
			'The fieldset should be collapsable.'
		);
		$this->assertSame(
			$shouldBeByDefaultCollapsed,
			$fieldset->mCollapsed,
			'The default collapsed state for the fieldset is not correct.'
		);
	}

	public static function provideGetCheckUserHelperFieldset() {
		return [
			'wgCheckUserCollapseCheckUserHelperByDefault set to true' => [
				true, true, 1
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to false' => [
				false, false, 2
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer less than the row count' => [
				3, true, 5
			],
			'wgCheckUserCollapseCheckUserHelperByDefault set to an integer greater than the row count' => [
				10, false, 3
			]
		];
	}

	public function testGetCheckUserHelperFieldsetWhenNoResults() {
		$object = $this->setUpObject();
		$object->mResult = $this->createMock( IResultWrapper::class );
		$object->mResult->method( 'numRows' )->willReturn( 0 );
		$this->assertNull(
			$object->getCheckUserHelperFieldset(),
			'The fieldset should not be shown if there are no results.'
		);
	}
}
