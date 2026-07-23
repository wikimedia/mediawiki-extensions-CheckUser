<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\CheckUser\Widgets;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\Widgets\CIDRCalculator;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Unit\HtmlAssertionHelperTrait;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Widgets\CIDRCalculator
 */
class CIDRCalculatorTest extends MediaWikiIntegrationTestCase {
	use HtmlAssertionHelperTrait;

	/** @dataProvider provideToString */
	public function testToString( $config, $textToBeInHtml, $textNotToBeInHtml ) {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
		Theme::setSingleton( new BlankTheme() );

		$context = new DerivativeContext( RequestContext::getMain() );
		$objectUnderTest = new CIDRCalculator( $context->getOutput(), $config );

		// Use type casting to call __toString and then test that too. That method calls all of the other methods
		// in the class we are testing.
		$html = (string)$objectUnderTest;

		// Check the modules needed for the calculator JS code are added
		$this->assertContains( 'ext.checkUser', $context->getOutput()->getModules() );
		$this->assertContains( 'ext.checkUser.styles', $context->getOutput()->getModuleStyles() );

		// Check that the HTML produced for the calculator is as expected
		$panelLayoutHtml = $this->assertSelectorMatchesOneElement( $html, '#mw-checkuser-cidrform' );
		$this->assertSelectorMatchesOneElement( $panelLayoutHtml, '.mw-checkuser-cidr-iplist' );
		$this->assertSelectorMatchesOneElement( $panelLayoutHtml, '.mw-checkuser-cidr-res' );
		$resultLabelHtml = $this->assertSelectorMatchesOneElement( $panelLayoutHtml, '.mw-checkuser-cidr-res-label' );
		$this->assertStringContainsString( '(checkuser-cidr-res', $resultLabelHtml );
		$this->assertSelectorMatchesOneElement( $panelLayoutHtml, '.mw-checkuser-cidr-tool-links' );
		$this->assertSelectorMatchesOneElement( $panelLayoutHtml, '.mw-checkuser-cidr-ipnote' );

		// Check that text snippets in $textToBeInHtml are in the HTML of the panel
		foreach ( $textToBeInHtml as $textSnippet ) {
			$this->assertStringContainsString( $textSnippet, $panelLayoutHtml );
		}

		// Check that text snippets in $textNotToBeInHtml are not in the HTML of the panel
		foreach ( $textNotToBeInHtml as $textSnippet ) {
			$this->assertStringNotContainsString( $textSnippet, $panelLayoutHtml );
		}
	}

	public static function provideToString() {
		return [
			'Config left as the defaults' => [ [], [ '(checkuser-cidr-label)' ], [] ],
			'Config defines calculator widget as collapsable' => [
				[ 'collapsable' => true ], [ '(checkuser-cidr-label)', 'collapsibleFieldsetLayout' ], [],
			],
			'Config defines has having no wrapper legend text' => [
				[ 'wrapperLegend' => false ], [], [ '(checkuser-cidr-label)', 'fieldsetLayout' ],
			],
		];
	}
}
