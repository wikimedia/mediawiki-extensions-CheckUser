<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\CheckUser\Widgets;

use MediaWiki\Extension\CheckUser\CheckUser\Widgets\CIDRCalculatorResultBox;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\Widgets\CIDRCalculatorResultBox
 */
class CIDRCalculatorResultBoxTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideIsAlwaysReadOnly */
	public function testIsAlwaysReadOnly( $config ) {
		$resultBox = TestingAccessWrapper::newFromObject( new CIDRCalculatorResultBox( $config ) );
		$this->assertSame(
			'readonly',
			$resultBox->input->getAttribute( 'readonly' ),
			'The input should always have the readonly attribute set.'
		);
	}

	public static function provideIsAlwaysReadOnly() {
		return [
			'Read-only is not set in the caller\'s config' => [
				[],
			],
			'Read-only is set in the caller\'s config' => [
				[ 'readOnly' => true ],
			],
		];
	}

	public function testSetReadOnly() {
		$resultBox = TestingAccessWrapper::newFromObject( new CIDRCalculatorResultBox( [] ) );
		$this->assertSame(
			$resultBox->object,
			$resultBox->setReadOnly( false ),
			'setReadOnly should return the result box object'
		);
		$this->assertSame(
			'readonly',
			$resultBox->input->getAttribute( 'readonly' ),
			'The input should not have been non-read-only by the setReadOnly call.'
		);
	}
}
