<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\DatabaseBlockStoreFactory;
use MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler;
use MediaWiki\Config\Config;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler
 */
class PerformRetroactiveAutoblockHandlerTest extends MediaWikiUnitTestCase {
	public function testPerformRetroactiveBlockForNonExistentUser() {
		// Create a DatabaseBlock mock instance that pretends that the target of the block is a non-existent user
		// (i.e. a user with ID 0).
		$block = $this->createMock( DatabaseBlock::class );
		$block->method( 'getTargetUserIdentity' )
			->willReturn( new UserIdentityValue( 0, 'Testing1234' ) );
		// Call the method under test with the mock DatabaseBlock.
		$objectUnderTest = new PerformRetroactiveAutoblockHandler(
			$this->createMock( IConnectionProvider::class ),
			$this->createMock( DatabaseBlockStoreFactory::class ),
			$this->createMock( Config::class )
		);
		$blockIds = [];
		$this->assertTrue( $objectUnderTest->onPerformRetroactiveAutoblock( $block, $blockIds ) );
		$this->assertCount(
			0, $blockIds, 'No autoblocks should be performed if the existing block target is a non-existent user'
		);
	}
}
