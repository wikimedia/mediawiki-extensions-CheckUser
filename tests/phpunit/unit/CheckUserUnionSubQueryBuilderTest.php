<?php

namespace MediaWiki\CheckUser\Tests\Unit;

use IDatabase;
use MediaWiki\CheckUser\CheckUserUnionSubQueryBuilder;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSubQueryBuilder
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSubQueryBuilder
 */
class CheckUserUnionSubQueryBuilderTest extends MediaWikiUnitTestCase {
	public function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject(
			new CheckUserUnionSubQueryBuilder( $this->createMock( IDatabase::class ) )
		);
	}

	public function testUpdateLastAlias() {
		$object = $this->setUpObject();
		$object->updateLastAlias( 'testing' );
		$this->assertSame(
			'testing',
			$object->lastAlias,
			'updateLastAlias() did not correctly update the last alias'
		);
	}

	public function testGetLastAlias() {
		$object = $this->setUpObject();
		$object->lastAlias = 'testing';
		$this->assertSame(
			'testing',
			$object->getLastAlias( 'testing' ),
			'getLastAlias() did not correctly return the last alias'
		);
	}
}
