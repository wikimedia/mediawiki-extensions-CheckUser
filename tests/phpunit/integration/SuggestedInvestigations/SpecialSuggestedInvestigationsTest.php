<?php
/**
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

use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use PHPUnit\Framework\ExpectationFailedException;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations
 * @group Database
 */
class SpecialSuggestedInvestigationsTest extends SpecialPageTestBase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
		$this->unhideSuggestedInvestigations();
	}

	protected function newSpecialPage(): SpecialSuggestedInvestigations {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'SuggestedInvestigations' );
		$this->assertInstanceOf( SpecialSuggestedInvestigations::class, $page );
		return $page;
	}

	public function testLoadSpecialPageWhenMissingRequiredRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage();
	}

	public function testLoadSpecialPageWithRequiredRight() {
		$checkuser = $this->getTestUser( [ 'checkuser' ] )->getUser();

		$hookDefinedSignals = [
			'dev-signal-1',
			'dev-signal-2',
			[
				'name' => 'dev-signal-3',
				'displayName' => 'Dev signal 3',
				'description' => 'Dev signal 3: A signal for tests',
				'urlName' => 'signal-32a',
			],
		];
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsGetSignals',
			static function ( &$signals ) use ( $hookDefinedSignals ) {
				$signals = $hookDefinedSignals;
			}
		);
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsOnDetailViewRender',
			function () {
				$this->fail(
					'Did not expect the CheckUserSuggestedInvestigationsOnDetailViewRender hook to be run ' .
						'when not in detail view'
				);
			}
		);

		$context = RequestContext::getMain();
		$context->setUser( $checkuser );
		$context->setLanguage( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'', new FauxRequest(), null, null, true, $context
		);

		$descriptionHtml = $this->assertAndGetByElementClass(
			$html, 'ext-checkuser-suggestedinvestigations-description'
		);
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-summary',
			$descriptionHtml
		);
		$this->assertAndGetByElementClass(
			$descriptionHtml, 'ext-checkuser-suggestedinvestigations-signals-popover-icon'
		);

		$actualJsConfigVars = $context->getOutput()->getJsConfigVars();
		$this->assertArrayHasKey( 'wgCheckUserSuggestedInvestigationsSignals', $actualJsConfigVars );
		$this->assertArrayEquals(
			$hookDefinedSignals,
			$actualJsConfigVars['wgCheckUserSuggestedInvestigationsSignals']
		);
	}

	public function testLoadDetailView() {
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser( [ 'checkuser' ] )->getUser() );
		$context->setLanguage( 'qqx' );

		$hookRun = false;
		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsOnDetailViewRender',
			function ( $caseId, $output ) use ( &$hookRun ) {
				$hookRun = true;

				$this->assertSame( 1, $caseId, 'Hook was not provided the expected case ID' );
				$this->assertInstanceOf( OutputPage::class, $output );
			}
		);

		[ $html ] = $this->executeSpecialPage(
			'detail/abcdef12', new FauxRequest(), null, null, true, $context
		);
		$this->assertTrue(
			$hookRun,
			'CheckUserSuggestedInvestigationsOnDetailViewRender hook was expected to be run in detail view'
		);

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-detail-view: 1)', $html );

		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-back-to-main-page)',
			$html,
			'Missing link to go back to the main suggested investigations page'
		);

		$descriptionHtml = $this->assertAndGetByElementClass(
			$html, 'ext-checkuser-suggestedinvestigations-description'
		);
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-summary-detail-view: 1',
			$descriptionHtml
		);
	}

	public function testLoadDetailViewWithUnknownUrlIdentifier() {
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser( [ 'checkuser' ] )->getUser() );
		$context->setLanguage( 'qqx' );

		/** @var $webResponse WebResponse */
		[ $html, $webResponse ] = $this->executeSpecialPage(
			'detail/abcxyz', new FauxRequest(), null, null, true, $context
		);

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-detail-view-not-found)', $html );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-detail-view-not-found-page-text: abcxyz',
			$html,
			'Missing link to go back to the main suggested investigations page'
		);
		$this->assertSame( 404, $webResponse->getStatusCode() );
	}

	public function testLoadWithUnknownSubpage() {
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser( [ 'checkuser' ] )->getUser() );
		$context->setLanguage( 'qqx' );

		/** @var $webResponse WebResponse */
		[ $html, $webResponse ] = $this->executeSpecialPage(
			'unknown', new FauxRequest(), null, null, true, $context
		);

		$this->assertStringContainsString( '(checkuser-suggestedinvestigations-subpage-not-found)', $html );
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-subpage-not-found-page-text',
			$html,
			'Missing link to go back to the main suggested investigations page'
		);
		$this->assertSame( 404, $webResponse->getStatusCode() );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}

	/** @dataProvider provideUnavailableSpecialPage */
	public function testUnavailableSpecialPage( bool $enabled, bool $hidden ) {
		if ( $enabled ) {
			$this->enableSuggestedInvestigations();
		} else {
			$this->disableSuggestedInvestigations();
		}
		if ( $hidden ) {
			$this->hideSuggestedInvestigations();
		} else {
			$this->unhideSuggestedInvestigations();
		}

		// This exception is thrown in `newSpecialPage` when the assertion fails
		$this->expectException( ExpectationFailedException::class );
		$this->executeSpecialPage();
	}

	public static function provideUnavailableSpecialPage() {
		return [
			'Feature disabled, not hidden' => [
				'enabled' => false,
				'hidden' => false,
			],
			'Feature disabled, hidden' => [
				'enabled' => false,
				'hidden' => true,
			],
			'Feature enabled, hidden' => [
				'enabled' => true,
				'hidden' => true,
			],
		];
	}

	/** @dataProvider providePageLoadInstrumentation */
	public function testPageLoadInstrumentation(
		string $subPage, array $queryParameters, array $expectedInstrumentationData
	) {
		$performer = $this->getTestUser( [ 'checkuser' ] )->getUser();

		$context = RequestContext::getMain();
		$context->setUser( $performer );
		$context->setRequest( new FauxRequest( $queryParameters ) );

		$expectedInstrumentationData['performer'] = [ 'id' => $performer->getId() ];

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$client->expects( $this->once() )
			->method( 'submitInteraction' )
			->with( $context, 'page_load', $expectedInstrumentationData );
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$this->executeSpecialPage( $subPage, null, null, null, false, $context );
	}

	public static function providePageLoadInstrumentation(): array {
		return [
			'Page load with no additional query parameters' => [
				'subPage' => '',
				'queryParameters' => [],
				'expectedInstrumentationData' => [
					'is_paging_results' => false, 'pager_limit' => 10, 'is_in_detail_view' => false,
				],
			],
			'Page load with offset and custom limit' => [
				'subPage' => '',
				'queryParameters' => [ 'offset' => '20250405060708', 'limit' => 20 ],
				'expectedInstrumentationData' => [
					'is_paging_results' => true, 'pager_limit' => 20, 'is_in_detail_view' => false,
				],
			],
			'Page load with no offset but backwards direction and custom limit' => [
				'subPage' => '',
				'queryParameters' => [ 'dir' => 'prev' ],
				'expectedInstrumentationData' => [
					'is_paging_results' => true, 'pager_limit' => 10, 'is_in_detail_view' => false,
				],
			],
			'Page load for detail subpage with a known URL identifier' => [
				'subPage' => 'detail/abcdef12',
				'queryParameters' => [],
				'expectedInstrumentationData' => [
					'is_paging_results' => false, 'pager_limit' => 10, 'is_in_detail_view' => true,
					'case_id' => 1,
				],
			],
		];
	}

	/** @inheritDoc */
	public function addDBDataOnce() {
		$this->enableSuggestedInvestigations();

		// Create a suggested investigations case and then set it's URL identifier to 'abcdef12' so we can
		// test the detailed view by using a pre-defined stable URL identifier.
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );
		$caseId = $caseManager->createCase( [ $this->getTestUser()->getUserIdentity() ], [ $signal ] );
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_url_identifier' => hexdec( 'abcdef12' ) ] )
			->where( [ 'sic_id' => $caseId ] )
			->caller( __METHOD__ )
			->execute();
	}
}
