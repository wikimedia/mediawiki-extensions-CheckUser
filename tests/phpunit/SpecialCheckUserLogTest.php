<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWikiIntegrationTestCase;
use SpecialCheckUserLog;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for SpecialCheckUserLog class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers SpecialCheckUserLog
 */
class SpecialCheckUserLogTest extends MediaWikiIntegrationTestCase {
	public function testGetPerformerSearchCondsForUser() {
		$user = $this->getTestUser()->getUser();
		$objectUnderTest = $this->getMockBuilder( SpecialCheckUserLog::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->target = $user->getName();
		$this->assertArrayEquals(
			[ 'cul_user' => $user->getId() ],
			$objectUnderTest->getPerformerSearchConds(),
			false,
			true,
			'The performer search conditions should search for the user ID in the cul_user column ' .
			'searching for a user with an ID.'
		);
	}

	/** @dataProvider provideInitiatorNameWithNoID */
	public function testGetPerformerSearchCondsForIPOrNonExistentUser( $initiatorName ) {
		$objectUnderTest = $this->getMockBuilder( SpecialCheckUserLog::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->target = $initiatorName;
		$this->assertArrayEquals(
			[ 'cul_user_text' => $initiatorName ],
			$objectUnderTest->getPerformerSearchConds(),
			false,
			true,
			'The performer search conditions should search for the user name in the cul_user_text column when ' .
			'searching for checks performed by an IP address or user with no ID in the user table.'
		);
	}

	public static function provideInitiatorNameWithNoID() {
		return [
			'Non-existent user' => [ 'Non-existent user 1234245345234234234234' ],
			'IP address' => [ '127.0.0.1' ],
		];
	}
}
