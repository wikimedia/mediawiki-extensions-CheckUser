<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\PopulateCulComment;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCulComment
 */
class PopulateCulCommentWithoutUpdateAppliedTest extends MaintenanceBaseTestCase {

	use CheckUserCommonTraitTest;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCulComment::class;
	}

	public function testDoDBUpdatesWhenCulReasonDoesNotExist(): void {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'ipusers', 'ip', '127.0.0.1', 'test'
		);

		$this->assertTrue( $this->maintenance->doDBUpdates() );

		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'The cul_reason field does not exist which is needed for migration',
			$actualOutput
		);
	}
}
