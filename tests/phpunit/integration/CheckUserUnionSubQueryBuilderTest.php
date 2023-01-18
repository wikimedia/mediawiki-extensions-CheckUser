<?php

namespace MediaWiki\CheckUser\Tests;

use MediaWiki\CheckUser\CheckUserUnionSubQueryBuilder;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserUnionSubQueryBuilder
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUserUnionSubQueryBuilder
 * @coversDefaultClass \MediaWiki\CheckUser\CheckUserUnionSubQueryBuilder
 */
class CheckUserUnionSubQueryBuilderTest extends MediaWikiIntegrationTestCase {
	public function setUpObject(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject( new CheckUserUnionSubQueryBuilder( $this->getDb() ) );
	}

	/**
	 * @covers ::updateLastAlias
	 */
	public function testUpdateLastAlias() {
		$object = $this->setUpObject();
		$object->updateLastAlias( 'testing' );
		$this->assertSame(
			'testing',
			$object->lastAlias,
			'updateLastAlias() did not correctly update the last alias'
		);
	}

	/**
	 * @covers ::getLastAlias
	 */
	public function testGetLastAlias() {
		$object = $this->setUpObject();
		$object->lastAlias = 'testing';
		$this->assertSame(
			'testing',
			$object->getLastAlias( 'testing' ),
			'updateLastAlias() did not correctly update the last alias'
		);
	}
}
