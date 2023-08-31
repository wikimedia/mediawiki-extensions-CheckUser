<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWikiUnitTestCase;

/**
 * Test class for CheckUserGetIPsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class CheckUserGetIPsPagerTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideGetQueryInfoThrowsExceptionOnReadNew */
	public function testGetQueryInfoThrowsExceptionOnReadNew( $table ) {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( InvalidArgumentException::class );
		$object->getQueryInfo( $table );
	}

	public static function provideGetQueryInfoThrowsExceptionOnReadNew() {
		return [
			'cu_log_event table' => [ CheckUserQueryInterface::LOG_EVENT_TABLE ],
			'cu_private_event table' => [ CheckUserQueryInterface::PRIVATE_LOG_EVENT_TABLE ],
		];
	}

	public function testGetIndexField() {
		$object = $this->getMockBuilder( CheckUserGetIPsPager::class )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->assertSame(
			'last',
			$object->getIndexField(),
			'::getIndexField did not return the expected value.'
		);
	}
}
