<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use CentralAuthTestUser;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CheckUser\Services\CheckUserMultiLockUrlLookup;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserMultiLockUrlLookup
 */
class CheckUserMultiLockUrlLookupTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
	}

	private function getObjectUnderTest(): CheckUserMultiLockUrlLookup {
		return $this->getServiceContainer()->get( 'CheckUserMultiLockUrlLookup' );
	}

	/**
	 * Creates a global account that is attached to the local wiki and is in the given global groups.
	 */
	private function getUserWithGlobalGroups( array $globalGroups ): UserIdentity {
		$testUser = $this->getMutableTestUser();
		$centralUser = CentralAuthTestUser::newFromTestUser( $testUser )->save( $this->getDb() )->getCentralUser();
		foreach ( $globalGroups as $group ) {
			$centralUser->addToGlobalGroup( $group );
		}
		return $testUser->getUserIdentity();
	}

	public function testGetMultiLockUrlWhenUserNotInConfiguredGroup(): void {
		$this->overrideConfigValue(
			'CheckUserCAMultiLock',
			[ 'centralDB' => 'centralwiki', 'groups' => [ 'steward' ] ]
		);

		$this->assertNull(
			$this->getObjectUnderTest()->getMultiLockUrl( $this->getUserWithGlobalGroups( [ 'global-sysop' ] ) ),
			'No URL should be returned when the user is not in a configured global group'
		);
	}

	public function testGetMultiLockUrlFallsBackToLocalUrl(): void {
		// Use a wiki ID that is not in $wgConf, so that no URL can be generated for the central wiki
		$this->overrideConfigValue(
			'CheckUserCAMultiLock',
			[ 'centralDB' => 'unknownwiki', 'groups' => [ 'steward' ] ]
		);

		$this->assertSame(
			SpecialPage::getTitleFor( 'MultiLock' )->getLinkURL(),
			$this->getObjectUnderTest()->getMultiLockUrl( $this->getUserWithGlobalGroups( [ 'steward' ] ) ),
			'The local Special:MultiLock URL should be used if no central wiki URL can be generated'
		);
	}

	public function testGetMultiLockUrlForCentralWiki(): void {
		$conf = new SiteConfiguration();
		$conf->settings = [
			'wgServer' => [ 'centralwiki' => 'https://central.example.org' ],
			'wgArticlePath' => [ 'centralwiki' => '/wiki/$1' ],
		];
		$conf->suffixes = [ 'wiki' ];
		$this->setMwGlobals( 'wgConf', $conf );
		$this->overrideConfigValue(
			'CheckUserCAMultiLock',
			[ 'centralDB' => 'centralwiki', 'groups' => [ 'steward', 'global-sysop' ] ]
		);

		$this->assertSame(
			'https://central.example.org/wiki/Special:MultiLock',
			$this->getObjectUnderTest()->getMultiLockUrl( $this->getUserWithGlobalGroups( [ 'global-sysop' ] ) ),
			'The URL should point to Special:MultiLock on the configured central wiki'
		);
	}
}
