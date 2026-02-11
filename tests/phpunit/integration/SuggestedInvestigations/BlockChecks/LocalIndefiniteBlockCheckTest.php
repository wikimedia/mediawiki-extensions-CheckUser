<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\BlockChecks;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\LocalIndefiniteBlockCheck;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\BlockChecks\LocalIndefiniteBlockCheck
 * @group CheckUser
 * @group Database
 */
class LocalIndefiniteBlockCheckTest extends MediaWikiIntegrationTestCase {

	private DatabaseBlockStore $blockStore;
	private LocalIndefiniteBlockCheck $check;

	public function setUp(): void {
		parent::setUp();

		$this->blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$this->check = new LocalIndefiniteBlockCheck( $this->blockStore );
	}

	public function testGetIndefinitelyBlockedUserIds(): void {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->blockUserIndefinitely( $user );

		$this->assertSame(
			[ $user->getId() ],
			$this->check->getIndefinitelyBlockedUserIds( [ $user->getId() ] )
		);
	}

	private function blockUserIndefinitely( UserIdentity $user ): void {
		$block = $this->blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => 'infinity',
		] );
		$this->blockStore->insertBlock( $block );
	}
}
