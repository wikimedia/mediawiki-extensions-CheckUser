<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\CheckUserPagerNavigationBuilder;
use MediaWiki\Extension\CheckUser\Services\TokenManager;
use MediaWiki\Html\FormOptions;
use MediaWiki\Tests\Unit\HtmlAssertionHelperTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\CheckUserPagerNavigationBuilder
 */
class CheckUserPagerNavigationBuilderTest extends MediaWikiIntegrationTestCase {
	use HtmlAssertionHelperTrait;

	public function testMakeLink() {
		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'limit', '' );
		$opts->add( 'dir', '' );
		$opts->add( 'offset', '' );
		$opts->add( 'wpHideTemporaryAccounts', true );

		$opts->setValue( 'reason', 'testing reason' );

		$context = RequestContext::getMain();
		$objectUnderTest = TestingAccessWrapper::newFromObject( new CheckUserPagerNavigationBuilder(
			$context,
			$this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ),
			$context->getCsrfTokenSet(),
			$context->getRequest(),
			$opts,
			UserIdentityValue::newAnonymous( '1.2.3.4' )
		) );

		$actualLinkHtml = $objectUnderTest->makeLink(
			[ 'dir' => 'prev', 'offset' => '20250504050405|1', 'limit' => 123 ],
			'mw-prevlink',
			'prev text',
			'tooltip',
			'prev'
		);

		$formHtml = $this->assertSelectorMatchesOneElement( $actualLinkHtml, '.mw-checkuser-paging-links-form' );
		$form = DOMUtils::parseHTML( $formHtml );

		$submitButtonHtml = $this->assertSelectorMatchesOneElement( $formHtml, '.mw-checkuser-paging-links' );
		$this->assertStringContainsString( 'prev text', $submitButtonHtml );

		// Expect that the paging links have the temporary accounts hide filter, so that the current value persists
		// across pages
		$hideTemporaryAccountsField = DOMCompat::querySelector( $form, 'input[name="wpHideTemporaryAccounts"]' );
		$this->assertNotNull( $hideTemporaryAccountsField );
		$this->assertSame( '1', $hideTemporaryAccountsField->getAttribute( 'value' ) );

		/** @var TokenManager $tokenManager */
		$tokenManager = $this->getServiceContainer()->get( 'CheckUserTokenManager' );

		$tokenField = DOMCompat::querySelector( $form, '.mw-checkuser-paging-links-token' );
		$actualToken = $tokenField->getAttribute( 'value' );
		$this->assertArrayEquals(
			[
				'period' => 0, 'limit' => 123, 'reason' => 'testing reason', 'offset' => '20250504050405|1',
				'dir' => 'prev', 'user' => '1.2.3.4',
			],
			$tokenManager->decode( $context->getRequest()->getSession(), $actualToken ),
			false,
			true,
			'CheckUser JWT token for paging returned unexpected data'
		);

		$editTokenField = DOMCompat::querySelector( $form, '.mw-checkuser-paging-links-edit-token' );
		$this->assertTrue(
			$context->getCsrfTokenSet()->matchToken( $editTokenField->getAttribute( 'value' ) ),
			'wpEditToken field had an invalid token specified'
		);
	}

	public function testMakeLinkWhenNoQueryProvided() {
		$opts = new FormOptions();

		$context = RequestContext::getMain();
		$objectUnderTest = TestingAccessWrapper::newFromObject( new CheckUserPagerNavigationBuilder(
			$context,
			$this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ),
			$context->getCsrfTokenSet(),
			$context->getRequest(),
			$opts,
			UserIdentityValue::newAnonymous( '1.2.3.4' )
		) );

		$actualLinkHtml = $objectUnderTest->makeLink( null, 'mw-prevlink', 'prev text', 'tooltip', 'prev' );

		$pagingForm = DOMCompat::querySelector(
			DOMUtils::parseHTML( $actualLinkHtml ),
			'.mw-checkuser-paging-links-form'
		);
		$this->assertNull( $pagingForm, 'No paging form should be added if there $query param was null' );

		$pagingLink = DOMCompat::querySelector(
			DOMUtils::parseHTML( $actualLinkHtml ),
			'span.mw-prevlink'
		);
		$this->assertNotNull( $pagingLink, 'The paging link should be rendered as text instead of a link' );
	}
}
