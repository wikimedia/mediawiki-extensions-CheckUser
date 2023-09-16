<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use IDatabase;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserLogPager
 */
class CheckUserLogPagerTest extends MediaWikiUnitTestCase {
	private function commonGetPerformerSearchConds( string $initiatorName, $mockReturnValue ) {
		$objectUnderTest = $this->getMockBuilder( CheckUserLogPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$mockActorStore = $this->createMock( ActorStore::class );
		$mockDbr = $this->createMock( IDatabase::class );
		$mockActorStore->expects( $this->once() )
			->method( 'findActorIdByName' )
			->with( $initiatorName, $mockDbr )
			->willReturn( $mockReturnValue );
		$objectUnderTest->actorStore = $mockActorStore;
		$objectUnderTest->mDb = $mockDbr;
		return $objectUnderTest;
	}

	/** @dataProvider provideInitiatorNames */
	public function testGetPerformerSearchCondsHasActorId( $initiatorName ) {
		$objectUnderTest = $this->commonGetPerformerSearchConds( $initiatorName, 1 );
		$this->assertArrayEquals(
			[ 'cul_actor' => 1 ],
			$objectUnderTest->getPerformerSearchConds( $initiatorName ),
			false,
			true,
			'If an IP or user has an actor ID, the actor ID should be returned.'
		);
	}

	/** @dataProvider provideInitiatorNames */
	public function testGetPerformerSearchCondsHasNoActorId( $initiatorName ) {
		$objectUnderTest = $this->commonGetPerformerSearchConds( $initiatorName, null );
		$this->assertSame(
			null,
			$objectUnderTest->getPerformerSearchConds( $initiatorName ),
			'If an IP or user has no actor ID, null should be returned.'
		);
	}

	public static function provideInitiatorNames() {
		return [
			'IP' => [ '127.0.0.1' ],
			'User' => [ 'TestAccount' ]
		];
	}
}
