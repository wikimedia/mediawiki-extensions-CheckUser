<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseLogLookup;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\Services\CheckUserLogService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsUserLinkRenderer;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsUserLinkRenderer
 */
class SuggestedInvestigationsUserLinkRendererTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;
	use CheckUserTempUserTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
			MainConfigNames::LanguageCode => 'qqx',
		] );

		$this->enableAutoCreateTempUser();
	}

	public function testUserLinkLineForVisibleUserInSingleCase(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->setUserEditCount( $user, 1 );
		$caseId = $this->createCaseForUsers( [ $user ] );

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredUltimateAuthority(),
			$this->makeQqxContext(),
			[
				'caseId' => $caseId,
				'caseDetailsLink' => 'Special:SuggestedInvestigations/detail/abc',
			]
		);

		// Expected status: There's a 'contribs' link and 'check user' link; no 'past checks' or 'X SI cases'

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user:', $html );
		$this->assertStringContainsString( $user->getName(), $html );

		$this->assertStringContainsString( 'Special:Contributions/' . $user->getName(), $html );
		$this->assertStringContainsString( '(contribslink: ' . $user->getName() . ')', $html );

		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', $user->getName() ) .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html
		);
		$this->assertStringContainsString(
			urlencode( "Special:SuggestedInvestigations/detail/abc, $caseId, " . $user->getName() ),
			$html
		);

		$this->assertStringNotContainsString( 'checkuser-suggestedinvestigations-user-past-checks-link-text', $html );
		$this->assertStringNotContainsString( 'mw-usertoollinks-suggestedinvestigations-cases', $html );
	}

	/** @dataProvider provideUserEditCount */
	public function testContribsLinkMarkedWhenUserHasNoEdits( int $editCount, bool $expectRedLink ): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->setUserEditCount( $user, $editCount );

		$renderer = $this->getRenderer();
		$html = $renderer->makeUserLinkLine(
			$user,
			$this->mockRegisteredUltimateAuthority(),
			$this->makeQqxContext()
		);

		$this->assertFalse( $renderer->useGlobalContribs );

		if ( $expectRedLink ) {
			$this->assertStringContainsString( 'mw-usertoollinks-contribs-no-edits', $html );
		} else {
			$this->assertStringNotContainsString( 'mw-usertoollinks-contribs-no-edits', $html );
		}
	}

	public static function provideUserEditCount(): array {
		return [
			'User has edits' => [
				'editCount' => 1,
				'redLink' => false,
			],
			'User has no edits' => [
				'editCount' => 0,
				'redLink' => true,
			],
		];
	}

	/** @dataProvider provideIncompleteOptions */
	public function testCheckUserLinkHasNoPrefilledReasonIncompleteOptions( array $options ): void {
		// Incomplete options means that not enough data is passed to generate the prefill reason
		$user = $this->getMutableTestUser()->getUser();

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredUltimateAuthority(),
			$this->makeQqxContext(),
			$options
		);

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user-check-link-text', $html );
		$this->assertStringNotContainsString( 'reason=', $html );
	}

	public static function provideIncompleteOptions(): array {
		return [
			'Empty options' => [
				'options' => [],
			],
			'Only caseId given' => [
				'options' => [
					'caseId' => 1,
				],
			],
			'Only caseDetailsLink given' => [
				'options' => [
					'caseDetailsLink' => 'Special:SuggestedInvestigations/detail/abc',
				],
			],
		];
	}

	/** @dataProvider provideUserEditCount */
	public function testGlobalContributionsLinkUsedWhenConfigured( int $editCount, bool $expectRedLink ): void {
		$this->skipIfGlobalContribsUnavailable();

		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', true );

		// Create the users before mocking UserEditTracker, as creating a test user updates its cache
		$user = $this->getMutableTestUser()->getUser();

		// Mock the global edit counts so that only the first user has a global edit
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturn( $editCount );
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );

		// Expect that the local edit count is never fetched, as we are using the global one. Other
		// UserEditTracker methods may be legitimately called outside the renderer (e.g. autopromote
		// condition checks when looking up the hidden status), so only getUserEditCount is restricted.
		$mockUserEditTracker = $this->createMock( UserEditTracker::class );
		$mockUserEditTracker->expects( $this->never() )->method( 'getUserEditCount' );
		$mockUserEditTracker->method( 'getFirstEditTimestamp' )->willReturn( false );
		$this->setService( 'UserEditTracker', $mockUserEditTracker );

		$renderer = $this->getRenderer();
		$this->clearHooks();
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();

		$this->assertTrue( $renderer->useGlobalContribs );

		$html = $renderer->makeUserLinkLine( $user, $authority, $context );
		$this->assertStringContainsString( 'Special:GlobalContributions/' . $user->getName(), $html );
		$this->assertStringNotContainsString( 'Special:Contributions/', $html );
		if ( $expectRedLink ) {
			$this->assertStringContainsString( 'mw-usertoollinks-contribs-no-edits', $html );
		} else {
			$this->assertStringNotContainsString( 'mw-usertoollinks-contribs-no-edits', $html );
		}
	}

	/** @dataProvider provideToolLinksThatVaryBasedOnRights */
	public function testToolLinksThatVaryBasedOnRights(
		array $rights,
		bool $shouldSeeCheckUserToolLink,
		bool $shouldSeeCheckUserLogToolLink
	): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->addCheckUserLogEntry( $user );

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredAuthorityWithPermissions( $rights ),
			$this->makeQqxContext()
		);

		$checkUserKey = 'checkuser-suggestedinvestigations-user-check-link-text';
		if ( $shouldSeeCheckUserToolLink ) {
			$this->assertStringContainsString( $checkUserKey, $html );
		} else {
			$this->assertStringNotContainsString( $checkUserKey, $html );
		}

		$pastChecksKey = 'checkuser-suggestedinvestigations-user-past-checks-link-text';
		if ( $shouldSeeCheckUserLogToolLink ) {
			$this->assertStringContainsString( $pastChecksKey, $html );
		} else {
			$this->assertStringNotContainsString( $pastChecksKey, $html );
		}
	}

	public static function provideToolLinksThatVaryBasedOnRights(): array {
		return [
			'User has the checkuser and checkuser-log rights' => [
				'rights' => [ 'checkuser', 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser-log right' => [
				'rights' => [ 'checkuser' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => false,
			],
			'User lacks the checkuser right' => [
				'rights' => [ 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser and checkuser-log rights' => [
				'rights' => [],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => false,
			],
			'Only hideuser should not show the CU and CU log links' => [
				'rights' => [ 'hideuser' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => false,
			],
		];
	}

	/** @dataProvider provideUserWasChecked */
	public function testPastChecksLinkOnlyShownForCheckedUser( bool $wasChecked ): void {
		$user = $this->getMutableTestUser()->getUser();
		if ( $wasChecked ) {
			$this->addCheckUserLogEntry( $user );
		}

		$renderer = $this->getRenderer();
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();

		$html = $renderer->makeUserLinkLine( $user, $authority, $context );

		$logLink = 'Special:CheckUserLog/' . $user->getName();

		if ( $wasChecked ) {
			$this->assertStringContainsString( $logLink, $html );
		} else {
			$this->assertStringNotContainsString( $logLink, $html );
		}
	}

	public static function provideUserWasChecked(): array {
		return [
			'User was checked' => [ true ],
			'User was not checked' => [ false ],
		];
	}

	/** @dataProvider provideNumberOfSiCases */
	public function testSiCasesLinkShownForUserInMultipleCases( int $numCases, bool $shouldShow ): void {
		$user = $this->getMutableTestUser()->getUser();
		for ( $i = 0; $i < $numCases; $i++ ) {
			$this->createCaseForUsers( [ $user ] );
		}

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredUltimateAuthority(),
			$this->makeQqxContext()
		);

		if ( !$shouldShow ) {
			$this->assertStringNotContainsString( 'mw-usertoollinks-suggestedinvestigations-cases', $html );
		} else {
			$this->assertStringContainsString( 'mw-usertoollinks-suggestedinvestigations-cases', $html );
			$this->assertStringContainsString( 'Special:SuggestedInvestigations', $html );
			$this->assertStringContainsString( 'username=' . urlencode( $user->getName() ), $html );
			$this->assertStringContainsString( 'hideCasesWithNoUserEdits=0', $html );
			$this->assertStringContainsString(
				"(checkuser-suggestedinvestigations-user-si-cases-count: $numCases)",
				$html
			);
		}
	}

	public static function provideNumberOfSiCases(): array {
		return [
			'User is in 2 cases' => [
				'numCases' => 2,
				'shouldShow' => true,
			],
			'User is in 1 case' => [
				'numCases' => 1,
				'shouldShow' => false,
			],
			'User is in no cases' => [
				'numCases' => 0,
				'shouldShow' => false,
			],
		];
	}

	public function testPreloadedDataIsUsedWhenRenderingMultipleUsers(): void {
		// Set up: the first user is checked, has no edits and is in one case;
		// The second user is unchecked, has an edit and is in two cases;
		// The third user is visible (and used only for hiding).
		$firstUser = $this->getMutableTestUser()->getUser();
		$secondUser = $this->getMutableTestUser()->getUser();
		$thirdUser = $this->getMutableTestUser()->getUser();
		$this->setUserEditCount( $firstUser, 0 );
		$this->setUserEditCount( $secondUser, 1 );
		$this->addCheckUserLogEntry( $firstUser );
		$this->createCaseForUsers( [ $firstUser, $secondUser ] );
		$this->createCaseForUsers( [ $secondUser ] );

		// The viewer must not have 'hideuser', as it would skip the hidden status check entirely
		$authority = $this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] );
		$context = $this->makeQqxContext();

		$renderer = $this->getRenderer();
		$renderer->preloadEditCounts( [ $firstUser, $secondUser, $thirdUser ] );
		$renderer->preloadNonEditingData( [ $firstUser, $secondUser, $thirdUser ], $authority );

		// Invert the state of every preloaded data point, so that the assertions below only pass
		// when the preloaded data is used for rendering instead of being queried again.
		$this->createCaseForUsers( [ $firstUser ] );
		$this->createCaseForUsers( [ $secondUser ] );
		$this->placeHideUserBlock( $thirdUser );
		$this->removeCheckUserLogEntries( $firstUser );
		$this->addCheckUserLogEntry( $secondUser );
		// Change the edit counts last, so that nothing can refresh the edit count cache from the
		// DB in between (e.g. a reload of the user row caused by placing the block above)
		$this->setUserEditCount( $firstUser, 5 );
		$this->setUserEditCount( $secondUser, 0 );

		$firstUserHtml = $renderer->makeUserLinkLine( $firstUser, $authority, $context );
		$this->assertStringContainsString( 'Special:CheckUserLog/' . $firstUser->getName(), $firstUserHtml );
		$this->assertStringContainsString( 'mw-usertoollinks-contribs-no-edits', $firstUserHtml );
		$this->assertStringNotContainsString( 'mw-usertoollinks-suggestedinvestigations-cases', $firstUserHtml );

		$secondUserHtml = $renderer->makeUserLinkLine( $secondUser, $authority, $context );
		$this->assertStringNotContainsString(
			'checkuser-suggestedinvestigations-user-past-checks-link-text',
			$secondUserHtml
		);
		$this->assertStringNotContainsString( 'mw-usertoollinks-contribs-no-edits', $secondUserHtml );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-user-si-cases-count: 2)',
			$secondUserHtml
		);

		$thirdUserHtml = $renderer->makeUserLinkLine( $thirdUser, $authority, $context );
		$this->assertStringContainsString( $thirdUser->getName(), $thirdUserHtml );
		$this->assertStringNotContainsString( '(rev-deleted-user)', $thirdUserHtml );
	}

	public function testPreloadDataForGlobalContribs(): void {
		$this->skipIfGlobalContribsUnavailable();

		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', true );

		$user = $this->getMutableTestUser()->getUser();
		$this->setUserGlobalEditCount( $user, 0 );
		// Ensure we don't mistake local and global edits
		$this->setUserEditCount( $user, 5 );

		$renderer = $this->getRenderer();
		$this->clearHooks();
		$renderer->preloadEditCounts( [ $user ] );
		$this->setUserGlobalEditCount( $user, 5 );

		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();

		$userHtml = $renderer->makeUserLinkLine( $user, $authority, $context );
		$this->assertStringContainsString( 'mw-usertoollinks-contribs-no-edits', $userHtml );
	}

	public function testPreloadForNoUsersDontThrow(): void {
		$this->expectNotToPerformAssertions();
		$authority = $this->mockRegisteredUltimateAuthority();
		$renderer = $this->getRenderer();
		$renderer->preloadEditCounts( [] );
		$renderer->preloadNonEditingData( [], $authority );
	}

	public function testHiddenUserReplacedForViewerWhoCannotSeeHiddenUsers(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->placeHideUserBlock( $user );

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredAuthorityWithoutPermissions( [ 'hideuser' ] ),
			$this->makeQqxContext()
		);

		$this->assertSame( '<span class="history-deleted">(rev-deleted-user)</span>', $html );
	}

	public function testHiddenUserShownToViewerWhoCanSeeHiddenUsers(): void {
		$user = $this->getMutableTestUser()->getUser();
		$this->placeHideUserBlock( $user );

		$html = $this->getRenderer()->makeUserLinkLine(
			$user,
			$this->mockRegisteredUltimateAuthority(),
			$this->makeQqxContext()
		);

		$this->assertStringContainsString( $user->getName(), $html );
		$this->assertStringNotContainsString( '(rev-deleted-user)', $html );
	}

	/** @dataProvider provideAbuseFilterHitCount */
	public function testAbuseFilterHitsLinkShownForUserWithHits(
		int $numHits,
		bool $showLink
	): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );

		$user = $this->getMutableTestUser()->getUser();
		$this->createCaseForUsers( [ $user ] );

		$abuseLogLookup = $this->createMock( AbuseLogLookup::class );
		$abuseLogLookup->method( 'getHitCountsForUsers' )
			->willReturn( [ $user->getId() => $numHits ] );
		$this->setService( 'AbuseFilterAbuseLogLookup', $abuseLogLookup );

		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();
		$html = $this->getRenderer()->makeUserLinkLine( $user, $authority, $context );

		if ( $showLink ) {
			$this->assertStringContainsString( 'mw-usertoollinks-abusefilter-hits', $html );
			$this->assertStringContainsString( 'Special:AbuseLog', $html );
			$this->assertStringContainsString(
				'wpSearchUser=' . urlencode( $user->getName() ),
				$html
			);
			$this->assertStringContainsString(
				"(checkuser-suggestedinvestigations-user-af-hits-count: $numHits)",
				$html,
			);
		} else {
			$this->assertStringNotContainsString( 'mw-usertoollinks-abusefilter-hits', $html );
		}
	}

	public static function provideAbuseFilterHitCount(): array {
		return [
			'Has no hits' => [
				'numHits' => 0,
				'showLink' => false,
			],
			'Has 5 hits' => [
				'numHits' => 5,
				'showLink' => true,
			],
		];
	}

	private function getRevertedRevisionsTestUsers(): array {
		$userWithReverts = $this->getMutableTestUser()->getUser();
		$userWithDeletedReverts = $this->getMutableTestUser()->getUser();
		$userWithoutReverts = $this->getTestSysop()->getUser();

		// Reverted revision
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $userWithReverts );
		$revertedEditId = $this
			->editPage( 'Foo', 'Foo Bar', '', NS_MAIN, $userWithReverts )
			->getNewRevision()
			->getId();
		$this->editPage( 'Foo', 'Foo', '', NS_MAIN, $userWithoutReverts );

		// Mock that the edit has been marked as reverted, which usually happens via job
		$rcId = null;
		$this->getServiceContainer()->getChangeTagsStore()
			->updateTags( [ 'mw-reverted' ], [], $rcId, $revertedEditId );

		// Deleted/Reverted revision
		$this->editPage( 'Foo2', 'Foo', '', NS_MAIN, $userWithDeletedReverts );
		$revertedDeletedEditId = $this
			->editPage( 'Foo2', 'Foo Bar', '', NS_MAIN, $userWithDeletedReverts )
			->getNewRevision()
			->getId();
		$this->editPage( 'Foo2', 'Foo', '', NS_MAIN, $userWithDeletedReverts );

		// Mock that the edit has been marked as reverted, which usually happens via job
		$rcId = null;
		$this->getServiceContainer()->getChangeTagsStore()
			->updateTags( [ 'mw-reverted' ], [], $rcId, $revertedDeletedEditId );

		// Delete the page, creating the reverted/deleted edit
		$deletePage = $this->getServiceContainer()
			->getDeletePageFactory()
			->newDeletePage(
				Title::makeTitle( NS_MAIN, 'Foo2' )->toPageIdentity(),
				$userWithoutReverts
			)
			->forceImmediate( true );
		$deletePage->deleteUnsafe( 'Force delete' );

		return [ $userWithReverts, $userWithDeletedReverts, $userWithoutReverts ];
	}

	public function testRevertedRevisionsShownWithContributionsLink(): void {
		[ $userWithReverts, $userWithDeletedReverts, $userWithoutReverts ] = $this->getRevertedRevisionsTestUsers();
		$this->createCaseForUsers( [ $userWithReverts, $userWithoutReverts ] );

		// Assert that revert revision data is only shown if it exists
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();
		$userWithRevertsHtml = $this->getRenderer()->makeUserLinkLine( $userWithReverts, $authority, $context );
		$this->assertStringContainsString(
			'checkuser-suggestedinvestigations-reverted-revisions',
			$userWithRevertsHtml
		);
		$userWithoutRevertsHtml = $this->getRenderer()->makeUserLinkLine( $userWithoutReverts, $authority, $context );
		$this->assertStringContainsString(
			'contribslink',
			$userWithoutRevertsHtml
		);
	}

	public function testRevertedDeletedRevisionsShownWithContributionsLink(): void {
		[ $userWithReverts, $userWithDeletedReverts, $userWithoutReverts ] = $this->getRevertedRevisionsTestUsers();
		$this->createCaseForUsers( [ $userWithDeletedReverts, $userWithoutReverts ] );

		// Assert that revert revision data is only shown if it exists
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();
		$userWithRevertsHtml = $this->getRenderer()->makeUserLinkLine( $userWithDeletedReverts, $authority, $context );
		$this->assertStringContainsString(
			'checkuser-suggestedinvestigations-reverted-revisions',
			$userWithRevertsHtml
		);
		$userWithoutRevertsHtml = $this->getRenderer()->makeUserLinkLine( $userWithoutReverts, $authority, $context );
		$this->assertStringContainsString(
			'contribslink',
			$userWithoutRevertsHtml
		);
	}

	public function testRevertedRevisionsNotShownForGlobalContribsLink(): void {
		$this->skipIfGlobalContribsUnavailable();

		[ $userWithReverts, $userWithDeletedReverts, $userWithoutReverts ] = $this->getRevertedRevisionsTestUsers();
		$this->createCaseForUsers( [ $userWithReverts, $userWithDeletedReverts, $userWithoutReverts ] );

		// Assert that if global contributions are enabled, the reverted revision data isn't shown regardless
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', true );
		$userWithRevertsHtml = $this->getRenderer()->makeUserLinkLine( $userWithReverts, $authority, $context );
		$this->assertStringNotContainsString(
			'checkuser-suggestedinvestigations-reverted-revisions',
			$userWithRevertsHtml
		);
		$this->assertStringContainsString( 'contribslink', $userWithRevertsHtml );
	}

	public function testDoesntThrowWithoutExtensions(): void {
		$this->expectNotToPerformAssertions();
		$this->clearHooks();
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( false );
		$this->setService( 'ExtensionRegistry', $extensionRegistry );

		$user = $this->getMutableTestUser()->getUser();
		$authority = $this->mockRegisteredUltimateAuthority();
		$context = $this->makeQqxContext();
		$renderer = $this->getRenderer();
		$renderer->makeUserLinkLine( $user, $authority, $context );
	}

	/** @dataProvider provideMethodsPreloadNonEditingData */
	public function testPreloadsNonEditingDataWhenNeeded( string $method ): void {
		$user = $this->getMutableTestUser()->getUser();
		$authority = $this->mockAnonNullAuthority();

		$renderer = $this->getRenderer();
		$wrappedRenderer = TestingAccessWrapper::newFromObject( $renderer );
		$wrappedRenderer->$method( $user, $authority );

		$this->assertArrayHasKey( $user->getId(), $wrappedRenderer->hiddenUsersCache );
	}

	public static function provideMethodsPreloadNonEditingData(): array {
		return [
			[ 'isUserVisible' ],
			[ 'getCaseCountForUser' ],
			[ 'hasUserBeenChecked' ],
			[ 'getFilterHitCountForUser' ],
		];
	}

	private function getRenderer(): SuggestedInvestigationsUserLinkRenderer {
		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsUserLinkRenderer' );
	}

	private function makeQqxContext(): RequestContext {
		$context = RequestContext::getMain();
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		return $context;
	}

	private function createCaseForUsers( array $users ): int {
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'signal', 'Test value', false );

		return $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' )
			->createCase( $users, [ $signal ] );
	}

	private function addCheckUserLogEntry( User $target ): void {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(),
			'userips',
			'user',
			$target->getName(),
			'test',
			$target->getId()
		);
		DeferredUpdates::doUpdates();
	}

	private function removeCheckUserLogEntries( UserIdentity $user ): void {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( 'cu_log' )
			->where( [ 'cul_target_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function placeHideUserBlock( UserIdentity $user ): void {
		$this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$user,
				$this->mockRegisteredUltimateAuthority(),
				'indefinite',
				'Test reason',
				[ 'isHideUser' => true ]
			)
			->placeBlock();
	}

	private function setUserEditCount( UserIdentity $user, int $count ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $count ] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function setUserGlobalEditCount( UserIdentity $user, int $count ): void {
		$centralUser = CentralAuthUser::getInstance( $user );
		$this->getDb()->newReplaceQueryBuilder()
			->replaceInto( 'global_edit_count' )
			->uniqueIndexFields( 'gec_user' )
			->row( [
				'gec_user' => $centralUser->getId(),
				'gec_count' => $count,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	private function skipIfGlobalContribsUnavailable(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$isGlobalContributionsEnabled = $this->getServiceContainer()->getSpecialPageFactory()
			->exists( 'GlobalContributions' );
		if ( !$isGlobalContributionsEnabled ) {
			$this->markTestSkipped( 'Test requires GlobalContributions dependencies to be met' );
		}
	}
}
