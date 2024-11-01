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
	 * @dataProvider provideCanAccessTemporaryAccountIPAddressesBlocked
	 */
	public function testCanAccessTemporaryAccountIPAddressesBlocked(
		array $permissions,
		bool $acceptedAgreement
	): void {
		$block = $this->createMock( Block::class );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )
			->willReturn( new UserIdentityValue( 1, 'TestUser' ) );
		$authority->method( 'getBlock' )
			->willReturn( $block );
		$authority->method( 'isAllowed' )
			->willReturnCallback( fn ( string $permission ) => in_array( $permission, $permissions ) );

		$this->userOptionsLookup->method( 'getOption' )
			->with( $authority->getUser(), 'checkuser-temporary-account-enable' )
			->willReturn( $acceptedAgreement ? '1' : '0' );

		$permStatus = $this->checkUserPermissionsManager->canAccessTemporaryAccountIPAddresses( $authority );

		$this->assertStatusNotGood( $permStatus );
		$this->assertSame( $block, $permStatus->getBlock() );
	}

	public static function provideCanAccessTemporaryAccountIPAddressesBlocked(): iterable {
		yield 'authorized to view data without accepting agreement' => [
			[ 'checkuser-temporary-account-no-preference' ],
			false,
		];

		yield 'authorized and agreement accepted' => [
			[ 'checkuser-temporary-account' ],
			true,
		];
	}
}
