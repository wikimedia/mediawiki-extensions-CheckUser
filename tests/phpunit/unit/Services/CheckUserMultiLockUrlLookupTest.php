<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\CheckUser\Services\CheckUserMultiLockUrlLookup;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserMultiLockUrlLookup
 */
class CheckUserMultiLockUrlLookupTest extends MediaWikiUnitTestCase {

	private function getObjectUnderTest(
		array|false $checkUserCAMultiLock,
		bool $centralAuthLoaded
	): CheckUserMultiLockUrlLookup {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->with( 'CentralAuth' )
			->willReturn( $centralAuthLoaded );

		return new CheckUserMultiLockUrlLookup(
			new ServiceOptions(
				CheckUserMultiLockUrlLookup::CONSTRUCTOR_OPTIONS,
				[ 'CheckUserCAMultiLock' => $checkUserCAMultiLock ]
			),
			$extensionRegistry
		);
	}

	public function testGetMultiLockUrlWhenConfigDisabled(): void {
		$this->assertNull(
			$this->getObjectUnderTest( false, true )
				->getMultiLockUrl( UserIdentityValue::newRegistered( 1, 'TestUser' ) ),
			'No URL should be returned when $wgCheckUserCAMultiLock is false'
		);
	}

	public function testGetMultiLockUrlThrowsWhenCentralAuthNotLoaded(): void {
		$this->expectException( ConfigException::class );
		$this->getObjectUnderTest( [ 'centralDB' => 'metawiki', 'groups' => [ 'steward' ] ], false )
			->getMultiLockUrl( UserIdentityValue::newRegistered( 1, 'TestUser' ) );
	}
}
