<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate;

use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\Tests\SpecialPage\FormSpecialPageTestCase;
use MediaWiki\User\User;
use TestUser;

/**
 * @covers \MediaWiki\CheckUser\Investigate\SpecialInvestigate
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePager
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\ComparePager
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager
 * @group CheckUser
 * @group Database
 */
class SpecialInvestigateTest extends FormSpecialPageTestCase {

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Investigate' );
	}

	/**
	 * Generates a test user with the checkuser group and also assigns that user as the user for the main context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = $this->getTestUser( [ 'checkuser', 'sysop' ] )->getUser();
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	/**
	 * Returns the same string as returned by SpecialInvestigate::getTabParam for the given tab.
	 * Used to generate the correct subpage name when testing the Special:Investigate tabs.
	 *
	 * @param string $tab
	 * @return string
	 */
	private function getTabParam( string $tab ): string {
		$name = wfMessage( 'checkuser-investigate-tab-' . $tab )->inLanguage( 'en' )->text();
		return str_replace( ' ', '_', $name );
	}

	public function testViewSpecialPageBeforeCheck() {
		// Execute the special page. We need the full HTML to verify that the logs button is shown.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );
		// Verify that the HTML includes the form fields needed to start an investigation.
		$this->assertStringContainsString( '(checkuser-investigate-duration-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-targets-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-reason-label', $html );
		// Verify that the form legend is displayed
		$this->assertStringContainsString( '(checkuser-investigate-legend', $html );
		// Verify that the 'Logs' button is shown
		$this->assertStringContainsString( '(checkuser-investigate-indicator-logs', $html );
	}

	public function testSubmitFiltersForm() {
		$subPage = $this->getTabParam( 'compare' );
		// Set-up the valid request and get a test checkuser user
		$testCheckUser = $this->getTestCheckUser();
		$fauxRequest = new FauxRequest(
			[
				'targets' => [ '127.0.0.1', 'InvestigateTestUser1' ],
				'exclude-targets' => [ 'InvestigateTestUser2' ],
			],
			true
		);
		RequestContext::getMain()->setRequest( $fauxRequest );
		// Generate a valid token and set it in the request.
		$token = $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' )->updateToken(
			$fauxRequest,
			[ 'offset' => null, 'reason' => 'Test reason', 'targets' => [ '127.0.0.1' ], 'exclude-targets' => [] ]
		);
		$fauxRequest->setVal( 'token', $token );
		// The request URL is required to be set, as it is used by SpecialInvestigate::alterForm.
		$fauxRequest->setRequestURL(
			$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::CanonicalServer ) .
			"Special:Investigate/$subPage"
		);
		// Execute the special page and get the HTML output.
		[ $html, $response ] = $this->executeSpecialPage( $subPage, $fauxRequest, null, $testCheckUser );
		$this->assertSame(
			'', $html,
			'The form should not be displayed after submitting the form using POST, as it causes a redirect.'
		);
		/** @var $response FauxResponse */
		$this->assertNotEmpty(
			$response->getHeader( 'Location' ),
			'The response should be a redirect after submitting the form using POST.'
		);
	}

	public function testSubmitFormForPost() {
		// Set-up the valid request and get a test checkuser user
		$testCheckUser = $this->getTestCheckUser();
		$fauxRequest = new FauxRequest(
			[
				'targets' => "127.0.0.1\nInvestigateTestUser1", 'duration' => '',
				'reason' => 'Test reason', 'wpEditToken' => $testCheckUser->getEditToken(),
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);
		RequestContext::getMain()->setRequest( $fauxRequest );
		// The request URL is required to be set, as it is used by SpecialInvestigate::alterForm.
		$fauxRequest->setRequestURL(
			$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::CanonicalServer ) .
			"Special:Investigate"
		);
		// Execute the special page and get the HTML output.
		[ $html, $response ] = $this->executeSpecialPage( '', $fauxRequest, null, $testCheckUser );
		$this->assertSame(
			'', $html,
			'The form should not be displayed after submitting the form using POST, as it causes a redirect.'
		);
		/** @var $response FauxResponse */
		$this->assertNotEmpty(
			$response->getHeader( 'Location' ),
			'The response should be a redirect after submitting the form using POST.'
		);
	}

	public function addDBDataOnce() {
		// Create two test users that will be referenced in the tests. These are constructed here to avoid creating the
		// users on each test.
		( new TestUser( 'InvestigateTestUser1' ) )->getUser();
		( new TestUser( 'InvestigateTestUser2' ) )->getUser();
	}
}
