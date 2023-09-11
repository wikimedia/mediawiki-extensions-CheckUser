<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Tests\Unit\Libs\Rdbms\AddQuoterMock;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

abstract class CheckUserPagerCommonUnitTest extends MediaWikiUnitTestCase {

	/**
	 * Gets the name of the Pager class currently under test.
	 *
	 * @return string The pager class name
	 */
	abstract protected function getPagerClass(): string;

	public function commonTestGetQueryInfo( $target, $xfor, $table, $tableSpecificQueryInfo, $expectedQueryInfo ) {
		$methodsToMock = [ 'getQueryInfoForCuChanges', 'getQueryInfoForCuLogEvent', 'getQueryInfoForCuPrivateEvent' ];
		$object = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( $methodsToMock )
			->getMock();
		// Mock the relevant method that returns the table-specific query info for ::getQueryInfo
		$methodToMock = $methodsToMock[array_search( $table, CheckUserQueryInterface::RESULT_TABLES )];
		$object->expects( $this->once() )
			->method( $methodToMock )
			->willReturn( $tableSpecificQueryInfo );
		// Expect the other methods that return table-specific query info to not be called.
		foreach ( $methodsToMock as $method ) {
			if ( $method !== $methodToMock ) {
				$object->expects( $this->never() )
					->method( $method );
			}
		}
		$object->mDb = new AddQuoterMock();
		$object = TestingAccessWrapper::newFromObject( $object );
		$object->target = $target;
		$object->xfor = $xfor;
		$this->assertArrayEquals(
			$expectedQueryInfo,
			$object->getQueryInfo( $table ),
			false,
			true,
			'::getQueryInfo did not return the expected result.'
		);
	}

	public function commonGetQueryInfoForTableSpecificMethod( $methodName, $propertiesToSet, $expectedQueryInfo ) {
		$object = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
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
}
