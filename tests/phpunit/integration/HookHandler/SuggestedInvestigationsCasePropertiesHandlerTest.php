<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsCasePropertiesHandler;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsCasePropertiesHandler
 * @group Database
 * @group CheckUser
 */
class SuggestedInvestigationsCasePropertiesHandlerTest extends MediaWikiIntegrationTestCase {
	private function getObjectUnderTest(): SuggestedInvestigationsCasePropertiesHandler {
		return new SuggestedInvestigationsCasePropertiesHandler(
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCasePropertyManager' )
		);
	}

	/** @dataProvider provideTestExecute */
	public function testExecuteNoUserId( int $userId, bool $SIEnabled ) {
		$mockSICaseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		if ( $userId ) {
			$mockSICaseLookup->expects( $this->once() )
				->method( 'areSuggestedInvestigationsEnabled' )
				->willReturn( $SIEnabled );
		} else {
			$mockSICaseLookup->expects( $this->never() )
				->method( 'areSuggestedInvestigationsEnabled' );
		}

		$mockSICasePropertyManager = $this->createMock( SuggestedInvestigationsCasePropertyManagerService::class );
		if ( $userId && $SIEnabled ) {
			$mockSICasePropertyManager->expects( $this->once() )
				->method( 'updateEditRelatedPropertiesForCasesWithUsers' );
		} else {
			$mockSICasePropertyManager->expects( $this->never() )
				->method( 'updateEditRelatedPropertiesForCasesWithUsers' );
		}

		$objectUnderTest = new SuggestedInvestigationsCasePropertiesHandler(
			$mockSICaseLookup,
			$mockSICasePropertyManager
		);

		$mockUserIdentity = $this->createMock( UserIdentity::class );
		$mockUserIdentity
			->expects( $this->once() )
			->method( 'getId' )
			->willReturn( $userId );

		$tags = [];
		$objectUnderTest->onRevisionFromEditComplete(
			$this->createMock( WikiPage::class ),
			$this->createMock( RevisionRecord::class ),
			false,
			$mockUserIdentity,
			$tags
		);
		DeferredUpdates::doUpdates();
	}

	public static function provideTestExecute() {
		return [
			'No user id' => [
				'userId' => 0,
				'SIEnabled' => true,
			],
			'Suggested Investigations not enabled' => [
				'userId' => 1,
				'SIEnabled' => false,
				],
			'Execute' => [
				'userId' => 1,
				'SIEnabled' => true,
			],
		];
	}
}
