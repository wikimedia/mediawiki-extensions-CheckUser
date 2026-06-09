<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\CheckUser\Widgets;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\CollapsibleFieldsetLayout;
use MediaWiki\Output\OutputPage;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\LabelWidget;
use OOUI\MultilineTextInputWidget;
use OOUI\PanelLayout;
use OOUI\Widget;

class CIDRCalculator {

	private readonly bool $mCollapsible;

	/**
	 * Text to be shown as the legend for the calculator.
	 *
	 * @var string|bool
	 */
	private readonly string|bool $mWrapperLegend;

	private readonly array $mWrapperAttributes;

	private readonly bool $mCollapsed;

	private readonly bool $mShowFrame;

	/**
	 * @param OutputPage $out
	 * @param array $config Configuration array
	 */
	public function __construct(
		private readonly OutputPage $out,
		array $config = []
	) {
		$out->enableOOUI();
		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'ext.checkUser.styles' );

		$this->mCollapsible = $config['collapsible'] ?? $config['collapsable'] ?? false;
		$this->mWrapperLegend = $config['wrapperLegend'] ?? $out->msg( 'checkuser-cidr-label' )->text();
		$this->mWrapperAttributes = $config['wrapperAttributes'] ?? [];
		$this->mCollapsed = $config['collapsed'] ?? false;
		$this->mShowFrame = $config['showFrame'] ?? ( $this->mWrapperLegend !== false );
	}

	/**
	 * @return string HTML
	 */
	public function getHtml(): string {
		$items = [];
		$items[] = new MultilineTextInputWidget( [
			'classes' => [ 'mw-checkuser-cidr-iplist' ],
			'rows' => 5,
			'dir' => 'ltr',
		] );
		$input = new CIDRCalculatorResultBox( [
			'size' => 35,
			'classes' => [ 'mw-checkuser-cidr-res' ],
			'name' => 'mw-checkuser-cidr-res',
		] );
		$items[] = new LabelWidget( [
			'input' => $input,
			'classes' => [ 'mw-checkuser-cidr-res-label' ],
			'label' => $this->out->msg( 'checkuser-cidr-res' )->text(),
		] );
		$items[] = $input;
		$items[] = new LabelWidget( [
			'classes' => [
				'mw-checkuser-cidr-tool-links',
				'mw-checkuser-cidr-tool-links-hidden',
			],
		] );
		$items[] = new Element( [
			'tagName' => 'p',
			'classes' => [ 'mw-checkuser-cidr-ipnote' ],
		] );
		if ( is_string( $this->mWrapperLegend ) ) {
			$attributes = [
				'label' => $this->mWrapperLegend,
				'collapsed' => $this->mCollapsed,
				'items' => $items,
			] + Element::configFromHtmlAttributes( $this->mWrapperAttributes );
			if ( $this->mCollapsible ) {
				$content = new CollapsibleFieldsetLayout( $attributes );
			} else {
				$content = new FieldsetLayout( $attributes );
			}
		} else {
			$content = new Widget( [
				'content' => $items,
			] );
		}

		$panelLayout = ( new PanelLayout( [
			'classes' => [
				'mw-checkuser-cidrform',
				'mw-checkuser-cidr-calculator-hidden',
			],
			'id' => 'mw-checkuser-cidrform',
			'expanded' => false,
			'padded' => $this->mShowFrame,
			'framed' => $this->mShowFrame,
			'content' => $content,
		] ) )->toString();

		return Html::rawElement(
			'noscript',
			[],
			Html::element( 'p', [], $this->out->msg( 'checkuser-cidr-no-script-message' )->text() )
		) . $panelLayout;
	}

	/**
	 * @return string HTML
	 */
	public function __toString(): string {
		return $this->getHtml();
	}
}
