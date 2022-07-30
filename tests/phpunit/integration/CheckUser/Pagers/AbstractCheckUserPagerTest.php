<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use FormOptions;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
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
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject() {
		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'limit', '' );
		$opts->add( 'dir', '' );
		$opts->add( 'offset', '' );
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
			$services->getUserFactory()
		);
		return TestingAccessWrapper::newFromObject( $object );
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::getIpConds
	 * @dataProvider provideGetIpConds
	 */
	public function testGetIpConds( $target, $expected ) {
		$this->assertEquals(
			$expected,
			AbstractCheckUserPager::getIpConds( $this->db, $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::getIpConds
	 * @return array
	 */
	public function provideGetIpConds() {
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

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::getIpConds
	 */
	public function testGetIpCondsLowerThanLimit() {
		// Need to not have these in a dataProvider as $this->lowerThanLimit... isn't set when
		// the data is returned.
		$this->testGetIpConds( "0.17.184.5/$this->lowerThanLimitIPv4", false );
		$this->testGetIpConds( "2000::/$this->lowerThanLimitIPv6", false );
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::isValidRange
	 * @dataProvider provideIsValidRange
	 */
	public function testIsValidRange( $target, $expected ) {
		$object = $this->setUpObject();
		$this->assertSame(
			$expected,
			$object->isValidRange( $target )
		);
	}

	/**
	 * Test cases for AbstractCheckUserPager::isValid
	 * @return array
	 */
	public function provideIsValidRange() {
		return [
			'Single IPv4 address' => [ '212.35.31.121', true ],
			'Single IPv4 address notated as a /32' => [ '212.35.31.121/32', true ],
			'Single IPv6 address' => [ '::e:f:2001', true ],
			'IPv6 /96 range' => [ '::e:f:2001/96', true ],
			'Invalid IP address' => [ 'abcedf', false ]
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::isValidRange
	 */
	public function testIsValidRangeLowerThanLimit() {
		$this->testIsValidRange( "0.17.184.5/{$this->lowerThanLimitIPv4}", false );
		$this->testIsValidRange( "2000::/{$this->lowerThanLimitIPv6}", false );
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::getDateRangeCond
	 * @dataProvider provideGetDateRangeCond
	 */
	public function testGetDateRangeCond( $period, $fakeTime, $expected ) {
		ConvertibleTimestamp::setFakeTime( $fakeTime );
		$object = $this->setUpObject();
		$object->opts->add( 'period', $period );
		if ( $expected ) {
			$condition = 'cuc_timestamp > ' . $this->db->addQuotes( $this->db->timestamp( $expected ) );
			$this->assertArrayEquals(
				[ $condition ],
				$object->getDateRangeCond( '', '' ),
				false,
				false,
				'A different time condition was generated than expected.'
			);
		} else {
			$this->assertCount(
				0,
				$object->getDateRangeCond( '', '' ),
				'Conditions were generated when they were not supposed to be.'
			);
		}
	}

	/** @return array[] */
	public function provideGetDateRangeCond(): array {
		return [
			'Empty period' => [ '', '1653047635', false ],
			'Period value for all' => [ 0, '1653047635', false ],
			'Period value for 7 days' => [ 7, '1653077137', '20220513000000' ],
			'Period value for 30 days' => [ 30, '1653047635', '20220420000000' ],
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::userWasBlocked
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
	 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager::noMatchesMessage
	 */
	public function testNoMatchesMessageNoCheckLast() {
		$object = $this->setUpObject();
		$this->assertSame(
			wfMessage( 'checkuser-nomatch' )->parseAsBlock(),
			$object->noMatchesMessage( 'test', false ),
			'The checkuser-nomatch message should have been returned.'
		);
	}
}
