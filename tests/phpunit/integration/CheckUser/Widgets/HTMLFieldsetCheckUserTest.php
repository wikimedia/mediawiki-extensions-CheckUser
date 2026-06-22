<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\CheckUser\Widgets;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\PanelLayout;
use OOUI\Theme;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser
 */
class HTMLFieldsetCheckUserTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// OOUI requires a theme to render widgets.
		Theme::setSingleton( new BlankTheme() );
	}

	private function newForm(): HTMLFieldsetCheckUser {
		$context = new DerivativeContext( RequestContext::getMain() );

		return new HTMLFieldsetCheckUser( [], $context );
	}

	public function testWrapFormWithoutLegendReturnsPanelLayout(): void {
		$form = $this->newForm();
		$form->outerClass = 'my-outer-class';

		$result = $form->wrapForm( '<p>CONTENT</p>' );

		$this->assertInstanceOf( PanelLayout::class, $result );

		$html = (string)$result;
		$this->assertStringContainsString( 'CONTENT', $html );
		$this->assertStringContainsString( 'my-outer-class', $html );
		$this->assertStringContainsString( 'mw-htmlform-ooui-wrapper', $html );
	}

	public function testWrapFormWithLegendUsesFieldsetLayout(): void {
		$form = $this->newForm();
		$form->setWrapperLegend( 'My legend' );

		$result = $form->wrapForm( '<p>CONTENT</p>' );

		$this->assertInstanceOf( PanelLayout::class, $result );

		$html = (string)$result;
		$this->assertStringContainsString( 'My legend', $html );
		$this->assertStringContainsString( 'CONTENT', $html );
	}

	public function testWrapFormWithCollapsibleLegendUsesCollapsibleFieldsetLayout(): void {
		$form = $this->newForm();
		$form->setWrapperLegend( 'My legend' );
		$form->setCollapsibleOptions( true );

		$result = $form->wrapForm( '<p>CONTENT</p>' );

		$this->assertInstanceOf( PanelLayout::class, $result );

		$html = (string)$result;
		$this->assertStringContainsString( 'My legend', $html );
		$this->assertStringContainsString( 'CONTENT', $html );
	}
}
