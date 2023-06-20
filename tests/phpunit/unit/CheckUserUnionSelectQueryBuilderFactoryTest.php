<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use IDatabase;
use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CommentStore\CommentStore;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilderFactory
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory
 * @coversDefaultClass \MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory
 */
class CheckUserUnionSelectQueryBuilderFactoryTest extends MediaWikiUnitTestCase {
	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			new CheckUserUnionSelectQueryBuilderFactory( $this->createMock( CommentStore::class ) )
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory
	 */
	public function testNewObjectMethod() {
		$this->assertInstanceOf(
			CheckUserUnionSelectQueryBuilder::class,
			$this->setUpObject()->newCheckUserSelectQueryBuilder( $this->createMock( IDatabase::class ) ),
			'Factory did not create the correct object'
		);
	}
}
