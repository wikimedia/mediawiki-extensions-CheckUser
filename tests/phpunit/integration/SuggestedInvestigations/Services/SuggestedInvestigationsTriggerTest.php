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
	) {
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
			)
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

	public function testHeadersMergedWithExtraData() {
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
			)
		);

		$trigger->matchSignalsAgainstUserInJob(
			$mockUser,
			SuggestedInvestigationsSignalMatchService::EVENT_SUCCESSFUL_EDIT,
			[ 'revId' => $revId ]
		);
	}

	private function setMainRequestWithHeaders( array $headers ): void {
		$request = new FauxRequest();
		$request->setHeaders( $headers );
		RequestContext::getMain()->setRequest( $request );
	}

	private function setUpMockJobQueue(
		User $expectedUserIdentity,
		string $expectedEventType,
		array $expectedExtraData,
		string $expectedMethod = 'lazyPush'
	): JobQueueGroup {
		$mockJobQueueGroup = $this->createMock( JobQueueGroup::class );
		$mockJobQueueGroup->expects( $this->once() )
			->method( $expectedMethod )
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
