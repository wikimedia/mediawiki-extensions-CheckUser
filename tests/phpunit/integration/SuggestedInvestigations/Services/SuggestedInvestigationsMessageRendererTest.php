<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer
 */
class SuggestedInvestigationsMessageRendererTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		Theme::setSingleton( new BlankTheme() );
	}

	public function testReturnsNonEmptyStringWhenUsersHaveOpenCases(): void {
		$user = $this->getMutableTestUser()->getUser();

		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'TestSignal', 'test-value', false );
		$caseManager->createCase( [ $user ], [ $signal ] );

		$pager = $this->createMock( CheckUserGetUsersPager::class );
		$pager->expects( $this->once() )
			->method( 'getResultUsernameMap' )
			->willReturn( [ $user->getId() => $user->getName() ] );

		$renderer = new SuggestedInvestigationsMessageRenderer(
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' )
		);

		$result = $renderer->getOpenCasesNotice(
			$pager,
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer()
		);

		$this->assertNotSame( '', $result );
	}
}
