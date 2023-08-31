<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use InvalidArgumentException;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWikiUnitTestCase;

/**
 * Test class for CheckUserGetUsersPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 */
class CheckUserGetUsersPagerTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideGetQueryInfoThrowsExceptionOnReadNew */
	public function testGetQueryInfoThrowsExceptionOnReadNew( $table ) {
		$object = $this->getMockBuilder( CheckUserGetUsersPager::class )
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
}
