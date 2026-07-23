<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Tests\Unit\HtmlAssertionHelperTrait;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use Wikimedia\Codex\Utility\Codex;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer
 */
class SuggestedInvestigationsMessageRendererTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;
	use HtmlAssertionHelperTrait;

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
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			new Codex()
		);

		$result = $renderer->getOpenCasesNotice(
			$pager,
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer()
		);

		$this->assertNotSame( '', $result );
	}

	public function testGetUserDismissableWarning(): void {
		$renderer = new SuggestedInvestigationsMessageRenderer(
			$this->createMock( SuggestedInvestigationsCaseLookupService::class ),
			new Codex()
		);

		$result = $renderer->getUserDismissableWarning( 'Test HTML', 'test-class-abc' );

		$this->assertSelectorMatchesOneElement(
			$result,
			'.ext-checkuser-suggestedinvestigations-warning-dismiss'
		);
		$actualMessageHtml = $this->assertSelectorMatchesOneElement(
			$result,
			'.ext-checkuser-suggestedinvestigations-dismissable-warning.test-class-abc'
		);
		$this->assertStringContainsString( 'Test HTML', $actualMessageHtml );
	}
}
