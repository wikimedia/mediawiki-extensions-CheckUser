<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser;

use IDatabase;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUserLog;
use MediaWiki\User\ActorStore;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserLogTest extends MediaWikiUnitTestCase {
	private function commonVerifyInitiator( string $initiatorName, $mockReturnValue ) {
		$objectUnderTest = $this->getMockBuilder( SpecialCheckUserLog::class )
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
		$objectUnderTest->dbr = $mockDbr;
		return $objectUnderTest;
	}

	/** @dataProvider provideInitiatorNames */
	public function testVerifyInitiatorInitiatorHasActorId( $initiatorName ) {
		$objectUnderTest = $this->commonVerifyInitiator( $initiatorName, 1 );
		$this->assertSame(
			1,
			$objectUnderTest->verifyInitiator( $initiatorName ),
			'If an IP or user has an actor ID, the actor ID should be returned.'
		);
	}

	/** @dataProvider provideInitiatorNames */
	public function testVerifyInitiatorInitiatorHasNoActorId( $initiatorName ) {
		$objectUnderTest = $this->commonVerifyInitiator( $initiatorName, null );
		$this->assertSame(
			false,
			$objectUnderTest->verifyInitiator( $initiatorName ),
			'If an IP or user has no actor ID, false should be returned.'
		);
	}

	public static function provideInitiatorNames() {
		return [
			'IP' => [ '127.0.0.1' ],
			'User' => [ 'TestAccount' ]
		];
	}

	/** @dataProvider provideVerifyTargetIP */
	public function testVerifyTargetIP( $target, $expected ) {
		$this->assertArrayEquals(
			$expected,
			SpecialCheckUserLog::verifyTarget( $target ),
			true,
			false,
			'Valid IP addresses should be seen as valid targets and parsed as an IP or IP range.'
		);
	}

	public static function provideVerifyTargetIP() {
		return [
			'Single IP' => [ '124.0.0.0', [ '7C000000' ] ],
			'/24 IP range' => [ '124.0.0.0/24', [ '7C000000', '7C0000FF' ] ],
			'/16 IP range' => [ '124.0.0.0/16', [ '7C000000', '7C00FFFF' ] ],
			'Single IP notated as a /32 range' => [ '1.2.3.4/32', [ '01020304' ] ],
			'Single IPv6' => [ '::e:f:2001', [ 'v6-00000000000000000000000E000F2001' ] ],
			'/96 IPv6 range' => [ '::e:f:2001/96', [
				'v6-00000000000000000000000E00000000',
				'v6-00000000000000000000000EFFFFFFFF'
			]
			],
		];
	}
}
