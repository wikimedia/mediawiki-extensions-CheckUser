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

use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsCasesPager
 * @group Database
 */
class SuggestedInvestigationsCasesPagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

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
			[ self::$testUser1->getName(), self::$testUser2->getName() ],
			$row->users,
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

		// Mock the edit counts for our test users so that the first test user has no edits
		// and all other users have one edit
		$mockUserEditTracker = $this->createMock( UserEditTracker::class );
		$mockUserEditTracker->method( 'getUserEditCount' )
			->willReturnCallback(
				static fn ( UserIdentity $user ) => self::$testUser1->equals( $user ) ? 0 : 1
			);
		$this->setService( 'UserEditTracker', $mockUserEditTracker );

		// Expect that the global edit count is never fetched, as we are using the local one
		$this->setService(
			'CheckUserGlobalContributionsLookup',
			$this->createNoOpMock( CheckUserGlobalContributionsLookup::class )
		);

		$pager = $this->getPager( $context );

		$html = $pager->getBody();

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

		$name1 = urlencode( self::$testUser1->getName() );
		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name1 . '%0A' . $name2 .
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

	public function testOutputWhenGlobalContributionsUsedAsContribsLink() {
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
		$mockUserEditTracker = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$mockUserEditTracker->method( 'getGlobalContributionsCount' )
			->willReturnCallback(
				static fn ( string $target ) => self::$testUser1->getName() === $target ? 0 : 1
			);
		$this->setService( 'CheckUserGlobalContributionsLookup', $mockUserEditTracker );

		// Expect that the local edit count is never fetched, as we are using the global one
		$this->setService( 'UserEditTracker', $this->createNoOpMock( UserEditTracker::class ) );

		// Check that the pager uses Special:GlobalContributions as the "contribs" tool link
		$html = $this->getPager( $context )->getBody();
		$this->commonTestContribsToolLinks( 'GlobalContributions', $html );
	}

	public function testOutputWhenCaseIdFilterSet() {
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

		$html = $pager->getBody();

		// Test that only the first case is shown.
		// Two <tr> elements will be present when this happens (1 data row + 1 header row)
		$this->assertSame( 2, substr_count( $html, '<tr' ) );
		$this->assertStringContainsString( 'first case reason', $html );
		$this->assertStringNotContainsString( 'second case reason', $html );

		// When filtering by case ID, no columns should be sortable
		$this->assertStringNotContainsString( 'cdx-table__table__cell--has-sort', $html );
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

	/** @dataProvider provideDefaultReasonWhenStatusIsInvalid */
	public function testDefaultReasonWhenStatusIsInvalid( $reasonInDatabase, $reasonDisplayedInPager ) {
		$caseId = $this->addCaseWithTwoUsers();

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseManager->setCaseStatus( $caseId, CaseStatus::Invalid, $reasonInDatabase );

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

	public static function provideDefaultReasonWhenStatusIsInvalid(): array {
		return [
			'Empty reason in database' => [ '', '(checkuser-suggestedinvestigations-status-reason-default-invalid)' ],
			'Non-empty reason in database' => [ 'testingabc', 'testingabc' ],
		];
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

	private function getPager( IContextSource $context ): SuggestedInvestigationsCasesPager {
		return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsPagerFactory' )
			->createCasesPager( $context );
	}
}
