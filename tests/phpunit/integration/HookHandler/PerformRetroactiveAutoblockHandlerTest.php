<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use RecentChange;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\PerformRetroactiveAutoblockHandler
 */
class PerformRetroactiveAutoblockHandlerTest extends MediaWikiIntegrationTestCase implements CheckUserQueryInterface {

	use TempUserTestTrait;
	use CheckUserCommonTraitTest;

	/**
	 * @dataProvider provideOnPerformRetroactiveAutoblock
	 */
	public function testOnPerformRetroactiveAutoblock( array $tablesWithData, bool $shouldAutoblock ) {
		$target = $this->getMutableTestUser()->getUserIdentity();
		// Set the request IP, which is the IP that should be autoblocked if an autoblock is applied.
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		// Insert the specified test data
		if ( in_array( self::CHANGES_TABLE, $tablesWithData ) ) {
			// Insert a testing edit into cu_changes
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[ 'rc_user' => $target->getId(), 'rc_user_text' => $target->getName() ],
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		if ( in_array( self::PRIVATE_LOG_EVENT_TABLE, $tablesWithData ) ) {
			// Insert a RecentChanges event for a log entry that has no associated log ID (and therefore gets saved to
			// cu_private_event).
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[
					'rc_type' => RC_LOG, 'rc_log_type' => '',
					'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
				]
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		if ( in_array( self::LOG_EVENT_TABLE, $tablesWithData ) ) {
			// Insert a RecentChanges event for a log entry that has a associated log ID (and therefore causes an
			// insert into cu_log_event).
			$logId = $this->newLogEntry();
			$rc = new RecentChange;
			$rc->setAttribs( array_merge(
				self::getDefaultRecentChangeAttribs(),
				[
					'rc_type' => RC_LOG, 'rc_logid' => $logId,
					'rc_user' => $target->getId(), 'rc_user_text' => $target->getName(),
				]
			) );
			( new Hooks() )->updateCheckUserData( $rc );
		}
		// Block the target with autoblocking enabled. This should call the method under test.
		// We cannot call the hook handler directly, as the method will not work unless 'enableAutoblock'
		// is set. Setting 'enableAutoblock' causes the method under test to be called. Therefore,
		// calling the method under test directly would cause it to be run twice (which might cause unintended
		// consequences).
		$block = new DatabaseBlock( [ 'enableAutoblock' => true ] );
		$block->setTarget( $target );
		$block->setBlocker( $this->getTestSysop()->getUserIdentity() );
		$blockResult = $this->getServiceContainer()->getDatabaseBlockStore()->insertBlock( $block );
		$this->assertIsArray( $blockResult, 'The block on the target could not be performed' );
		// Get a block associated with the IP 127.0.0.2, if any exists.
		$blockManager = $this->getServiceContainer()->getBlockManager();
		$ipBlock = $blockManager->getIpBlock( '127.0.0.2', false );
		if ( $shouldAutoblock ) {
			$this->assertNotNull( $ipBlock, 'One autoblock should have been placed on the IP.' );
			$this->assertCount( 1, $blockResult['autoIds'], 'One autoblock should have been placed' );
			$this->assertSame( $ipBlock->getId(), $blockResult['autoIds'][0], 'The autoblock ID was not as expected' );
		} else {
			$this->assertNull( $ipBlock, 'No autoblock should have been placed on the IP.' );
			$this->assertCount( 0, $blockResult['autoIds'], 'No autoblocks should have been placed.' );
		}
	}

	public static function provideOnPerformRetroactiveAutoblock() {
		return [
			'Account as the target of the block and CheckUser data exists for the account' => [
				// Which CheckUser result tables have data for the target of the block
				[ self::CHANGES_TABLE, self::LOG_EVENT_TABLE, self::PRIVATE_LOG_EVENT_TABLE ],
				// Whether an autoblock should be performed.
				true,
			],
			'Account as the target of the block and target has only log related CheckUser data' => [
				[ self::LOG_EVENT_TABLE ], true,
			],
			'Account as the target of the block and target has only edit related CheckUser data' => [
				[ self::CHANGES_TABLE ], true,
			],
			'Account as the target of the block and target has no CheckUser data' => [ [], false ],
		];
	}
}
