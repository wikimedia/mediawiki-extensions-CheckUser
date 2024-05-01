<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Pager\IndexPager;
use MediaWikiIntegrationTestCase;
use RequestContext;
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
			$overrides['hookRunner'] ?? $this->getServiceContainer()->get( 'CheckUserHookRunner' ),
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
