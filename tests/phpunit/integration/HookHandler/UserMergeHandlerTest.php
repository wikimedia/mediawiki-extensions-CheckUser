<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\UserMergeHandler;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\UserMergeHandler
 */
class UserMergeHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'UserMerge' );
	}

	public function testOnUserMergeAccountFields() {
		// @todo Test that the array items of $updateFields are as expected?
		$updateFields = [];
		$expectedCount = 3;
		$objectUnderTest = new UserMergeHandler();
		$objectUnderTest->onUserMergeAccountFields( $updateFields );
		$this->assertCount(
			$expectedCount,
			$updateFields,
			'3 updates were added'
		);
	}
}
