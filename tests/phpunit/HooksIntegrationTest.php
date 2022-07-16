<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\Hooks;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 * @coversDefaultClass \MediaWiki\CheckUser\Hooks
 */
class HooksIntegrationTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgCheckUserActorMigrationStage' => 3,
			'wgCheckUserLogActorMigrationStage' => 3
		] );
	}

	/**
	 * @covers ::onUserMergeAccountFields
	 */
	public function testOnUserMergeAccountFields() {
		$updateFields = [];
		Hooks::onUserMergeAccountFields( $updateFields );
		$this->assertCount(
			3,
			$updateFields,
			'3 updates were added'
		);
	}
}
