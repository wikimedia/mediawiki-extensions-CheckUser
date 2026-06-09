<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\RangeCalculator;

use MediaWiki\Extension\CheckUser\CheckUser\Widgets\CIDRCalculator;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialRangeCalculator extends SpecialPage {

	public function __construct() {
		parent::__construct( 'RangeCalculator' );
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$out = $this->getOutput();
		$out->addHTML( ( new CIDRCalculator( $out, [
			'showFrame' => false,
		] ) )->getHtml() );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	protected function getGroupName(): string {
		return 'wiki';
	}
}
