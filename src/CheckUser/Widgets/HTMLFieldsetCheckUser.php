<?php

namespace MediaWiki\CheckUser\CheckUser\Widgets;

use CollapsibleFieldsetLayout;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;
use OOUI\Widget;
use OOUIHTMLForm;

class HTMLFieldsetCheckUser extends OOUIHTMLForm {

	/**
	 * This returns the html but not wrapped in a form
	 * element, so that it can be optionally added by SpecialCheckUser.
	 *
	 * @inheritDoc
	 */
	public function wrapForm( $html ) {
		if ( is_string( $this->mWrapperLegend ) ) {
			$phpClass = $this->mCollapsible ? CollapsibleFieldsetLayout::class : FieldsetLayout::class;
			$content = new $phpClass( [
				'label' => $this->mWrapperLegend,
				'collapsed' => $this->mCollapsed,
				'items' => [
					new Widget( [
						'content' => new HtmlSnippet( $html )
					] ),
				],
			] + Element::configFromHtmlAttributes( $this->mWrapperAttributes ) );
		} else {
			$content = new HtmlSnippet( $html );
		}

		// Include a wrapper for style, if requested.
		return new PanelLayout( [
			'classes' => [ 'mw-htmlform-ooui-wrapper' ],
			'expanded' => false,
			'padded' => $this->mWrapperLegend !== false,
			'framed' => $this->mWrapperLegend !== false,
			'content' => $content,
		] );
	}
}
