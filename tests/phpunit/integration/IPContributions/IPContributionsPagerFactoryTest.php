<?php

namespace MediaWiki\CheckUser\Tests\Integration\IPContributions;

use MediaWiki\CheckUser\IPContributions\IPContributionsPager;
use MediaWiki\Context\RequestContext;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\IPContributions\IPContributionsPagerFactory
 * @group CheckUser
 * @group Database
 */
class IPContributionsPagerFactoryTest extends MediaWikiIntegrationTestCase {
	public function testCreatePager() {
		// Tests that the factory creates an IPContributionsPager instance and does not throw an exception.
		$this->assertInstanceOf(
			IPContributionsPager::class,
			$this->getServiceContainer()->get( 'CheckUserIPContributionsPagerFactory' )
				->createPager( RequestContext::getMain(), [], $this->getTestUser()->getUser() ),
			'CheckUserIPContributionsPagerFactory::createPager should create an IPContributionsPager instance'
		);
	}
}
