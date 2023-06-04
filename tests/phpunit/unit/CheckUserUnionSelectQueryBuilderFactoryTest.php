<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use IDatabase;
use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CommentStore\CommentStore;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilderFactory
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
 */
class CheckUserUnionSelectQueryBuilderFactoryTest extends MediaWikiUnitTestCase {
	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			new CheckUserUnionSelectQueryBuilderFactory( $this->createMock( CommentStore::class ) )
		);
	}

	/**
	 * @covers \MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilderFactory
	 */
	public function testNewObjectMethod() {
		$this->assertInstanceOf(
			CheckUserUnionSelectQueryBuilder::class,
			$this->setUpObject()->newCheckUserSelectQueryBuilder( $this->createMock( IDatabase::class ) ),
			'Factory did not create the correct object'
		);
	}
}
