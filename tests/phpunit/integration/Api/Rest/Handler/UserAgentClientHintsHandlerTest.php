<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler;
use MediaWiki\Extension\CheckUser\HookHandler\CheckUserEventsHandler;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsTrigger;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\UserAgentClientHintsHandler
 */
class UserAgentClientHintsHandlerTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;
	use CheckUserClientHintsCommonTestTrait;
	use MockServiceDependenciesTrait;
	use TempUserTestTrait;

	private static Authority $firstEventPerformer;
	private static Authority $secondEventPerformer;

	private function getObjectUnderTest(): UserAgentClientHintsHandler {
		$services = $this->getServiceContainer();
		return new UserAgentClientHintsHandler(
			$services->getMainConfig(),
			$services->getRevisionStore(),
			$services->get( 'UserAgentClientHintsManager' ),
			$services->getConnectionProvider(),
			$services->getActorStore(),
			$services->get( 'SuggestedInvestigationsTrigger' )
		);
	}

	public function testMissingPrivateEvent() {
		$this->expectExceptionObject(
			new LocalizedHttpException( new MessageValue( 'checkuser-api-useragent-clienthints-nonexistent-id' ), 404 )
		);
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData(),
			[],
			[],
			[ 'type' => 'privatelog', 'id' => 123 ],
			$validatedBody
		);
	}

	public function testPrivateEventTooOldToStoreClientHintsData() {
		ConvertibleTimestamp::setFakeTime( '20230405080910' );
		$this->overrideConfigValue( 'CheckUserClientHintsRestApiMaxTimeLag', 5 );
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-called-too-late' ),
				403
			)
		);
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData(),
			[],
			[],
			[ 'type' => 'privatelog', 'id' => 1 ],
			$validatedBody
		);
	}

	public function testUserNotEqualToPrivateEventPerformer() {
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( 'checkuser-api-useragent-clienthints-revision-user-mismatch' ),
			401
		) );
		$validatedBody = [ 'brands' => [ 'foo', 'bar' ], 'mobile' => true ];
		$this->executeHandler(
			$this->getObjectUnderTest(),
			new RequestData(),
			[],
			[],
			[ 'type' => 'privatelog', 'id' => 1 ],
			$validatedBody,
			$this->getTestUser()->getAuthority()
		);
	}

	/** @dataProvider provideSuccessfulApiCallForPrivateLogEvent */
	public function testSuccessfulApiCallForPrivateLogEvent( int $id, callable $performerCallback, bool $triggerSI ) {
		ConvertibleTimestamp::setFakeTime( '20230405060710' );

		/** @var Authority $performer */
		$performer = $performerCallback();

		// Also ensure that SuggestedInvestigations is triggered by client hints
		$suggestedInvestigationsTrigger = $this->createMock( SuggestedInvestigationsTrigger::class );
		$suggestedInvestigationsTrigger->expects( $triggerSI ? $this->once() : $this->never() )
			->method( 'matchSignalsAgainstUserInJob' )
			->willReturnCallback( function ( $user, $event, $data ) use ( $performer, $id ) {
				$this->assertSame( $user, $performer->getUser() );
				$this->assertSame( SuggestedInvestigationsSignalMatchService::EVENT_CLIENT_HINTS_SAVED, $event );
				$this->assertArrayContains( [ 'mobile' => true ], $data['clientHints'] );
				$this->assertSame( $id, $data['cuPrivateLogId'] );
			} );
		$this->setService( 'SuggestedInvestigationsTrigger', $suggestedInvestigationsTrigger );

		// Call the REST API
		$handler = $this->getObjectUnderTest();
		$response = $this->executeHandler(
			$handler,
			new RequestData(),
			[],
			[],
			[ 'type' => 'privatelog', 'id' => $id ],
			[ 'mobile' => true ],
			$performer
		);
		// Check that the call resulted in data being inserted to the relevant tables.
		$this->newSelectQueryBuilder()
			->select( [ 'uach_name', 'uach_value' ] )
			->from( 'cu_useragent_clienthints_map' )
			->join( 'cu_useragent_clienthints', null, [ 'uachm_uach_id=uach_id' ] )
			->caller( __METHOD__ )
			->assertRowValue( [ 'mobile', '1' ] );
		// Check that the output of the API is as expected
		$this->assertSame(
			json_encode( [
				"value" => $handler->getResponseFactory()->formatMessage(
					new MessageValue( 'checkuser-api-useragent-clienthints-explanation' )
				),
			], JSON_UNESCAPED_SLASHES ),
			$response->getBody()->getContents()
		);
	}

	public static function provideSuccessfulApiCallForPrivateLogEvent() {
		return [
			'cu_private_event ID 1' => [
				'id' => 1,
				'performerCallback' => static fn () => static::$firstEventPerformer,
				'triggerSI' => true,
			],
			'cu_private_event ID 2' => [
				'id' => 2,
				'performerCallback' => static fn () => static::$secondEventPerformer,
				'triggerSI' => false,
			],
		];
	}

	public function testDataAlreadyExists() {
		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue( 'checkuser-api-useragent-clienthints-mappings-exist' ),
				400
			)
		);
		// Run the code that successfully inserts data to the Client Hints data tables twice. The second call should
		// result in a 400 error for data already existing.
		$this->testSuccessfulApiCallForPrivateLogEvent( 1, static fn () => static::$firstEventPerformer, true );
		$this->testSuccessfulApiCallForPrivateLogEvent( 1, static fn () => static::$firstEventPerformer, false );
	}

	public function addDBDataOnce() {
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->enableAutoCreateTempUser();
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		// Add a password reset event so that we have something to use for testing.
		$hooks = new CheckUserEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
		$performer = $this->getMutableTestUser()->getUser();
		$hooks->onUser__mailPasswordInternal(
			$performer,
			'1.2.3.4',
			$this->getTestSysop()->getUser()
		);
		// Create a failed login attempt so that we can test with cu_private_event rows which have cupe_actor as NULL
		$hooks->onAuthManagerLoginAuthenticateAudit(
			AuthenticationResponse::newFail( wfMessage( 'test' ) ),
			null,
			$performer->getName(),
			[]
		);
		$this->assertSame(
			2,
			(int)$this->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'cu_private_event' )
				->caller( __METHOD__ )
				->fetchField()
		);
		static::$firstEventPerformer = $performer;
		static::$secondEventPerformer = $this->getServiceContainer()->getUserFactory()
			->newAnonymous( RequestContext::getMain()->getRequest()->getIP() );
		ConvertibleTimestamp::setFakeTime( false );
	}
}
