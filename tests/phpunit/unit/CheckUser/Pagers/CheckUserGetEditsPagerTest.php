<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserGetEditsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager
 */
class CheckUserGetEditsPagerTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideIsNavigationBarShown */
	public function testIsNavigationBarShown( $numRows, $shown ) {
		$object = $this->getMockBuilder( CheckUserGetEditsPager::class )
			->onlyMethods( [ 'getNumRows' ] )
			->disableOriginalConstructor()
			->getMock();
		$object->expects( $this->once() )
			->method( 'getNumRows' )
			->willReturn( $numRows );
		$object = TestingAccessWrapper::newFromObject( $object );
		if ( $shown ) {
			$this->assertTrue(
				$object->isNavigationBarShown(),
				'Navigation bar is not showing when it\'s supposed to'
			);
		} else {
			$this->assertFalse(
				$object->isNavigationBarShown(),
				'Navigation bar is showing when it is not supposed to'
			);
		}
	}

	public static function provideIsNavigationBarShown() {
		return [
			[ 0, false ],
			[ 2, true ]
		];
	}
}
