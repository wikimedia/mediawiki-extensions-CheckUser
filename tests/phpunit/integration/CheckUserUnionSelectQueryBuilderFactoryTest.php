<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilderFactory
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
 */
class CheckUserUnionSelectQueryBuilderFactoryTest extends MediaWikiIntegrationTestCase {
	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->get( 'CheckUserUnionSelectQueryBuilderFactory' )
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
	 */
	public function testNewObjectMethod() {
		$this->assertInstanceOf(
			CheckUserUnionSelectQueryBuilder::class,
			$this->setUpObject()->newCheckUserSelectQueryBuilder( $this->getDb() ),
			'Factory did not create the correct object'
		);
	}
}
