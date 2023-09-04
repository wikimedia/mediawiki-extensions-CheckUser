<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers\DeAbstractedCheckUserPagerTest;
use MediaWikiUnitTestCase;
use Message;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for AbstractCheckUserPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager
 */
class AbstractCheckUserPagerTest extends MediaWikiUnitTestCase {
	public function testGetTimeRangeStringFirstAndLastEqual() {
		$object = $this->getMockBuilder( DeAbstractedCheckUserPagerTest::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getFormattedTimestamp' ] )
			->getMock();
		$object->expects( $this->once() )
			->method( 'getFormattedTimestamp' )
			->willReturn( 'mock_formatted_timestamp' );
		$object = TestingAccessWrapper::newFromObject( $object );
		$this->assertSame(
			'mock_formatted_timestamp',
			$object->getTimeRangeString( '1653077137', '1653077137' ),
			'Return value of AbstractCheckUserPager::getTimeRangeString was not as expected.'
		);
	}

	public function testGetTimeRangeStringFirstAndLastNotEqual() {
		$object = $this->getMockBuilder( DeAbstractedCheckUserPagerTest::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'msg' ] )
			->getMock();
		// Mock the Message class to assert that the message is constructed correctly.
		$mockMessage = $this->createMock( Message::class );
		$mockMessage->expects( $this->once() )
			->method( 'dateTimeParams' )
			->with( '1653047635', '1653077137' )
			->willReturnSelf();
		$mockMessage->expects( $this->once() )
			->method( 'escaped' )
			->willReturn( 'mock_formatted_timestamp' );
		$object->expects( $this->once() )
			->method( 'msg' )
			->with( 'checkuser-time-range' )
			->willReturn( $mockMessage );
		$object = TestingAccessWrapper::newFromObject( $object );
		$this->assertSame(
			'mock_formatted_timestamp',
			$object->getTimeRangeString( '1653047635', '1653077137' ),
			'Return value of AbstractCheckUserPager::getTimeRangeString was not as expected.'
		);
	}
}
