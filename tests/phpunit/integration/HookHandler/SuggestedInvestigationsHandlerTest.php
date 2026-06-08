<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsHandler;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsHandler
 * @group CheckUser
 */
class SuggestedInvestigationsHandlerTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideRequestHeaderConfigs */
	public function testRequestHeadersCapturedIntoExtraData(
		array $configuredHeaders,
		array $requestHeaders,
		array $expectedHeaders
	) {
		$this->overrideConfigValue(
			'CheckUserSuggestedInvestigationsRequestHeaders',
			$configuredHeaders
		);
		$this->setMainRequestWithHeaders( $requestHeaders );

		$mockUser = $this->createMock( User::class );
		$handler = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue(
				$mockUser,
				'setemail',
				[ 'headers' => $expectedHeaders ]
			),
			$this->getServiceContainer()->getMainConfig()
		);

		$email = 'test@test.com';
		$handler->onUserSetEmail( $mockUser, $email );
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

	public function testHeadersMergedWithRevIdOnPageSaveComplete() {
		$this->overrideConfigValue(
			'CheckUserSuggestedInvestigationsRequestHeaders',
			[ 'User-Agent' ]
		);
		$this->setMainRequestWithHeaders( [ 'User-Agent' => 'foo bar' ] );

		$revId = 123;
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )->willReturn( $revId );

		$editResult = $this->createMock( EditResult::class );
		$editResult->method( 'isNullEdit' )->willReturn( false );

		$mockUser = $this->createMock( User::class );
		$handler = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue(
				$mockUser,
				'successfuledit',
				[ 'revId' => $revId, 'headers' => [ 'user-agent' => 'foo bar' ] ]
			),
			$this->getServiceContainer()->getMainConfig()
		);

		$handler->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$mockUser,
			'',
			0,
			$revisionRecord,
			$editResult
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
