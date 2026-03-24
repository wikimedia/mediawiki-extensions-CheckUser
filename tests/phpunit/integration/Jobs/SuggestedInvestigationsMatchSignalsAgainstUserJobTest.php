<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Jobs;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob
 * @group CheckUser
 */
class SuggestedInvestigationsMatchSignalsAgainstUserJobTest extends MediaWikiIntegrationTestCase {

	/**
	 * Test that when session data is provided and no persistent session is
	 * active (the async job runner case), importScopedSession is called and
	 * the job completes successfully.
	 */
	public function testExecutionWithSessionImport() {
		$userIdentity = new UserIdentityValue( 1, 'TestUser' );
		$sessionData = RequestContext::getMain()->exportSession();
		$extraData = [ 'session' => $sessionData ];

		$spec = SuggestedInvestigationsMatchSignalsAgainstUserJob::newSpec(
			$userIdentity, 'test-event-type', $extraData
		);

		$mockService = $this->createMock( SuggestedInvestigationsSignalMatchService::class );
		$mockService->expects( $this->once() )
			->method( 'matchSignalsAgainstUser' );

		$job = new SuggestedInvestigationsMatchSignalsAgainstUserJob(
			$spec->getParams(),
			$mockService
		);

		// In test/CLI context there is no persistent session,
		// so shouldImportSession() returns true and importScopedSession runs.
		$this->assertTrue( $job->run() );

		// Trigger teardown callbacks to exercise ScopedCallback::consume.
		$job->teardown( StatusValue::newGood() );
	}
}
