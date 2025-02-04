<?php
namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserPermissionManager
 */
class CheckUserPermissionManagerTest extends MediaWikiUnitTestCase {
	private UserOptionsLookup $userOptionsLookup;

	private CheckUserPermissionManager $checkUserPermissionsManager;

	protected function setUp(): void {
		parent::setUp();

		$this->userOptionsLookup = $this->createMock( UserOptionsLookup::class );

		$this->checkUserPermissionsManager = new CheckUserPermissionManager( $this->userOptionsLookup );
	}

	/**
	 * @dataProvider provideCanAccessTemporaryAccountIPAddresses
	 */
	public function testCanAccessTemporaryAccountIPAddresses(
		Authority $authority,
		bool $acceptedAgreement,
		CheckUserPermissionStatus $expectedStatus
	): void {
		$this->userOptionsLookup->method( 'getOption' )
			->with( $authority->getUser(), 'checkuser-temporary-account-enable' )
			->willReturn( $acceptedAgreement ? '1' : '0' );

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses( $authority );

		$this->assertEquals( $expectedStatus, $permStatus );
	}

	public static function provideCanAccessTemporaryAccountIPAddresses(): iterable {
		$actor = new UserIdentityValue( 1, 'TestUser' );

		yield 'missing permissions' => [
			new SimpleAuthority( $actor, [] ),
			true,
			CheckUserPermissionStatus::newPermissionError( 'checkuser-temporary-account' )
		];

		yield 'authorized but agreement not accepted' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account' ] ),
			false,
			CheckUserPermissionStatus::newFatal( 'checkuser-tempaccount-reveal-ip-permission-error-description' )
		];

		yield 'authorized to view data without accepting agreement' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account-no-preference' ] ),
			false,
			CheckUserPermissionStatus::newGood()
		];

		yield 'authorized and agreement accepted' => [
			new SimpleAuthority( $actor, [ 'checkuser-temporary-account' ] ),
			true,
			CheckUserPermissionStatus::newGood()
		];
	}

	/**
	 * @dataProvider provideCanAccessTemporaryAccountIPAddressesWhenBlocked
	 */
	public function testCanAccessTemporaryAccountIPAddressesWhenBlocked(
		bool $isSitewideBlock
	): void {
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( $isSitewideBlock );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( new UserIdentityValue( 1, 'TestUser' ) );
		$authority->method( 'getBlock' )
			->willReturn( $block );
		$authority->method( 'isAllowed' )
			->willReturn( true );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $authority->getUser(), 'checkuser-temporary-account-enable' )
			->willReturn( '1' );

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses( $authority );

		if ( $isSitewideBlock ) {
			$this->assertStatusNotGood( $permStatus );
			$this->assertSame( $block, $permStatus->getBlock() );
		} else {
			$this->assertStatusGood( $permStatus );
			$this->assertNull( $permStatus->getBlock() );
		}
	}

	public static function provideCanAccessTemporaryAccountIPAddressesWhenBlocked() {
		return [
			'user is sitewide blocked' => [ true ],
			'user is not sitewide blocked' => [ false ],
		];
	}
}
