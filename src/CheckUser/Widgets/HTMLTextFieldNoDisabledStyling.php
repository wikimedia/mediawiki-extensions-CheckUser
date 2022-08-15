<?php

namespace MediaWiki\CheckUser\CheckUser\Widgets;

use HTMLTextField;

class HTMLTextFieldNoDisabledStyling extends HTMLTextField {

	/**
	 * @inheritDoc
	 */
	protected function getInputWidget( $params ) {
		// So that the disabled state does not grey out the
		// text input as that does not make sense in this context
		return new TextInputWidgetNoDisabledStyling( $params );
	}
}
