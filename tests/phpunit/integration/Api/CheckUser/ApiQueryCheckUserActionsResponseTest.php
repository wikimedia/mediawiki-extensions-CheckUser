<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\CheckUser;

use FormatJson;
use LogEntryBase;
use LogFormatter;
use LogPage;
use ManualLogEntry;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserActionsResponse
 * @group Database
 */
class ApiQueryCheckUserActionsResponseTest extends MediaWikiIntegrationTestCase {
	/** @dataProvider provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters */
	public function testGetSummaryForLogEntry( $logParametersAsArray, $logParametersAsBlob ) {
		$moveLogEntry = new ManualLogEntry( 'move', 'move' );
		$moveLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$moveLogEntry->setTarget( $this->getExistingTestPage() );
		$moveLogEntry->setParameters( $logParametersAsArray );
		$mockApiQueryCheckUser = $this->createMock( ApiQueryCheckUser::class );
		$mockApiQueryCheckUser->method( 'extractRequestParams' )
			->willReturn( [
				'request' => 'actions', 'target' => 'Test', 'reason' => '', 'timecond' => '-3 months', 'limit' => '50'
			] );
		/** @var ApiQueryCheckUserResponseFactory $responseFactory */
		$responseFactory = $this->getServiceContainer()->get( 'ApiQueryCheckUserResponseFactory' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $responseFactory->newFromRequest(
			$mockApiQueryCheckUser
		) );
		$actualSummaryText = $objectUnderTest->getSummary(
			(object)[
				'log_type' => $moveLogEntry->getType(),
				'log_action' => $moveLogEntry->getSubtype(),
				'log_deleted' => 0,
				'user_text' => $moveLogEntry->getPerformerIdentity()->getName(),
				'user' => $moveLogEntry->getPerformerIdentity()->getId(),
				'title' => null,
				'page' => $moveLogEntry->getTarget()->getArticleID(),
				'log_params' => $logParametersAsBlob,
				'type' => RC_LOG,
				'timestamp' => $this->getDb()->timestamp( '20230405060708' ),
				'comment_text' => 'test',
				'comment_data' => FormatJson::encode( [] ),
			],
			UserIdentityValue::newAnonymous( '127.0.0.1' )
		);
		$this->assertSame(
			// The expected summary text is the action text from the move log entry, followed by the the comment text
			// in parantheses.
			LogFormatter::newFromEntry( $moveLogEntry )->getPlainActionText() .
				' ' . wfMessage( 'parentheses', 'test' ),
			$actualSummaryText,
			'The summary text returned by ::getSummary was not as expected'
		);
	}

	public static function provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters() {
		return [
			'Legacy log parameters' => [
				[ '4::target' => 'Testing', '5::noredir' => '0' ],
				LogPage::makeParamBlob( [ '4::target' => 'Testing', '5::noredir' => '0' ] ),
			],
			'Normal log parameters' => [
				[ '4::target' => 'Testing', '5::noredir' => '0' ],
				LogEntryBase::makeParamBlob( [ '4::target' => 'Testing', '5::noredir' => '0' ] ),
			]
		];
	}
}
