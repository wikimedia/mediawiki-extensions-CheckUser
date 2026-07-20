<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UserSummaryHandler;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Rest\Handler\SessionHelperTestTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UserSummaryHandler
 * @group Database
 */
class UserSummaryHandlerTest extends MediaWikiIntegrationTestCase {

	use SuggestedInvestigationsTestTrait;
	use HandlerTestTrait;
	use SessionHelperTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	private function getObjectUnderTest(): UserSummaryHandler {
		$services = $this->getServiceContainer();
		return new UserSummaryHandler(
			$services->getMainConfig(),
			$services->getActorStore(),
			$services->get( 'CheckUserSuggestedInvestigationsCaseLookup' )
		);
	}

	private function getRequestData( string $name, array $postParams = [] ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'name' => $name ],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $postParams ),
		] );
	}

	public function testWhenFeatureIsNotEnabled(): void {
		$this->disableSuggestedInvestigations();
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( 'checkuser-suggestedinvestigations-not-enabled' ),
			404
		) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( $this->getTestUser()->getUser()->getName() )
		);
	}

	public function testWhenUserLacksCheckUserRight(): void {
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-permission-error' ), 403 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( $this->getTestUser()->getUser()->getName() ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredNullAuthority()
		);
	}

	public function testWhenProvidedTokenIsInvalid(): void {
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-badtoken' ), 403 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( $this->getTestUser()->getUser()->getName(), [ 'token' => 'invalid' ] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority(),
			$this->getSession( false )
		);
	}

	public function testExecuteWhenUserIsInvalid(): void {
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( 'apierror-invaliduser' ),
			404
		) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( 'Foo' ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);
	}

	public function testExecute(): void {
		// Generate users and cases to query
		$userWithManyCases = $this->getMutableTestUser()->getUser();
		$userWithOneCase = $this->getMutableTestUser()->getUser();
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->createCase(
			[ $userWithManyCases, $userWithOneCase ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'foo', 'bar', false ) ]
		);
		$caseManager->createCase(
			[ $userWithManyCases ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'foo', 'bar', false ) ]
		);

		$multipleCasesJsonResponse = $this->executeHandlerAndGetBodyData(
			$this->getObjectUnderTest(),
			$this->getRequestData( $userWithManyCases->getName(), [] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);

		$this->assertArrayEquals(
			[
				'relatedUserIdsCount' => 1,
				'relatedCasesCount' => 2,
			],
			$multipleCasesJsonResponse,
			false,
			true
		);
	}
}
