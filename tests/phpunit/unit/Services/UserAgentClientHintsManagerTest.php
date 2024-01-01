<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsManager
 */
class UserAgentClientHintsManagerTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;
	use CheckUserClientHintsCommonTraitTest;

	/** @dataProvider provideValidTypes */
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
			'Log type' => [ 'log', 1 ],
			'Private log type' => [ 'privatelog', 2 ]
		];
	}

	/** @dataProvider provideInvalidTypes */
	public function testGetMapIdUsingInvalidType( string $invalidType ) {
		$this->expectException( LogicException::class );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$objectToTest->getMapIdByType( $invalidType );
	}

	public static function provideInvalidTypes() {
		return [
			'Invalid string type' => [ 'invalidtype1234' ]
		];
	}

	private function getMockedConnectionProvider( $dbwMock, $dbrMock ) {
		$connectionProviderMock = $this->createMock( IConnectionProvider::class );
		$connectionProviderMock->expects( $this->once() )
			->method( 'getPrimaryDatabase' )
			->willReturn( $dbwMock );
		$connectionProviderMock->expects( $this->once() )
			->method( 'getReplicaDatabase' )
			->willReturn( $dbrMock );
		return $connectionProviderMock;
	}

	private function getObjectUnderTest( $dbwMock, $dbrMock, $logger = null ): UserAgentClientHintsManager {
		return $this->newServiceInstance( UserAgentClientHintsManager::class, [
			'connectionProvider' => $this->getMockedConnectionProvider( $dbwMock, $dbrMock ),
			'logger' => $logger ?? LoggerFactory::getInstance( 'CheckUser' ),
		] );
	}

	public function testInsertClientHintValuesReturnsFatalOnExistingMapping() {
		// Mock replica DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbrMock ) );
		// One read should occur on the replica DB
		$dbrMock->expects( $this->once() )
			->method( 'selectRowCount' )
			->with(
				[ 'cu_useragent_clienthints_map' ],
				'*',
				[
					'uachm_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
					'uachm_reference_id' => 1
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::insertClientHintValues',
				[],
				[]
			)->willReturn( 11 );
		// Mock primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbwMock ) );
		// Read should not occur on the primary DB.
		$dbwMock->expects( $this->never() )->method( 'selectRowCount' );
		$objectToTest = $this->getObjectUnderTest( $dbwMock, $dbrMock );
		$status = $objectToTest->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), 1, 'revision'
		);
		$this->assertStatusNotOK(
			$status,
			'Status should be fatal if mapping already exists.'
		);
		$this->assertStatusError(
			'checkuser-api-useragent-clienthints-mappings-exist',
			$status,
			'Status not using correct message key when mapping already exists.'
		);
		$errors = $status->getErrors();
		$this->assertCount( 1, $errors );
		$this->assertArrayHasKey( 'params', $errors[0] );
		$this->assertCount( 1, $errors[0]['params'] );
		$this->assertArrayEquals(
			[
				'revision',
				1
			],
			$errors[0]['params'][0],
			'Fatal error message parameters not as expected.'
		);
	}

	public function testReturnsEarlyOnNoDataToInsert() {
		// Mock replica DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		// Mock primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$objectToTest = $this->getObjectUnderTest( $dbwMock, $dbrMock );
		$status = $objectToTest->insertClientHintValues(
			ClientHintsData::newFromJsApi( [] ), 1, 'revision'
		);
		$this->assertStatusGood(
			$status,
			'Status should be good if no Client Hints data is present in the ClientHintsData object.'
		);
	}

	public function testNoInsertOfMapRowsOnMissingClientHintsDataRow() {
		// Mock replica DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbrMock ) );
		$dbrMock->expects( $this->once() )
			->method( 'selectField' )
			->with(
				[ 'cu_useragent_clienthints' ],
				'uach_id',
				[
					'uach_name' => 'mobile',
					'uach_value' => false
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::insertMappingRows',
				[],
				[]
			)->willReturn( false );
		// Mock primary DB - No writes should occur.
		$dbwMock = $this->createMock( IDatabase::class );
		// Mock logger
		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )
			->method( 'warning' )
			->with(
				"Lookup failed for cu_useragent_clienthints row with name {name} and value {value}.",
				[ 'mobile', false ]
			);
		$objectToTest = $this->getObjectUnderTest( $dbwMock, $dbrMock, $logger );
		$objectToTest = TestingAccessWrapper::newFromObject( $objectToTest );
		$status = $objectToTest->insertMappingRows(
			ClientHintsData::newFromJsApi( [ 'mobile' => false ] ), 1, 'revision'
		);
		$this->assertStatusGood( $status );
	}

	public function testSuccessfulInsertMappingRows() {
		// Mock replica DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbrMock ) );
		$dbrMock->expects( $this->once() )
			->method( 'selectField' )
			->with(
				[ 'cu_useragent_clienthints' ],
				'uach_id',
				[
					'uach_name' => 'mobile',
					'uach_value' => false
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::insertMappingRows',
				[],
				[]
			)->willReturn( 2 );
		// Mock primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newInsertQueryBuilder' )->willReturnCallback( fn () => new InsertQueryBuilder( $dbwMock ) );
		$dbwMock->expects( $this->once() )
			->method( 'insert' )
			->with(
				'cu_useragent_clienthints_map',
				[
					[ 'uachm_uach_id' => 2, 'uachm_reference_type' => 0, 'uachm_reference_id' => 1 ]
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::insertMappingRows',
				[ 'IGNORE' ]
			);
		$objectToTest = $this->getObjectUnderTest( $dbwMock, $dbrMock );
		$objectToTest = TestingAccessWrapper::newFromObject( $objectToTest );
		$status = $objectToTest->insertMappingRows(
			ClientHintsData::newFromJsApi( [ 'mobile' => false ] ), 1, 'revision'
		);
		$this->assertStatusGood( $status );
	}

	/** @dataProvider provideDeleteMappingRows */
	public function testDeleteMappingRows(
		$referenceIds, $referenceMappingIds
	) {
		// Mock primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn () => new SelectQueryBuilder( $dbwMock ) );
		$dbwMock->method( 'newDeleteQueryBuilder' )->willReturnCallback( fn () => new DeleteQueryBuilder( $dbwMock ) );
		$dbwDeleteExpectedArgs = [];
		$dbwAffectedRowsConsecutiveReturn = [];
		// Test cases that the DB methods are called as appropriate.
		$idsCount = 0;
		foreach ( $referenceIds as $type => $ids ) {
			$idsCount += count( $ids );
			$dbwDeleteExpectedArgs[] = [
				'cu_useragent_clienthints_map',
				[
					'uachm_reference_id' => $ids,
					'uachm_reference_type' => $referenceMappingIds[$type]
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::deleteMappingRows'
			];
			$dbwAffectedRowsConsecutiveReturn[] = count( $ids );
		}
		$dbwMock
			->method( 'delete' )
			->willReturnCallback( function ( ...$args ) use ( &$dbwDeleteExpectedArgs ) {
				$this->assertSame( array_shift( $dbwDeleteExpectedArgs ), $args );
			} );
		$dbwMock
			->method( 'affectedRows' )
			->willReturnOnConsecutiveCalls( ...$dbwAffectedRowsConsecutiveReturn );
		$mockReferenceIds = $this->createMock( ClientHintsReferenceIds::class );
		$mockReferenceIds->method( 'getReferenceIds' )
			->willReturn( $referenceIds );
		$loggerMock = $this->createMock( LoggerInterface::class );
		$loggerMock->expects( $this->once() )
			->method( 'debug' )
			->with(
				"Deleted {mapping_rows_deleted} mapping rows.",
				[ 'mapping_rows_deleted' => $idsCount ]
			)
			->willReturn( null );
		$mappingRowsDeleted = $this->getObjectUnderTest(
			$dbwMock, $this->createMock( IReadableDatabase::class ), $loggerMock
		)->deleteMappingRows( $mockReferenceIds );
		$this->assertSame(
			$idsCount,
			$mappingRowsDeleted,
			'The number of mapping rows deleted did not match the number returned by ::deleteMappingRows.'
		);
	}

	public static function provideDeleteMappingRows() {
		return [
			'Revision reference IDs' => [
				// Reference IDs array held by ClientHintsReferenceIds
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [
						0, 1, 2, 5, 123
					]
				],
				// The expected mapping ID for the cu_useragent_clienthints_map table for each type
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => 0
				],
			]
		];
	}

	public function testDeleteMappingRowsWithEmptyReferenceIdsList() {
		$clientHintReferenceIds = new ClientHintsReferenceIds( [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [],
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [],
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [],
		] );
		$loggerMock = $this->createMock( LoggerInterface::class );
		$loggerMock->expects( $this->once() )
			->method( 'info' )
			->with( "No mapping rows deleted." )
			->willReturn( null );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [ 'logger' => $loggerMock ] )
		);
		/** @var Status $result */
		$result = $objectToTest->deleteMappingRows( $clientHintReferenceIds );
		$this->assertSame(
			0,
			$result,
			'No mapping rows should have been deleted, but ::deleteMappingRows reported ' .
			'deleting some mapping rows.'
		);
	}

	public function testIsMapRowOrphanedForInvalidMappingId() {
		$this->expectException( LogicException::class );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$objectToTest->isMapRowOrphaned( 123, 123 );
	}
}
