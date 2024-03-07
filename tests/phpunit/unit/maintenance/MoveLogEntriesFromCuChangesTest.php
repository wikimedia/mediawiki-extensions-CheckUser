<?php

namespace MediaWiki\CheckUser\Tests\Unit\Maintenance;

use MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges;
use MediaWiki\Config\HashConfig;
use MediaWiki\MediaWikiServices;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges
 */
class MoveLogEntriesFromCuChangesTest extends MediaWikiUnitTestCase {

	public function testGetUpdateKey() {
		$objectUnderTest = TestingAccessWrapper::newFromObject( new MoveLogEntriesFromCuChanges() );
		$this->assertSame(
			'MediaWiki\\CheckUser\\Maintenance\\MoveLogEntriesFromCuChanges',
			$objectUnderTest->getUpdateKey(),
			'::getUpdateKey did not return the expected value.'
		);
	}

	/** @dataProvider provideInvalidMigrationStages */
	public function testDoDBUpdatesWithWrongMigrationStage( $migrationStage ) {
		// Mock the config so that the migration stage to test with is set.
		$mockConfig = new HashConfig( [
			'CheckUserEventTablesMigrationStage' => $migrationStage
		] );
		// Mock the service container to return this config to a call to ::getMainConfig
		$mockServiceContainer = $this->createMock( MediaWikiServices::class );
		$mockServiceContainer->method( 'getMainConfig' )
			->willReturn( $mockConfig );
		// Get the object under test and make the getServiceContainer return the mock MediaWikiServices.
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getServiceContainer', 'output' ] )
			->getMock();
		$objectUnderTest->method( 'getServiceContainer' )
			->willReturn( $mockServiceContainer );
		// Expect that doDBUpdates returns false.
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertFalse(
			$objectUnderTest->doDBUpdates(),
			"::doDBUpdates should return false when the migration stage doesn't allow the script to run."
		);
	}

	public static function provideInvalidMigrationStages() {
		return [
			'Reading and writing old' => [ SCHEMA_COMPAT_OLD ],
			'Read new, reading old and writing old' => [ SCHEMA_COMPAT_READ_NEW | SCHEMA_COMPAT_OLD ],
		];
	}

	public function testDoDBUpdatesWithNoRowsInCuChanges() {
		// Mock the primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbwMock ) );
		// Expect that a query for the number of rows in cu_changes is made.
		$dbwMock->method( 'selectRowCount' )
			->with(
				[ 'cu_changes' ],
				'*',
				[],
				'MediaWiki\CheckUser\Maintenance\MoveLogEntriesFromCuChanges::doDBUpdates',
				[],
				[]
			)
			->willReturn( 0 );
		// Mock the config to use the migration stage that allows the use of the script (i.e.
		// includes write new).
		$mockConfig = new HashConfig( [
			'CheckUserEventTablesMigrationStage' => SCHEMA_COMPAT_WRITE_NEW | SCHEMA_COMPAT_OLD
		] );
		// Mock the service container to return this config to a call to ::getMainConfig
		$mockServiceContainer = $this->createMock( MediaWikiServices::class );
		$mockServiceContainer->method( 'getMainConfig' )
			->willReturn( $mockConfig );
		// Get the object under test and make the getServiceContainer return the mock MediaWikiServices.
		$objectUnderTest = $this->getMockBuilder( MoveLogEntriesFromCuChanges::class )
			->onlyMethods( [ 'getServiceContainer', 'getDB', 'output' ] )
			->getMock();
		$objectUnderTest->method( 'getServiceContainer' )
			->willReturn( $mockServiceContainer );
		$objectUnderTest
			->method( 'getDB' )
			->with( DB_PRIMARY )
			->willReturn( $dbwMock );
		// Expect that doDBUpdates returns true.
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertTrue(
			$objectUnderTest->doDBUpdates(),
			"::doDBUpdates should return true when the no rows are in cu_changes."
		);
	}
}
