<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use FormOptions;
use HashConfig;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
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
	 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::isValidRange
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
			'Single IPv4 address' => [ '212.35.31.121', true ],
			'Single IPv4 address notated as a /32' => [ '212.35.31.121/32', true ],
			'Single IPv6 address' => [ '::e:f:2001', true ],
			'IPv6 /96 range' => [ '::e:f:2001/96', true ],
			'Invalid IP address' => [ 'abcedf', false ]
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::isValidRange
	 */
	public function testIsValidRangeLowerThanLimit() {
		$this->testIsValidRange( "0.17.184.5/{$this->lowerThanLimitIPv4}", false );
		$this->testIsValidRange( "2000::/{$this->lowerThanLimitIPv6}", false );
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::checkReason
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
			'Empty reason with wgCheckUserForceSummary as false' => [ false, '', true ],
			'Non-empty reason with wgCheckUserForceSummary as false' => [ false, 'Test Reason', true ],
			'Empty reason with wgCheckUserForceSummary as true' => [ true, '', false ],
			'Non-empty reason with wgCheckUserForceSummary as true' => [ true, 'Test Reason', true ]
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
