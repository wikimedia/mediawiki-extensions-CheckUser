<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger
 * @group CheckUser
 */
class SuggestedInvestigationsTriggerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideRequestHeaderConfigs */
	public function testRequestHeadersCapturedIntoExtraData(
		array $configuredHeaders,
		array $requestHeaders,
		array $expectedHeaders
	): void {
		$this->setMainRequestWithHeaders( $requestHeaders );

		$mockUser = $this->createMock( User::class );
		$trigger = new SuggestedInvestigationsTrigger(
			$this->setUpMockJobQueue(
				$mockUser,
				'setemail',
				[ 'headers' => $expectedHeaders ]
			),
			new ServiceOptions(
				SuggestedInvestigationsTrigger::CONSTRUCTOR_OPTIONS,
				[
					'CheckUserSuggestedInvestigationsRequestHeaders' => $configuredHeaders,
				]
			),
			$this->newImmediateConnectionProvider()
		);

		$trigger->matchSignalsAgainstUserInJob(
			$mockUser,
			SuggestedInvestigationsSignalMatchService::EVENT_SET_EMAIL
		);
	}

	public static function provideRequestHeaderConfigs(): array {
		return [
			'all configured headers present, keys lowercased' => [
				[ 'User-Agent', 'Accept-Language' ],
				[ 'User-Agent' => 'foo bar', 'Accept-Language' => 'en,de' ],
				[ 'user-agent' => 'foo bar', 'accept-language' => 'en,de' ],
			],
			'configured header absent from request is skipped' => [
				[ 'User-Agent', 'X-Missing' ],
				[ 'User-Agent' => 'foo bar' ],
				[ 'user-agent' => 'foo bar' ],
			],
			'present but unconfigured header is not captured' => [
				[ 'User-Agent' ],
				[ 'User-Agent' => 'foo bar', 'Referer' => 'https://example.com' ],
				[ 'user-agent' => 'foo bar' ],
			],
			'configured header name in mixed/upper case is matched and lowercased' => [
				[ 'ACCEPT-LANGUAGE' ],
				[ 'Accept-Language' => 'en,de' ],
				[ 'accept-language' => 'en,de' ],
			],
			'no configured headers present in request yields empty map' => [
				[ 'X-One', 'X-Two' ],
				[ 'User-Agent' => 'foo bar' ],
				[],
			],
		];
	}

	public function testHeadersMergedWithExtraData(): void {
		$this->setMainRequestWithHeaders( [ 'User-Agent' => 'foo bar' ] );

		$revId = 123;
		$mockUser = $this->createMock( User::class );
		$trigger = new SuggestedInvestigationsTrigger(
			$this->setUpMockJobQueue(
				$mockUser,
				'successfuledit',
				[ 'revId' => $revId, 'headers' => [ 'user-agent' => 'foo bar' ] ]
			),
			new ServiceOptions(
				SuggestedInvestigationsTrigger::CONSTRUCTOR_OPTIONS,
				[
					'CheckUserSuggestedInvestigationsRequestHeaders' => [ 'User-Agent' ],
				]
			),
			$this->newImmediateConnectionProvider()
		);

		$trigger->matchSignalsAgainstUserInJob(
			$mockUser,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revId ]
		);
	}

	public function testJobIsOnlyEnqueuedAfterTransactionCommits(): void {
		// Capture the commit callback without running it, to assert the job is not
		// enqueued until the triggering transaction commits.
		$capturedCallback = null;
		$db = $this->createMock( IDatabase::class );
		$db->method( 'onTransactionCommitOrIdle' )
			->willReturnCallback( static function ( callable $callback ) use ( &$capturedCallback ) {
				$capturedCallback = $callback;
			} );
		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getPrimaryDatabase' )->willReturn( $db );

		$jobPushed = false;
		$mockJobQueueGroup = $this->createMock( JobQueueGroup::class );
		$mockJobQueueGroup->expects( $this->once() )
			->method( 'lazyPush' )
			->willReturnCallback( static function () use ( &$jobPushed ) {
				$jobPushed = true;
			} );

		$mockUser = $this->createMock( User::class );
		$trigger = new SuggestedInvestigationsTrigger(
			$mockJobQueueGroup,
			new ServiceOptions(
				SuggestedInvestigationsTrigger::CONSTRUCTOR_OPTIONS,
				[ 'CheckUserSuggestedInvestigationsRequestHeaders' => [] ]
			),
			$connectionProvider
		);

		$trigger->matchSignalsAgainstUserInJob(
			$mockUser,
			SuggestedInvestigationsSignalMatchService::EVENT_CREATE_ACCOUNT
		);

		$this->assertFalse( $jobPushed, 'Enqueue was not deferred to transaction commit' );
		$this->assertNotNull( $capturedCallback );

		// Simulate the transaction committing.
		( $capturedCallback )();
	}

	/**
	 * A helper function to create a connection provider, which immediately runs the on commit callback
	 */
	private function newImmediateConnectionProvider(): IConnectionProvider {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'onTransactionCommitOrIdle' )
			->willReturnCallback( static fn ( callable $callback ) => $callback() );
		$connectionProvider = $this->createMock( IConnectionProvider::class );
		$connectionProvider->method( 'getPrimaryDatabase' )->willReturn( $db );
		return $connectionProvider;
	}

	private function setMainRequestWithHeaders( array $headers ): void {
		$request = new FauxRequest();
		$request->setHeaders( $headers );
		RequestContext::getMain()->setRequest( $request );
	}

	private function setUpMockJobQueue(
		User $expectedUserIdentity,
		string $expectedEventType,
		array $expectedExtraData
	): JobQueueGroup {
		$mockJobQueueGroup = $this->createMock( JobQueueGroup::class );
		$mockJobQueueGroup->expects( $this->once() )
			->method( 'lazyPush' )
			->willReturnCallback( function ( $job ) use (
				$expectedUserIdentity,
				$expectedEventType,
				$expectedExtraData
			) {
				$this->assertInstanceOf( IJobSpecification::class, $job );
				$this->assertSame(
					SuggestedInvestigationsMatchSignalsAgainstUserJob::TYPE,
					$job->getType(),
					'Inserted job was not of the expected type'
				);

				$jobParams = $job->getParams();
				unset( $jobParams['requestId'] );
				$this->assertArrayEquals(
					[
						'userIdentityId' => $expectedUserIdentity->getId(),
						'userIdentityName' => $expectedUserIdentity->getName(),
						'eventType' => $expectedEventType,
						'extraData' => $expectedExtraData,
					],
					$jobParams,
					false,
					true,
					'Job parameters are not as expected'
				);
			} );

		return $mockJobQueueGroup;
	}
}
