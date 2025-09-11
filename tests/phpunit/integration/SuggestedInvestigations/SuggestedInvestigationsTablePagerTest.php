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

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations;

use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SuggestedInvestigationsTablePager
 * @group Database
 */
class SuggestedInvestigationsTablePagerTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	private static User $testUser1;
	private static User $testUser2;
	private static int $caseId;

	public function testQuery() {
		$pager = new SuggestedInvestigationsTablePager(
			$this->getServiceContainer()->getConnectionProvider(),
			$this->getServiceContainer()->getUserLinkRenderer(),
		);

		$results = $pager->reallyDoQuery( '', 10, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 1, $results->numRows() );

		$row = $results->fetchObject();
		$this->assertSame( '1', $row->sic_id );
		$this->assertSame( '0', $row->sic_status );
		$this->assertSame( '', $row->sic_status_reason );
		$this->assertArrayEquals(
			[ self::$testUser1->getName(), self::$testUser2->getName() ],
			$row->users,
		);
		$this->assertArrayEquals(
			[ [ 'name' => 'sharedemail', 'value' => 'Test value' ] ],
			$row->signals,
		);
	}

	public function testOutput() {
		$context = RequestContext::getMain();
		$context->setTitle( Title::newFromText( 'Special:SuggestedInvestigations' ) );
		$context->setLanguage( 'qqx' );

		$pager = new SuggestedInvestigationsTablePager(
			$this->getServiceContainer()->getConnectionProvider(),
			$this->getServiceContainer()->getUserLinkRenderer(),
			RequestContext::getMain(),
		);

		$html = $pager->getBody();

		// 1 data row + 1 header row
		$this->assertSame( 2, substr_count( $html, '<tr' ) );

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-user-check:', $html );
		$this->assertStringContainsString( 'Special:CheckUser/' . self::$testUser1->getName(), $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-signal-sharedemail)', $html );
		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-status-open)', $html );

		$name1 = urlencode( self::$testUser1->getName() );
		$name2 = urlencode( self::$testUser2->getName() );
		$this->assertStringContainsString(
			'?title=Special:Investigate&amp;targets=' . $name1 . '%0A' . $name2,
			$html
		);

		$changeStatusButtonHtml = $this->assertAndGetByElementClass(
			$html, 'mw-checkuser-suggestedinvestigations-change-status-button'
		);
		$this->assertStringContainsString( 'data-case-id="' . self::$caseId . '"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status="open"', $changeStatusButtonHtml );
		$this->assertStringContainsString( 'data-case-status-reason=""', $changeStatusButtonHtml );
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

	public function addDBDataOnce() {
		$this->enableSuggestedInvestigations();
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		self::$testUser1 = $user1 = $this->getMutableTestUser()->getUser();
		self::$testUser2 = $user2 = $this->getMutableTestUser()->getUser();

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'sharedemail', 'Test value', false );

		self::$caseId = $caseManager->createCase( [ $user1, $user2 ], [ $signal ] );
	}
}
