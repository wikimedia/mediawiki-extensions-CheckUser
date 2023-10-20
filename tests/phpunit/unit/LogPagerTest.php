<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\LogPager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use User;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\LogPager
 */
class LogPagerTest extends MediaWikiUnitTestCase {
	public function testGetPerformerSearchCondsForExistingUser() {
		$userMock = $this->createMock( User::class );
		$userMock->method( 'getName' )
			->willReturn( 'Test' );
		$userMock->method( 'getId' )
			->willReturn( 1 );
		$userFactoryMock = $this->createMock( UserFactory::class );
		$userFactoryMock->method( 'newFromName' )
			->with( $userMock->getName() )
			->willReturn( $userMock );
		$objectUnderTest = $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->userFactory = $userFactoryMock;
		$this->assertArrayEquals(
			[ 'cul_user' => $userMock->getId() ],
			$objectUnderTest->getPerformerSearchConds( $userMock->getName() ),
			false,
			true,
			'The performer search conditions should search for the user ID in the cul_user column ' .
			'searching for an existing user (user with non-zero ID).'
		);
	}

	/** @dataProvider provideInitiatorNameWithNoID */
	public function testGetPerformerSearchCondsForIPOrNonExistentUser( $initiatorName ) {
		$userFactoryMock = $this->createMock( UserFactory::class );
		$userFactoryMock->method( 'newFromName' )
			->with( $initiatorName )
			->willReturn( null );
		$userNameUtilsMock = $this->createMock( UserNameUtils::class );
		$userNameUtilsMock->method( 'getCanonical' )
			->with( $initiatorName )
			->willReturn( $initiatorName );
		$objectUnderTest = $this->getMockBuilder( LogPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$objectUnderTest->userFactory = $userFactoryMock;
		$objectUnderTest->userNameUtils = $userNameUtilsMock;
		$this->assertArrayEquals(
			[ 'cul_user_text' => $initiatorName ],
			$objectUnderTest->getPerformerSearchConds( $initiatorName ),
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
