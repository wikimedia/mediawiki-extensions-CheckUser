<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use HashConfig;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog;
use MediaWiki\CheckUser\Tests\CheckUserIntegrationTestCaseTest;

/**
 * Test class for SpecialCheckUserLog class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog
 */
class SpecialCheckUserLogTest extends CheckUserIntegrationTestCaseTest {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_log',
			]
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog::verifyTarget
	 */
	public function testVerifyTargetUser() {
		// Existing user
		$testUser = $this->getTestUser()->getUser();
		$this->assertTrue( $testUser->getUser()->isRegistered() );
		$this->assertSame(
			$testUser->getId(),
			SpecialCheckUserLog::verifyTarget( $testUser->getName() ),
			'For an existing user it\'s ID should be returned.'
		);

		// Non-existent user with a valid username
		$testUser = $this->getNonExistentTestUser();
		$this->assertFalse(
			SpecialCheckUserLog::verifyTarget( $testUser->getName() ),
			'Non-existent users should not be a valid target.'
		);

		// Invalid username
		$this->assertFalse(
			SpecialCheckUserLog::verifyTarget( '/' ),
			'Invalid usernames should not be a valid target.'
		);
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->getServiceContainer()->getSpecialPageFactory()
			->getPage( 'CheckUserLog' );
		if ( $checkUserLog === null ) {
			$this->fail( 'CheckUserLog special page does not exist' );
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
				'Groups/rights given to the test user should allow it to access the CheckUserLog.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to the CheckUserLog.'
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
			'checkuser-log right only' => [ 'checkuser-log', true ],
		];
	}
}
