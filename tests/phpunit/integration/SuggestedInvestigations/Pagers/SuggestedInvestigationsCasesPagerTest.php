<?php
/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\Pagers;

use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\CentralAuth\CentralAuthEditCounter;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager
 * @group Database
 */
class SuggestedInvestigationsCasesPagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;

	private static User $testUser1;
	private static User $testUser2;
	private const SIGNAL = 'signalname';

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	public function testQuery() {
		$caseId = $this->addCaseWithTwoUsers();
		$pager = $this->getPager( RequestContext::getMain() );

		$results = $pager->reallyDoQuery( '', 10, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 1, $results->numRows() );

		$row = $results->fetchObject();
		$this->assertSame( $caseId, (int)$row->sic_id );
		$this->assertSame( '0', $row->sic_status );
		$this->assertSame( '', $row->sic_status_reason );
		$this->assertArrayEquals(
			[ self::$testUser2->getName(), self::$testUser1->getName() ],
			array_map( static fn ( $user ) => $user->getName(), $row->users ),
			true,
			false,
			'Users row did not have the expected items in the expected order',
		);
		$this->assertArrayEquals(
			[ self::SIGNAL ],
			$row->signals,
		);
	}

	public function testOutput() {
		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => false,
			MainConfigNames::LanguageCode => 'qqx',
		] );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$caseId = $this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		// Mock the edit counts for our test users so that the first test user has no edits
		// and all other users have one edit
		$mockUserEditTracker = $this->createMock( UserEditTracker::class );
		$mockUserEditTracker->method( 'getUserEditCount' )
			->willReturnCallback(
				static fn ( UserIdentity $user ) => self::$testUser1->equals( $user ) ? 0 : 1
			);
		$this->setService( 'UserEditTracker', $mockUserEditTracker );

		// Expect that the global edit count is never fetched, as we are using the local one.
		// If CentralAuth is loaded, use a no-op mock. Otherwise a use of the service should
		// mean trying to access methods on `null` (which will fail)
		if ( $this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'CentralAuth' ) ) {
			$this->setService(
				'CentralAuth.CentralAuthEditCounter',
				$this->createNoOpMock( CentralAuthEditCounter::class )
			);
		}

		$pager = $this->getPager( $context );

		$parserOutput = $pager->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user:', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-status-open)', $html );

		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser1->getName() ) .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should contain link to Special:CheckUser for the first user'
		);
		$this->assertStringContainsString(
			'?title=Special:CheckUser/' . str_replace( ' ', '_', self::$testUser2->getName() ) .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-check-reason-prefill',
			$html,
			'Should contain link to Special:CheckUser for the second user'
		);

		$this->commonTestContribsToolLinks( 'Contributions', $html );

		$this->assertStringNotContainsString(
			'checkuser-suggestedinvestigations-user-past-checks-link-text',
			$html,
			'Links to Special:CheckUserLog should not be added unless a user has been checked'
		);

		$name1 = urlencode( self::$testUser1->getName() );
		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name2 . '%0A' . $name1 .
				'&amp;reason=%28checkuser-suggestedinvestigations-user-investigate-reason-prefill',
			$html,
			'Should contain link to Special:Investigate in the case row'
		);
		$this->assertStringContainsString( 'title="(checkuser-suggestedinvestigations-action-investigate)"', $html );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status="open"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status-reason=""', $changeStatusButtonHtml );

		// Validate the timestamp cell contains the correct data and also links to the detail view
		$urlIdentifier = $this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertStringContainsString(
			'Special:SuggestedInvestigations/detail/' . dechex( $urlIdentifier ),
			$html,
			'The detail view link for the case is missing'
		);

		$context = RequestContext::getMain();
		$this->assertStringContainsString(
			$context->getLanguage()->userTimeAndDate( '20250403020100', $context->getUser() ),
			$html,
			'The case creation timestamp is not present in the table row for the case'
		);

		// Validate that both the status reason and status cells have the associated suggested investigations case
		// ID as data attributes.
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusReasonCell );

		$statusCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $statusCell );

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$html,
			'Filter button is not present in the page or has an unexpected label'
		);
		$this->assertActiveFiltersJsConfigVar( [], $parserOutput );
	}

	/**
	 * Verifies that the contribs toollinks are present for a two user case and that they use the provided
	 * contributions special page.
	 */
	private function commonTestContribsToolLinks(
		string $expectedContributionsSpecialPageName,
		string $html
	): void {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$contributionsToolLinks = DOMCompat::querySelectorAll( $specialPageDocument, '.mw-usertoollinks-contribs' );
		$this->assertCount( 2, $contributionsToolLinks, 'Expected two contributions toollinks' );
		foreach ( $contributionsToolLinks as $contributionsToolLink ) {
			$toolLinkHtml = DOMCompat::getOuterHTML( $contributionsToolLink );

			// Check if the tool link has the class for indicating that the user has no edits:
			// * If it does, then the first test user is the user we should expect be associated
			//   with this tool link.
			// * If it does not, then the second test user should be expected
			// This is because we mock in the test for the first test user to have no edits
			// and all other users to have one edit
			$expectedUserName = str_contains( $toolLinkHtml, 'mw-usertoollinks-contribs-no-edits' ) ?
				self::$testUser1->getName() : self::$testUser2->getName();

			$this->assertStringContainsString(
				$expectedUserName,
				$toolLinkHtml,
				"Expected tool link to be for user $expectedUserName"
			);
			$this->assertStringContainsString(
				"Special:$expectedContributionsSpecialPageName/$expectedUserName",
				$toolLinkHtml
			);
			$this->assertStringContainsString( "(contribslink: $expectedUserName)", $toolLinkHtml );
		}
	}

	/** @dataProvider provideSignalNameForVaryingSignalsArray */
	public function testSignalNameForVaryingSignalsArray( array $signals, string $expectedSignalName ): void {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		$pager = $this->getPager( $context, $signals );
		$html = $pager->getBody();

		$this->assertStringContainsString(
			$expectedSignalName,
			$html,
			'Signal was not present in the page as expected'
		);
	}

	public static function provideSignalNameForVaryingSignalsArray(): array {
		return [
			'Signals array does not have self::SIGNAL defined' => [
				'signals' => [],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined just as a string' => [
				[ self::SIGNAL ],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined using array format' => [
				[ [ 'name' => self::SIGNAL ] ],
				'expectedSignalName' => '(checkuser-suggestedinvestigations-signal-' . self::SIGNAL . ')',
			],
			'Signals array has self::SIGNAL defined using array format with custom display name' => [
				[ [ 'name' => self::SIGNAL, 'displayName' => 'Test signal display name' ] ],
				'expectedSignalName' => 'Test signal display name',
			],
		];
	}

	public function testOutputWhenGlobalContributionsUsedAsContribsLink() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$isGlobalContributionsEnabled = $this->getServiceContainer()->getSpecialPageFactory()
			->exists( 'GlobalContributions' );
		if ( !$isGlobalContributionsEnabled ) {
			$this->markTestSkipped( 'Test requires GlobalContributions dependencies to be met' );
		}

		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => true,
			MainConfigNames::LanguageCode => 'qqx',
		] );

		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );

		// Mock the global edit counts for our test users so that the first test user has no edits
		// and all other users have one edit
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturnCallback(
				static fn ( CentralAuthUser $centralUser ) =>
					self::$testUser1->getName() === $centralUser->getName() ? 0 : 1
			);
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );

		// Expect that the local edit count is never fetched, as we are using the global one
		$this->setService( 'UserEditTracker', $this->createNoOpMock( UserEditTracker::class ) );

		// Check that the pager uses Special:GlobalContributions as the "contribs" tool link
		$html = $this->getPager( $context )->getBody();
		$this->commonTestContribsToolLinks( 'GlobalContributions', $html );
	}

	public function testOutputWhenUserHasBeenCheckedBefore() {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );

		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredUltimateAuthority() );

		$this->addCheckUserLogEntryForFirstUser();

		$html = $this->getPager( $context )->getBody();

		// Expect that the table pager shows the "past checks" link for the first test user
		$this->assertStringContainsString( 'Special:CheckUserLog/' . self::$testUser1->getName(), $html );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-user-past-checks-link-text: ' . self::$testUser1->getName(),
			$html
		);

		// Expect that the table pager does not show the "past checks" link for the second test user
		$this->assertStringNotContainsString( 'Special:CheckUserLog/' . self::$testUser2->getName(), $html );
		$this->assertStringNotContainsString(
			'(checkuser-suggestedinvestigations-user-past-checks-link-text: ' . self::$testUser2->getName(),
			$html
		);
	}

	/**
	 * Add a CheckUserLog entry where the target of the check is the first test user
	 * (as set by {@link self::addCaseWithTwoUsers})
	 */
	private function addCheckUserLogEntryForFirstUser(): void {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			self::$testUser2, 'userips', 'user', self::$testUser1->getName(), 'test',
			self::$testUser1->getId()
		);
		DeferredUpdates::doUpdates();
	}

	/** @dataProvider provideToolLinksThatVaryBasedOnRights */
	public function testToolLinksThatVaryBasedOnRights(
		array $authorityRights,
		bool $shouldSeeCheckUserToolLink,
		bool $shouldSeeCheckUserLogToolLink
	) {
		$this->addCaseWithTwoUsers();
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setAuthority( $this->mockRegisteredAuthorityWithPermissions( $authorityRights ) );
		$context->setLanguage( 'qqx' );

		$this->addCheckUserLogEntryForFirstUser();

		// If the user has the 'checkuser' right, then the tool links should include a link to
		// Special:CheckUser. Otherwise the tool link should not be displayed
		$html = $this->getPager( $context )->getBody();

		if ( $shouldSeeCheckUserToolLink ) {
			$this->assertStringContainsString(
				'checkuser-suggestedinvestigations-user-check-link-text',
				$html,
				'Should have checkuser tool link as user has the checkuser right'
			);
		} else {
			$this->assertStringNotContainsString(
				'checkuser-suggestedinvestigations-user-check-link-text',
				$html,
				'Should not have checkuser tool link as user lacks the checkuser right'
			);
		}

		if ( $shouldSeeCheckUserLogToolLink ) {
			$this->assertStringContainsString(
				'checkuser-suggestedinvestigations-user-past-checks-link-text',
				$html,
				'Should have checkuser log tool link as user has the checkuser right'
			);
		} else {
			$this->assertStringNotContainsString(
				'checkuser-suggestedinvestigations-user-past-checks-link-text',
				$html,
				'Should not have checkuser log tool link as user lacks the checkuser right'
			);
		}
	}

	public static function provideToolLinksThatVaryBasedOnRights(): array {
		return [
			'User has the checkuser and checkuser-log rights' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser', 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser-log right' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser' ],
				'shouldSeeCheckUserToolLink' => true,
				'shouldSeeCheckUserLogToolLink' => false,
			],
			'User lacks the checkuser right' => [
				'authorityRights' => [ 'checkuser-suggested-investigations', 'checkuser-log' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => true,
			],
			'User lacks the checkuser and checkuser-log rights' => [
				'authorityRights' => [ 'checkuser-suggested-investigations' ],
				'shouldSeeCheckUserToolLink' => false,
				'shouldSeeCheckUserLogToolLink' => false,
			],
		];
	}

	public function testOutputWhenCaseIdFilterSet() {
		ConvertibleTimestamp::setFakeTime( '20250403020100' );

		$firstCaseId = $this->addCaseWithTwoUsers();
		$secondCaseId = $this->addCaseWithTwoUsers();

		// Update the cases to have a specific reason, so that we can assert that the first case and not the
		// second case is shown in the page
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->setCaseStatus( $firstCaseId, CaseStatus::Open, 'first case reason' );
		$caseManager->setCaseStatus( $secondCaseId, CaseStatus::Invalid, 'second case reason' );

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );
		$pager->caseIdFilter = $firstCaseId;

		$html = $pager->getFullOutput()->getContentHolder()->getAsHtmlString();

		// Test that only the first case is shown.
		// Two <tr> elements will be present when this happens (1 data row + 1 header row)
		$this->assertSame( 2, substr_count( $html, '<tr' ) );
		$this->assertStringContainsString( 'first case reason', $html );
		$this->assertStringNotContainsString( 'second case reason', $html );

		// When filtering by case ID, no columns should be sortable
		$this->assertStringNotContainsString( 'cdx-table__table__cell--has-sort', $html );

		// No link to the detail view should be present when on that detail view page, but the
		// case creation timestamp should still be shown
		$this->assertStringNotContainsString( 'Special:SuggestedInvestigations/detail/', $html );

		$context = RequestContext::getMain();
		$this->assertStringContainsString(
			$context->getLanguage()->userTimeAndDate( '20250403020100', $context->getUser() ),
			$html,
			'The case creation timestamp is not present in the table row for the case'
		);

		$this->assertStringNotContainsString(
			'cdx-table__header',
			$html,
			'Detailed view should not have the table header element'
		);
		$this->assertStringNotContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button',
			$html,
			'Detailed view should not have the filter button'
		);
	}

	public function testInvestigateDisabledWhenTooManyUsers() {
		$caseId = $this->addCaseWithManyUsers();

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringNotContainsString( '?title=Special:Investigate', $html );

		$usersLimit = SpecialInvestigate::MAX_TARGETS;
		$this->assertStringContainsString(
			'title="(checkuser-suggestedinvestigations-action-investigate-disabled: ' . $usersLimit . ')"',
			$html );

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . $caseId . '"', $changeStatusButtonHtml );
	}

	/** @dataProvider provideStatusReasonDisplayedInPager */
	public function testStatusReasonDisplayedInPager(
		CaseStatus $caseStatus,
		string $reasonInDatabase,
		string $reasonDisplayedInPager
	) {
		$caseId = $this->addCaseWithTwoUsers();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->setCaseStatus( $caseId, $caseStatus, $reasonInDatabase );

		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

		// Validate that the status reason contains the default for the invalid status
		$statusReasonCell = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-status-reason'
		);
		$this->assertStringContainsString( $reasonDisplayedInPager, $statusReasonCell );
	}

	public static function provideStatusReasonDisplayedInPager(): array {
		return [
			'Empty reason in database for invalid case' => [
				CaseStatus::Invalid, '', '(checkuser-suggestedinvestigations-status-reason-default-invalid)',
			],
			'Non-empty reason in database for invalid case' => [ CaseStatus::Invalid, 'testingabc', 'testingabc' ],
			'Empty reason in database for resolved case' => [ CaseStatus::Resolved, '', '' ],
			'Non-empty reason in database for open case' => [ CaseStatus::Open, 'testingabc', 'testingabc' ],
		];
	}

	public function testStatusReasonHasWikitext() {
		$wikitextReason = '[[Test]]';
		$this->testStatusReasonDisplayedInPager(
			CaseStatus::Open,
			$wikitextReason,
			$this->getServiceContainer()->getCommentFormatter()->format( $wikitextReason )
		);
	}

	public function testWhenStatusFilterIsSet() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases, where one is then closed
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		$caseManager->setCaseStatus( $firstCaseId, CaseStatus::Resolved );

		// Load the pager with the 'status' query parameter set to 'open'
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'status', 'open' );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows the open case by checking
		// only the first case ID is present as a data attribute
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$html,
			'Filter button is not present in the page or has an unexpected label'
		);
		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$html,
			'The info chip indicating how many filters were applied was not present'
		);
		$this->assertActiveFiltersJsConfigVar( [ 'status' => [ 'open' ] ], $parserOutput );
	}

	public function testWhenUsernameFilterIsSet() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Load the pager with the 'username' query set to the first user's username
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'username', $firstUser->getName() );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the first case by checking
		// only the first case ID is present as a data attribute
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'username' => [ $firstUser->getName() ] ], $parserOutput );
	}

	public function testWhenUsernameFilterUsesUnknownUsername() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create a case for an existing user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		// Load the pager with the 'username' query set to a string that isn't an existing username
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'username', __METHOD__ . wfRandomString() );

		$html = $this->getPager( $context )->getBody();

		// Expect that the table pager has no results by looking for the table_pager_empty
		// message key and checking that no data-case-id attributes exist in the page
		$this->assertStringContainsString( '(table_pager_empty)', $html );
		$this->assertStringNotContainsString( 'data-case-id', $html );
	}

	/** @dataProvider provideLimitValues */
	public function testWhenHideCasesWithNoUserEditsFilterIsSetForLocalEditCounts( int $limit ) {
		$this->overrideConfigValue( 'CheckUserSuggestedInvestigationsUseGlobalContributionsLink', false );

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		// Mock that the first test user has 2 edits
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => 2 ] )
			->where( [ 'user_id' => $firstUser->getId() ] )
			->caller( __METHOD__ )
			->execute();

		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Load the pager with the 'hideCasesWithNoUserEdits' query param set to 1
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 1 );
		$context->getRequest()->setVal( 'limit', $limit );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the first case, as only the first case
		// has users with edits in it.
		$this->assertStringContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringNotContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoUserEdits' => true ], $parserOutput );
		$this->assertFalse(
			$jsConfigVars['wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed'],
			'Value of JS config var wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed ' .
				' is not as expected'
		);
	}

	public static function provideLimitValues(): array {
		return [
			'Limit of 1' => [ 1 ],
			'Limit of 2' => [ 2 ],
			'Limit of 10' => [ 10 ],
		];
	}

	/** @dataProvider provideLimitValues */
	public function testWhenHideCasesWithNoUserEditsFilterIsSetForGlobalEditCounts( int $limit ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$isGlobalContributionsEnabled = $this->getServiceContainer()->getSpecialPageFactory()
			->exists( 'GlobalContributions' );
		if ( !$isGlobalContributionsEnabled ) {
			$this->markTestSkipped( 'Test requires GlobalContributions dependencies to be met' );
		}

		$this->overrideConfigValues( [
			'CheckUserSuggestedInvestigationsUseGlobalContributionsLink' => true,
			MainConfigNames::LanguageCode => 'qqx',
		] );

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases each with a different user
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$firstUser = $this->getMutableTestUser()->getUserIdentity();
		$secondUser = $this->getMutableTestUser()->getUserIdentity();

		$firstCaseId = $caseManager->createCase( [ $firstUser ], [ $signal ] );
		$secondCaseId = $caseManager->createCase( [ $secondUser ], [ $signal ] );

		// Mock that the second test user has an edit and all others do not
		$mockCentralAuthEditCounter = $this->createMock( CentralAuthEditCounter::class );
		$mockCentralAuthEditCounter->method( 'getCount' )
			->willReturnCallback(
				static fn ( CentralAuthUser $centralUser ) =>
					$secondUser->getName() === $centralUser->getName() ? 1 : 0
			);
		$this->setService( 'CentralAuth.CentralAuthEditCounter', $mockCentralAuthEditCounter );

		// Load the pager with the 'hideCasesWithNoUserEdits' query param set to 1
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'hideCasesWithNoUserEdits', 1 );
		$context->getRequest()->setVal( 'limit', $limit );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();
		$jsConfigVars = $parserOutput->getJsConfigVars();

		// Expect that the table pager only shows the second case, as only the second case
		// has users with edits in it.
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );
		$this->assertActiveFiltersJsConfigVar( [ 'hideCasesWithNoUserEdits' => true ], $parserOutput );
		$this->assertTrue(
			$jsConfigVars['wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed'],
			'Value of JS config var wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed ' .
			' is not as expected'
		);
	}

	public function testWhenPHPFiltersLimitReached() {
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = $this->getPager( $context );

		// Actually hitting the limit would be expensive for tests, as we would need to create around
		// 1,000 testing rows. Therefore, we should just fake that this has been reached.
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->phpFiltersLimitReached = true;

		// Added via IContextSource::getOutput::addHTML to make sure it appears above the Codex table, however,
		// we need to call ::getFullOutput first so that IContextSource::getOutput::addHTML is actually called
		$pager->getFullOutput();
		$html = $context->getOutput()->getHtml();

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-too-many-results-filtered-in-php)',
			$html,
			'PHP filter limit hit message should be present in the outputted HTML'
		);
		$this->assertStringContainsString(
			'ext-checkuser-suggestedinvestigations-warning-dismiss',
			$html,
			'The warning should be dismissable'
		);
	}

	/** @dataProvider provideWhenSignalFilterIsSet */
	public function testWhenSignalFilterIsSet( $urlName, $signals ): void {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases, with different signals
		$firstSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$secondSignal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			'dev-signal-2', 'Test value', false
		);
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $firstSignal ] );
		$secondCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $secondSignal ] );

		// Load the pager with the 'signal' query parameter set to 'dev-signal-2'
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		$context->getRequest()->setVal( 'signal', $urlName );

		$parserOutput = $this->getPager( $context, $signals )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		// Expect that the table pager only shows the case with the dev-signal-2 signal
		$this->assertStringNotContainsString( 'data-case-id="' . $firstCaseId . '"', $html );
		$this->assertStringContainsString( 'data-case-id="' . $secondCaseId . '"', $html );

		$this->assertActiveFiltersJsConfigVar( [ 'signal' => [ 'dev-signal-2' ] ], $parserOutput );
	}

	public static function provideWhenSignalFilterIsSet(): array {
		return [
			'URL name is the database name of the signal' => [ 'dev-signal-2', [] ],
			'URL name is the database name of the signal with signals array defined' => [
				'dev-signal-2',
				[ [ 'name' => 'dev-signal-2', 'urlName' => 'signal-e3' ] ],
			],
			'Using the URL name of the signal with signals array defined' => [
				'signal-e3',
				[ [ 'name' => 'dev-signal-2', 'urlName' => 'signal-e3' ] ],
			],
		];
	}

	public function testFilterIsRelayedInLimitForm() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		// Create two cases, where one is then closed
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult(
			self::SIGNAL, 'Test value', false
		);
		$firstCaseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );
		$caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );

		$caseManager->setCaseStatus( $firstCaseId, CaseStatus::Resolved );

		// Load the pager with the 'status' query parameter set to [ 'open', 'resolved' ] and an unknown parameter
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );
		// So that we have the limit form shown in the output
		$context->getRequest()->setVal( 'limit', 1 );
		$context->getRequest()->setVal( 'status', [ 'open', 'resolved', 'unknown' => 'loremIpsum' ] );
		$context->getRequest()->setVal( 'unknownArrayField', [ 'test' ] );

		$parserOutput = $this->getPager( $context )->getFullOutput();
		$html = $parserOutput->getContentHolder()->getAsHtmlString();

		$parsedDom = DOMUtils::parseHTML( $html );
		$pager = DOMCompat::querySelector( $parsedDom, '.cdx-table-pager__start' );
		$this->assertNotNull( $pager );

		$this->assertNotNull( DOMCompat::querySelector( $pager, 'input[name="status[0]"][value="open"]' ) );
		$this->assertNotNull( DOMCompat::querySelector( $pager, 'input[name="status[1]"][value="resolved"]' ) );
		$this->assertNull( DOMCompat::querySelector( $pager, 'input[name="status[unknown]"]' ) );
		$this->assertNull( DOMCompat::querySelector( $pager, 'input[name="unknownArrayField[0]"]' ) );
	}

	/**
	 * Asserts that the {@link ParserOutput} has the wgCheckUserSuggestedInvestigationsActiveFilters JS
	 * config var and that is matches the expected value
	 *
	 * @param array $expected An array of properties that override the default value for these properties
	 *   in the expected array
	 */
	private function assertActiveFiltersJsConfigVar( array $expected, ParserOutput $parserOutput ): void {
		$this->assertArrayEquals(
			array_merge( [
				'status' => [],
				'username' => [],
				'hideCasesWithNoUserEdits' => false,
				'signal' => [],
			], $expected ),
			$parserOutput->getJsConfigVars()['wgCheckUserSuggestedInvestigationsActiveFilters'],
			false, true,
			'Active filters on the page is not as expected'
		);
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML of that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getOuterHTML( $element[0] );
	}

	private function addCaseWithTwoUsers() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		self::$testUser1 = $user1 = $this->getMutableTestUser()->getUser();
		self::$testUser2 = $user2 = $this->getMutableTestUser()->getUser();

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $caseManager->createCase( [ $user1, $user2 ], [ $signal ] );
	}

	private function addCaseWithManyUsers() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$users = [];
		for ( $i = 0; $i < SpecialInvestigate::MAX_TARGETS + 1; $i++ ) {
			$users[] = $this->getMutableTestUser()->getUser();
		}

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( self::SIGNAL, 'Test value', false );

		return $caseManager->createCase( $users, [ $signal ] );
	}

	private function getPager( IContextSource $context, array $signals = [] ): SuggestedInvestigationsCasesPager {
		return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsPagerFactory' )
			->createCasesPager( $context, $signals );
	}
}
