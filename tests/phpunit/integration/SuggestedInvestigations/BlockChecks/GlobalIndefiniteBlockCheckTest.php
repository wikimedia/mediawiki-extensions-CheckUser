<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\BlockChecks;

use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\GlobalIndefiniteBlockCheck;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLocalStatusManager;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockManager;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\GlobalIndefiniteBlockCheck
 * @group CheckUser
 * @group Database
 */
class GlobalIndefiniteBlockCheckTest extends MediaWikiIntegrationTestCase {

	/** @var UserIdentity[] */
	private static array $testUsers;

	private GlobalIndefiniteBlockCheck $check;
	private GlobalBlockManager $globalBlockManager;
	private GlobalBlockLocalStatusManager $globalBlockLocalStatusManager;
	private Authority $adminUser;

	public function addDBDataOnce(): void {
		self::$testUsers = [
			'blocked' => $this->getMutableTestUser()->getUserIdentity(),
			'unblocked' => $this->getMutableTestUser()->getUserIdentity(),
			'locallyDisabled' => $this->getMutableTestUser()->getUserIdentity(),
			'temporary' => $this->getMutableTestUser()->getUserIdentity(),
		];
	}

	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$globalBlockingServices = GlobalBlockingServices::wrap( $this->getServiceContainer() );
		$this->globalBlockManager = $globalBlockingServices->getGlobalBlockManager();
		$this->globalBlockLocalStatusManager = $globalBlockingServices->getGlobalBlockLocalStatusManager();
		$this->adminUser = $this->getTestSysop()->getAuthority();

		$this->check = new GlobalIndefiniteBlockCheck(
			$globalBlockingServices->getGlobalBlockLookup(),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			true
		);
	}

	public function testIndefinitelyGloballyBlockedUserIsReturned(): void {
		$blockedUser = self::$testUsers['blocked'];
		$this->globallyBlockIndefinitely( $blockedUser );

		$this->assertSame(
			[ $blockedUser->getId() ],
			$this->check->getIndefinitelyBlockedUserIds( [ $blockedUser->getId() ] )
		);
	}

	public function testLocallyDisabledGlobalBlockIsNotReturned(): void {
		$user = self::$testUsers['locallyDisabled'];
		$this->globallyBlockIndefinitely( $user );
		$this->globalBlockLocalStatusManager->locallyDisableBlock(
			$user->getName(),
			'local user block disabled',
			$this->adminUser->getUser()
		);

		$this->assertSame(
			[],
			$this->check->getIndefinitelyBlockedUserIds( [ $user->getId() ] )
		);
	}

	public function testTemporaryGlobalBlockIsNotReturned(): void {
		$user = self::$testUsers['temporary'];
		$this->globalBlockManager->block(
			$user->getName(),
			'temporary global block',
			'20300101000000',
			$this->adminUser
		);

		$this->assertSame(
			[],
			$this->check->getIndefinitelyBlockedUserIds( [ $user->getId() ] )
		);
	}

	public function testMixOfBlockedUnblockedAndLocallyDisabledUsers(): void {
		$blockedUser = self::$testUsers['blocked'];
		$unblockedUser = self::$testUsers['unblocked'];
		$locallyWhitelistedUser = self::$testUsers['locallyDisabled'];
		$temporarilyBlockedUser = self::$testUsers['temporary'];

		$this->globallyBlockIndefinitely( $blockedUser );
		$this->globallyBlockIndefinitely( $locallyWhitelistedUser );
		$this->globalBlockLocalStatusManager->locallyDisableBlock(
			$locallyWhitelistedUser->getName(),
			'local user block disabled',
			$this->adminUser->getUser()
		);
		$this->globalBlockManager->block(
			$temporarilyBlockedUser->getName(),
			'temporary global block',
			'20300101000000',
			$this->adminUser
		);

		$this->assertSame(
			[ $blockedUser->getId() ],
			$this->check->getIndefinitelyBlockedUserIds( [
				$blockedUser->getId(),
				$unblockedUser->getId(),
				$locallyWhitelistedUser->getId(),
				$temporarilyBlockedUser->getId(),
			] )
		);
	}

	private function globallyBlockIndefinitely( UserIdentity $user ): void {
		$this->globalBlockManager->block(
			$user->getName(),
			'global indefinite block',
			'infinity',
			$this->adminUser
		);
	}
}
