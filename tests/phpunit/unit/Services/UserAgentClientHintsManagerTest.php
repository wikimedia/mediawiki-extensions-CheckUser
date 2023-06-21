<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @coversDefaultClass \MediaWiki\CheckUser\Services\UserAgentClientHintsManager
 */
class UserAgentClientHintsManagerTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;

	/**
	 * @covers ::getMapIdByType
	 * @dataProvider provideValidTypes
	 */
	public function testGetMapIdByType( string $type, int $expectedMapId ) {
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$this->assertSame(
			$expectedMapId,
			$objectToTest->getMapIdByType( $type ),
			'::getMapIdType did not return the correct map ID'
		);
	}

	public static function provideValidTypes() {
		return [
			'Revision type' => [ 'revision', 0 ],
		];
	}
}
