<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Maintenance\PurgeOldData;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\Integration\Maintenance\Mocks\SemiMockedCheckUserDataPurger;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PurgeRecentChanges;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\Maintenance\PurgeOldData
 */
class PurgeOldDataTest extends MaintenanceBaseTestCase {

	/** @var MockObject|\Maintenance */
	protected $maintenance;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PurgeOldData::class;
	}

	protected function setUp(): void {
		parent::setUp();
		// Fix the current time and CUDMaxAge so that we can assert against pre-defined timestamp values
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->overrideConfigValue( 'CUDMaxAge', 30 );
	}

	protected function createMaintenance() {
		$obj = $this->getMockBuilder( $this->getMaintenanceClass() )
			->onlyMethods( [ 'runChild', 'getPrimaryDB' ] )
			->getMock();
		return TestingAccessWrapper::newFromObject( $obj );
	}

	/**
	 * @param bool $shouldPurgeRecentChanges Whether the maintenance script should purge data from recentchanges
	 * @return string The expected output regex
	 */
	private function generateExpectedOutputRegex( bool $shouldPurgeRecentChanges ): string {
		$expectedOutputRegex = '/';
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$expectedOutputRegex .= "Purging data from $table.*Purged " .
				SemiMockedCheckUserDataPurger::MOCKED_PURGED_ROW_COUNTS_PER_TABLE[$table] .
				" rows and 2 client hint mapping rows.\n";
		}
		$expectedOutputRegex .= "Purged 123 orphaned client hint mapping rows.\n";
		if ( $shouldPurgeRecentChanges ) {
			$expectedOutputRegex .= "Purging data from recentchanges[\s\S]*";
		}
		$expectedOutputRegex .= 'Done/';
		return $expectedOutputRegex;
	}

	/** @dataProvider provideExecute */
	public function testExecute( $config, $shouldPurgeRecentChanges ) {
		// Expect that the PurgeRecentChanges script is run if $shouldPurgeRecentChanges is true.
		$this->overrideConfigValues( $config );
		$this->maintenance->expects( $this->exactly( (int)$shouldPurgeRecentChanges ) )
			->method( 'runChild' )
			->with( PurgeRecentChanges::class );
		// Mock ::getPrimaryDB so that the maintenance script can use ::timestamp without having to
		// be a Database test.
		$mockDatabase = $this->createMock( IDatabase::class );
		$mockDatabase->method( 'timestamp' )
			->willReturnCallback( static function ( $ts ) {
				$t = new ConvertibleTimestamp( $ts );
				return $t->getTimestamp( TS_MW );
			} );
		$this->maintenance->method( 'getPrimaryDB' )
			->willReturn( $mockDatabase );
		// Expect that UserAgentClientHintsManager::deleteOrphanedMapRows and ::deleteMappingRows are called,
		// and give them fake return values.
		$mockUserAgentClientHintsManager = $this->createMock( UserAgentClientHintsManager::class );
		$mockUserAgentClientHintsManager->method( 'deleteOrphanedMapRows' )
			->willReturn( 123 );
		$mockUserAgentClientHintsManager->expects( $this->exactly( 3 ) )
			->method( 'deleteMappingRows' )
			->willReturn( 2 );
		$this->setService( 'UserAgentClientHintsManager', $mockUserAgentClientHintsManager );
		// Install the mock CheckUserDataPurger service that will assert for us.
		$mockCheckUserDataPurger = new SemiMockedCheckUserDataPurger();
		$this->setService( 'CheckUserDataPurger', $mockCheckUserDataPurger );
		// Run the maintenance script
		$this->maintenance->execute();
		// Verify the output of the maintenance script is as expected
		$this->expectOutputRegex( $this->generateExpectedOutputRegex( $shouldPurgeRecentChanges ) );
		$mockCheckUserDataPurger->checkThatExpectedCallsHaveBeenMade();
	}

	public static function provideExecute() {
		return [
			'wgPutIPinRC is false' => [ [ MainConfigNames::PutIPinRC => false ], false ],
			'wgPutIPinRC is true' => [ [ MainConfigNames::PutIPinRC => true ], true ],
		];
	}
}
