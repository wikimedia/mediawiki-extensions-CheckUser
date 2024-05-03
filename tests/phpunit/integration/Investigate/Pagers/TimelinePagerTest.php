<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Pager\IndexPager;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePager
 * @group CheckUser
 * @group Database
 */
class TimelinePagerTest extends MediaWikiIntegrationTestCase {

	private function getObjectUnderTest( array $overrides = [] ) {
		return TestingAccessWrapper::newFromObject( new TimelinePager(
			RequestContext::getMain(),
			$overrides['linkRenderer'] ?? $this->getServiceContainer()->getLinkRenderer(),
			$overrides['hookRuner'] ?? $this->getServiceContainer()->get( 'CheckUserHookRunner' ),
			$overrides['tokenQueryManager'] ?? $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ),
			$overrides['durationManager'] ?? $this->getServiceContainer()->get( 'CheckUserDurationManager' ),
			$overrides['timelineService'] ?? $this->getServiceContainer()->get( 'CheckUserTimelineService' ),
			$overrides['timelineRowFormatterFactory'] ?? $this->getServiceContainer()
				->get( 'CheckUserTimelineRowFormatterFactory' )->createRowFormatter(
					RequestContext::getMain()->getUser(), RequestContext::getMain()->getLanguage()
				),
			$overrides['logger'] ?? LoggerFactory::getInstance( 'CheckUser' )
		) );
	}

	/** @dataProvider provideFormatRow */
	public function testFormatRow( $row, $formattedRowItems, $lastDateHeader, $expectedHtml ) {
		// Temporarily disable the ::onCheckUserFormatRow hook to avoid test failures due to other code defining items
		// for display.
		$this->clearHook( 'CheckUserFormatRow' );
		$mockTimelineRowFormatter = $this->createMock( TimelineRowFormatter::class );
		$mockTimelineRowFormatter->expects( $this->once() )
			->method( 'getFormattedRowItems' )
			->willReturn( $formattedRowItems );
		// Define a mock TimelineService that expects a call to ::formatRow
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->timelineRowFormatter = $mockTimelineRowFormatter;
		$objectUnderTest->lastDateHeader = $lastDateHeader;
		$this->assertSame(
			$expectedHtml,
			$objectUnderTest->formatRow( (object)$row ),
			'::formatRow did not return the expected HTML'
		);
	}

	public static function provideFormatRow() {
		return [
			'Row with no items and date header' => [
				// The $row provided to ::formatRow as an array
				[ 'cuc_timestamp' => '20240405060708' ],
				// The result of TimelineRowFormatter::getFormattedRowItems
				[ 'info' => [], 'links' => [] ],
				// The value of the $lastDateHeader property
				null,
				// The expected HTML output
				'<h4>5 April 2024</h4><ul><li></li>',
			],
			'Row with no items and no date header' => [
				[ 'cuc_timestamp' => '20240405060708' ], [ 'info' => [], 'links' => [] ], '5 April 2024', '<li></li>',
			],
			'Row with items and different date header' => [
				[ 'cuc_timestamp' => '20240405060708' ],
				[ 'info' => [ 'info1', 'info2' ], 'links' => [ 'link1', 'link2' ] ],
				'4 April 2024', '</ul><h4>5 April 2024</h4><ul><li>link1 link2 . . info1 . . info2</li>',
			],
			'Invalid formatted row items' => [ [ 'cuc_timestamp' => '20240405060708' ], [], null, '' ],
		];
	}

	/** @dataProvider provideGetEndBody */
	public function testGetEndBody( $numRows, $expected ) {
		$objectUnderTest = $this->getMockBuilder( TimelinePager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getNumRows' ] )
			->getMock();
		$objectUnderTest->method( 'getNumRows' )
			->willReturn( $numRows );
		$this->assertSame(
			$expected,
			$objectUnderTest->getEndBody(),
			'::getEndBody did not return the expected HTML'
		);
	}

	public function provideGetEndBody() {
		return [
			'No rows' => [ 0, '' ],
			'One row' => [ 1, '</ul>' ],
		];
	}

	public function testGetQueryInfo() {
		$mockTimelineService = $this->createMock( TimelineService::class );
		$mockTimelineService->expects( $this->once() )
			->method( 'getQueryInfo' );
		// Define a mock TimelineService that expects a call to ::getQueryInfo
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->timelineService = $mockTimelineService;
		$objectUnderTest->getQueryInfo();
	}

	public function testReallyDoQueryOnAllExcludedTargets() {
		$tokenQueryManager = $this->getMockBuilder( TokenQueryManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getDataFromRequest' ] )
			->getMock();
		$tokenQueryManager->method( 'getDataFromRequest' )
			->willReturn( [
				'targets' => [ '1.2.3.4' ],
				'exclude-targets' => [ '1.2.3.4' ],
			] );

		$pager = $this->getObjectUnderTest( [
			'tokenQueryManager' => $tokenQueryManager,
		] );
		$actualResult = $pager->reallyDoQuery( '', 50, IndexPager::QUERY_ASCENDING );

		$this->assertSame( 0, $actualResult->numRows() );
	}
}
