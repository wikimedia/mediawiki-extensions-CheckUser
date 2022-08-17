<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use FormOptions;
use HashConfig;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
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
	}

	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		$object = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
		$testingWrapper = TestingAccessWrapper::newFromObject( $object );
		$testingWrapper->opts = new FormOptions();
		return $testingWrapper;
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

	/** @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::doesWrites */
	public function testDoesWrites() {
		$this->assertTrue(
			$this->setUpObject()->doesWrites(),
			'Special:CheckUser writes to the cu_log table so it does writes.'
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser::getPager
	 * @dataProvider provideGetPager
	 */
	public function testGetPager( $checkType, $userIdentity, $xfor = null ) {
		$object = $this->setUpObject();
		$object->opts->add( 'limit', 0 );
		$object->opts->add( 'reason', '' );
		$object->opts->add( 'period', 0 );
		if ( $checkType === SpecialCheckUser::SUBTYPE_GET_IPS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetIPsPager,
				'The Get IPs checktype should return the Get IPs pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_EDITS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetEditsPager,
				'The Get edits checktype should return the Get edits pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_USERS ) {
			$this->assertTrue(
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor )
				instanceof CheckUserGetUsersPager,
				'The Get users checktype should return the Get users pager.'
			);
		} else {
			$this->assertNull(
				$object->getPager( $checkType, $userIdentity, 'untested' ),
				'An unrecognised check type should return no pager.'
			);
		}
	}

	public function provideGetPager() {
		return [
			'Get IPs checktype' =>
				[ SpecialCheckUser::SUBTYPE_GET_IPS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get edits checktype with a registered user' =>
				[ SpecialCheckUser::SUBTYPE_GET_EDITS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get edits checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_EDITS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get edits checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_EDITS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'Get users checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get users checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'An invalid checktype' => [ '', UserIdentityValue::newRegistered( 1, 'test' ) ],
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
