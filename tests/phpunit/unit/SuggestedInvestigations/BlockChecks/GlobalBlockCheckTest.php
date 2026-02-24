<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck
 * @group CheckUser
 */
class GlobalBlockCheckTest extends MediaWikiUnitTestCase {

	private GlobalBlockLookup&MockObject $globalBlockLookup;
	private CentralIdLookup&MockObject $centralIdLookup;
	private UserIdentityLookup&MockObject $userIdentityLookup;
	private GlobalBlockCheck $globalBlockCheck;

	public function setUp(): void {
		parent::setUp();

		if ( !class_exists( GlobalBlock::class ) ) {
			$this->markTestSkipped( 'GlobalBlocking class is not found, skipping unit tests' );
		}

		$this->globalBlockLookup = $this->createMock( GlobalBlockLookup::class );
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->userIdentityLookup = $this->createMock( UserIdentityLookup::class );

		$this->globalBlockCheck = new GlobalBlockCheck(
			$this->globalBlockLookup,
			$this->centralIdLookup,
			$this->userIdentityLookup,
			true
		);
	}

	/**
	 * @dataProvider provideIndefiniteBlockCheckScenarios
	 */
	public function testIndefiniteBlockCheck(
		int|null $userCentralId,
		string|null $globalBlockExpiry,
		array $expected
	): void {
		$this->mockUserHasCentralId( $userCentralId );

		if ( $userCentralId !== null ) {
			$globalBlock = null;
			if ( $globalBlockExpiry !== null ) {
				$globalBlock = (object)[ 'gb_expiry' => $globalBlockExpiry, 'gb_id' => 1 ];
			}

			$this->globalBlockLookup->expects( $this->once() )
				->method( 'getGlobalBlockingBlock' )
				->with( null, $userCentralId )
				->willReturn( $globalBlock );
		} else {
			$this->globalBlockLookup->expects( $this->never() )
				->method( 'getGlobalBlockingBlock' );
		}

		$this->assertSame( $expected, $this->globalBlockCheck->getIndefinitelyBlockedUserIds( [ 1 ] ) );
	}

	public static function provideIndefiniteBlockCheckScenarios(): array {
		return [
			'user indefinitely globally blocked' => [
				'centralId' => 200,
				'globalBlockExpiry' => 'infinity',
				'expected' => [ 1 ],
			],
			'global block not indefinite' => [
				'centralId' => 200,
				'globalBlockExpiry' => '20300101000000',
				'expected' => [],
			],
			'user has no global block' => [
				'centralId' => 200,
				'globalBlockExpiry' => null,
				'expected' => [],
			],
			'user has no central ID' => [
				'centralId' => null,
				'globalBlockExpiry' => null,
				'expected' => [],
			],
		];
	}

	/** @dataProvider provideBlockCheckScenarios */
	public function testBlockCheck(
		string|null $globalBlockExpiry,
		array $expected
	): void {
		$this->mockUserHasCentralId( 200 );

		$globalBlock = null;
		if ( $globalBlockExpiry !== null ) {
			$globalBlock = (object)[ 'gb_expiry' => $globalBlockExpiry, 'gb_id' => 1 ];
		}

		$this->globalBlockLookup->expects( $this->once() )
			->method( 'getGlobalBlockingBlock' )
			->with( null, 200 )
			->willReturn( $globalBlock );

		$this->assertSame( $expected, $this->globalBlockCheck->getBlockedUserIds( [ 1 ] ) );
	}

	public static function provideBlockCheckScenarios(): array {
		return [
			'user indefinitely globally blocked' => [
				'globalBlockExpiry' => 'infinity',
				'expected' => [ 1 ],
			],
			'global block not indefinite' => [
				'globalBlockExpiry' => '20300101000000',
				'expected' => [ 1 ],
			],
			'user has no global block' => [
				'globalBlockExpiry' => null,
				'expected' => [],
			],
		];
	}

	public function testApplyGlobalEarlyExit(): void {
		$globalBlockLookup = $this->createNoOpMock( GlobalBlockLookup::class );

		$check = new GlobalBlockCheck(
			$globalBlockLookup,
			$this->centralIdLookup,
			$this->userIdentityLookup,
			false
		);

		$this->assertSame( [], $check->getIndefinitelyBlockedUserIds( [ 1 ] ) );
		$this->assertSame( [], $check->getBlockedUserIds( [ 1 ] ) );
	}

	public function testUserIdentityNotFound(): void {
		$this->userIdentityLookup->expects( $this->once() )
			->method( 'getUserIdentityByUserId' )
			->with( 1 )
			->willReturn( null );

		$this->centralIdLookup->expects( $this->never() )
			->method( 'centralIdFromLocalUser' );

		$this->globalBlockLookup->expects( $this->never() )
			->method( 'getGlobalBlockingBlock' );

		$this->assertSame( [], $this->globalBlockCheck->getIndefinitelyBlockedUserIds( [ 1 ] ) );
	}

	private function mockUserHasCentralId( int|null $userCentralId ): void {
		$userIdentity = new UserIdentityValue( 123, 'TestUser' );
		$this->userIdentityLookup->expects( $this->once() )
			->method( 'getUserIdentityByUserId' )
			->with( 1 )
			->willReturn( $userIdentity );

		$this->centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromLocalUser' )
			->with( $userIdentity, CentralIdLookup::AUDIENCE_RAW )
			->willReturn( $userCentralId ?? 0 );
	}
}
