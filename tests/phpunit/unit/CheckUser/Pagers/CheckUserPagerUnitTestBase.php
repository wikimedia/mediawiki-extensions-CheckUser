<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use LogicException;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

abstract class CheckUserPagerUnitTestBase extends MediaWikiUnitTestCase {

	/**
	 * Gets the name of the Pager class currently under test.
	 *
	 * @return class-string<AbstractCheckUserPager> The pager class name
	 */
	abstract protected function getPagerClass(): string;

	public function commonGetQueryInfoForTableSpecificMethod( $methodName, $propertiesToSet, $expectedQueryInfo ) {
		$object = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getConfig' ] )
			->getMock();

		// While the user agent table migration is in progress, we should set the value
		// as reading new. This has no effect on the associated unit tests and is tested
		// via integration tests
		$object->method( 'getConfig' )
			->willReturn( new HashConfig( [ 'CheckUserUserAgentTableMigrationStage' => SCHEMA_COMPAT_READ_NEW ] ) );

		$object = TestingAccessWrapper::newFromObject( $object );
		foreach ( $propertiesToSet as $propertyName => $propertyValue ) {
			$object->$propertyName = $propertyValue;
		}
		$this->assertArrayContains(
			$expectedQueryInfo,
			$object->$methodName(),
			"The ::$methodName response was not as expected."
		);
	}

	public function testGetQueryInfoWithNoProvidedTableThrowsException() {
		/** @var $objectUnderTest AbstractCheckUserPager */
		$objectUnderTest = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( LogicException::class );
		$objectUnderTest->getQueryInfo();
	}
}
