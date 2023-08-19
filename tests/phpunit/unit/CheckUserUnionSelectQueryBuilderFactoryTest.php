<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\CheckUserUnionSelectQueryBuilder;
use MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CommentStore\CommentStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSelectQueryBuilderFactory
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory
 */
class CheckUserUnionSelectQueryBuilderFactoryTest extends MediaWikiUnitTestCase {
	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		return TestingAccessWrapper::newFromObject(
			new CheckUserUnionSelectQueryBuilderFactory( $this->createMock( CommentStore::class ) )
		);
	}

	public function testNewObjectMethod() {
		$this->assertInstanceOf(
			CheckUserUnionSelectQueryBuilder::class,
			$this->setUpObject()->newCheckUserSelectQueryBuilder( $this->createMock( IDatabase::class ) ),
			'Factory did not create the correct object'
		);
	}
}
