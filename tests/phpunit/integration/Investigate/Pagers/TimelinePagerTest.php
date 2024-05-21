<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use TestUser;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePager
 * @group CheckUser
 * @group Database
 */
class TimelinePagerTest extends MediaWikiIntegrationTestCase {

	use TempUserTestTrait;

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
				[ 'timestamp' => '20240405060708' ],
				// The result of TimelineRowFormatter::getFormattedRowItems
				[ 'info' => [], 'links' => [] ],
				// The value of the $lastDateHeader property
				null,
				// The expected HTML output
				'<h4>5 April 2024</h4><ul><li></li>',
			],
			'Row with no items and no date header' => [
				[ 'timestamp' => '20240405060708' ], [ 'info' => [], 'links' => [] ], '5 April 2024', '<li></li>',
			],
			'Row with items and different date header' => [
				[ 'timestamp' => '20240405060708' ],
				[ 'info' => [ 'info1', 'info2' ], 'links' => [ 'link1', 'link2' ] ],
				'4 April 2024', '</ul><h4>5 April 2024</h4><ul><li>link1 link2 . . info1 . . info2</li>',
			],
			'Invalid formatted row items' => [ [ 'timestamp' => '20240405060708' ], [], null, '' ],
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
		// Also tests that the constructor correctly generates the filteredTargets property.
		// This is not tested in the ::testReallyDoQuery test because the filteredTargets property is manually set.
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

	/** @dataProvider provideReallyDoQuery */
	public function testReallyDoQuery( $offset, $limit, $order, $filteredTargets, $expectedRows ) {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->filteredTargets = $filteredTargets;
		$this->assertArrayEquals(
			$expectedRows,
			iterator_to_array( $objectUnderTest->reallyDoQuery( $offset, $limit, $order ) ),
			true,
			false,
			'::reallyDoQuery did not return the expected rows'
		);
	}

	public static function provideReallyDoQuery() {
		return [
			'Offset unset, limit 1, order ASC, InvestigateTestUser1 as target' => [
				// The $offset argument to ::reallyDoQuery
				null,
				// The $limit argument to ::reallyDoQuery
				1,
				// The $order argument to ::reallyDoQuery
				IndexPager::QUERY_ASCENDING,
				// The value of the $filteredTargets property
				[ 'InvestigateTestUser1' ],
				// The expected rows returned by ::reallyDoQuery
				[ (object)[
					'timestamp' => '20230405060708',
					'namespace' => '0',
					'title' => 'Foo_Page',
					'actiontext' => '',
					'minor' => '0',
					'page_id' => '1',
					'type' => '1',
					'this_oldid' => '0',
					'last_oldid' => '0',
					'ip' => '1.2.3.4',
					'xff' => '0',
					'agent' => 'foo user agent',
					'id' => '1',
					'user' => '1',
					'user_text' => 'InvestigateTestUser1',
					'comment_text' => 'Foo comment',
					'comment_data' => null,
					'actor' => '1',
				] ],
			],
			'Offset set, limit 1, order DESC, InvestigateTestUser1 as target' => [
				'20230405060710|1', 1, IndexPager::QUERY_DESCENDING, [ 'InvestigateTestUser1' ],
				[ (object)[
					'timestamp' => '20230405060708',
					'namespace' => '0',
					'title' => 'Foo_Page',
					'actiontext' => '',
					'minor' => '0',
					'page_id' => '1',
					'type' => '1',
					'this_oldid' => '0',
					'last_oldid' => '0',
					'ip' => '1.2.3.4',
					'xff' => '0',
					'agent' => 'foo user agent',
					'id' => '1',
					'user' => '1',
					'user_text' => 'InvestigateTestUser1',
					'comment_text' => 'Foo comment',
					'comment_data' => null,
					'actor' => '1',
				] ],
			],
			'No rows for IP and invalid user target' => [
				null, 10, IndexPager::QUERY_ASCENDING, [ '8.9.6.5', 'InvalidUser1' ], [],
			],
			'All targets filtered out' => [ null, 10, IndexPager::QUERY_ASCENDING, [], [] ],
		];
	}

	public function addDBDataOnce() {
		// Create a test user for use below in creating testing cu_changes entries.
		$testUser = ( new TestUser( 'InvestigateTestUser1' ) )->getUser();
		// Clear the cu_changes and cu_log_event tables to avoid log entries created by the test users being created
		// affecting the tests.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event' ] );

		// Automatic temp user creation cannot be enabled
		// if actor IDs are being created for IPs.
		$this->disableAutoCreateTempUser();

		$testData = [
			[
				'cuc_actor'      => $testUser->getActorId(),
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
				'cuc_timestamp'  => '20230405060708',
			], [
				'cuc_actor'      => $testUser->getActorId(),
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
				'cuc_timestamp'  => '20230405060710',
			], [
				'cuc_actor'      => $testUser->getActorId(),
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
				'cuc_timestamp'  => '20230405060710',
			],
		];

		$commonData = [
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_actiontext' => '',
			'cuc_comment_id' => $this->getServiceContainer()->getCommentStore()
				->createComment( $this->getDb(), 'Foo comment' )->id,
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
		];

		foreach ( $testData as &$row ) {
			$row['cuc_timestamp'] = $this->db->timestamp( $row['cuc_timestamp'] );
			$row += $commonData;
		}

		$this->db->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $testData )
			->execute();
	}
}
