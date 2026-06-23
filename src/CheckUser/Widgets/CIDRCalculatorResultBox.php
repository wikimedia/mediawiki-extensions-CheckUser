<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\CheckUser\Widgets;

use OOUI\TextInputWidget;

class CIDRCalculatorResultBox extends TextInputWidget {

	/**
	 * @param array $config
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );
		$this->input->setAttributes( [ 'readonly' => 'readonly' ] );
	}

	/**
	 * Because this widget is always readonly
	 * by definition this does nothing.
	 *
	 * @param bool $readOnly unused
	 * @return $this
	 */
	public function setReadOnly( $readOnly ) {
		// Ignore calls to setReadOnly as it should always be readonly.
		return $this;
	}
}
