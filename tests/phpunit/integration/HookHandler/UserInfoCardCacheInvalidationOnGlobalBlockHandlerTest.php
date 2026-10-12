<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalBlockHandler;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Extension\GlobalBlocking\GlobalBlock;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\UserInfoCardCacheInvalidationOnGlobalBlockHandler
 * @group CheckUser
 */
class UserInfoCardCacheInvalidationOnGlobalBlockHandlerTest extends MediaWikiIntegrationTestCase {

	private UserInfoCardBlockStatusCache $blockStatusCache;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
		$this->blockStatusCache = $this->createMock( UserInfoCardBlockStatusCache::class );
	}

	private function getHandler(): UserInfoCardCacheInvalidationOnGlobalBlockHandler {
		return new UserInfoCardCacheInvalidationOnGlobalBlockHandler(
			$this->blockStatusCache,
		);
	}

	public function testOnGlobalBlockingGlobalBlockAuditForUserBlock(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobalBlock' )
			->with( 'TestUser' );

		$this->getHandler()->onGlobalBlockingGlobalBlockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalBlockAuditForNonRegisteredTarget(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, '127.0.0.1' ) );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateGlobalBlock' );

		$this->getHandler()->onGlobalBlockingGlobalBlockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalUnblockAuditForUserBlock(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobalBlock' )
			->with( 'TestUser' );

		$this->getHandler()->onGlobalBlockingGlobalUnblockAudit( $globalBlock );
	}

	public function testOnGlobalBlockingGlobalUnblockAuditForNonRegisteredTarget(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, '127.0.0.1' ) );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateGlobalBlock' );

		$this->getHandler()->onGlobalBlockingGlobalUnblockAudit( $globalBlock );
	}

	/** @dataProvider provideLocalStatusChanges */
	public function testOnGlobalBlockingGlobalBlockLocalStatusChangeAuditForUserBlock(
		bool $isLocallyDisabled,
		string|false $wikiId
	): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 42, 'TestUser' ) );

		$this->blockStatusCache->expects( $this->once() )
			->method( 'invalidateGlobalBlock' )
			->with( 'TestUser' );

		$this->getHandler()->onGlobalBlockingGlobalBlockLocalStatusChangeAudit(
			$globalBlock,
			$isLocallyDisabled,
			$wikiId
		);
	}

	public static function provideLocalStatusChanges(): array {
		return [
			'Locally disabled on the local wiki' => [ true, false ],
			'Locally enabled on the local wiki' => [ false, false ],
			'Locally disabled on another wiki' => [ true, 'otherwiki' ],
		];
	}

	public function testOnGlobalBlockingGlobalBlockLocalStatusChangeAuditForNonRegisteredTarget(): void {
		$globalBlock = $this->createMock( GlobalBlock::class );
		$globalBlock->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, '127.0.0.1' ) );

		$this->blockStatusCache->expects( $this->never() )
			->method( 'invalidateGlobalBlock' );

		$this->getHandler()->onGlobalBlockingGlobalBlockLocalStatusChangeAudit( $globalBlock, true, false );
	}
}
