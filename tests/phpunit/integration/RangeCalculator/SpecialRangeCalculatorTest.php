<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\RangeCalculator;

use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\RangeCalculator\SpecialRangeCalculator
 */
class SpecialRangeCalculatorTest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'RangeCalculator' );
	}

	public function testExecute(): void {
		[ $html ] = $this->executeSpecialPage();
		$this->assertStringContainsString( 'mw-checkuser-cidrform', $html );
		$this->assertStringContainsString( '(rangecalculator-summary)', $html );
	}
}
