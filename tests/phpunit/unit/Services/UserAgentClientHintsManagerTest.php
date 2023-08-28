<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Status;
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
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbrMock ) );
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
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbwMock ) );
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
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbrMock ) );
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
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbrMock ) );
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
		$dbwMock->method( 'newInsertQueryBuilder' )->willReturnCallback( fn() => new InsertQueryBuilder( $dbwMock ) );
		$dbwMock->expects( $this->once() )
			->method( 'insert' )
			->with(
				'cu_useragent_clienthints_map',
				[
					[ 'uachm_uach_id' => 2, 'uachm_reference_type' => 0, 'uachm_reference_id' => 1 ]
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::insertMappingRows',
				[]
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
		$referenceIds, $referenceMappingIds, $clientHintRowIds, $clientHintOrphanedRowIds
	) {
		// Mock replica DB
		$dbrMock = $this->createMock( IReadableDatabase::class );
		$dbrMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbrMock ) );
		$dbrWithConsecutive = [];
		$dbrWillReturnConsecutive = [];
		// Mock primary DB
		$dbwMock = $this->createMock( IDatabase::class );
		$dbwMock->method( 'newSelectQueryBuilder' )->willReturnCallback( fn() => new SelectQueryBuilder( $dbwMock ) );
		$dbwMock->method( 'newDeleteQueryBuilder' )->willReturnCallback( fn() => new DeleteQueryBuilder( $dbwMock ) );
		$dbwDeleteWithConsecutive = [];
		// Test cases that the DB methods are called as appropriate.
		foreach ( $referenceIds as $type => $ids ) {
			$dbrWithConsecutive[] = [
				[ 'cu_useragent_clienthints_map' ],
				'uachm_uach_id',
				[
					'uachm_reference_id' => $ids,
					'uachm_reference_type' => $referenceMappingIds[$type]
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::deleteMappingRows',
				[ 'DISTINCT' ],
				[]
			];
			$dbrWillReturnConsecutive[] = $clientHintRowIds[$type];
			$dbwDeleteWithConsecutive[] = [
				'cu_useragent_clienthints_map',
				[
					'uachm_reference_id' => $ids,
					'uachm_reference_type' => $referenceMappingIds[$type]
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::deleteMappingRows'
			];
		}
		$dbwDeleteWithConsecutive[] = [
			'cu_useragent_clienthints',
			[
				'uach_id' => $clientHintOrphanedRowIds
			],
			'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::deleteMappingRows'
		];
		$dbrMock->method( 'selectFieldValues' )
			->withConsecutive( ...$dbrWithConsecutive )
			->willReturnOnConsecutiveCalls( ...$dbrWillReturnConsecutive );
		$allClientHintIds = array_merge( ...array_values( $clientHintRowIds ) );
		$dbwMock
			->expects( $this->once() )
			->method( 'selectFieldValues' )
			->with(
				[ 'cu_useragent_clienthints_map' ],
				'uachm_uach_id',
				[
					'uachm_uach_id' => $allClientHintIds
				],
				'MediaWiki\CheckUser\Services\UserAgentClientHintsManager::deleteMappingRows',
				[ 'DISTINCT' ],
				[]
			)->willReturn( array_values( array_diff( $allClientHintIds, $clientHintOrphanedRowIds ) ) );
		$dbwMock
			->method( 'delete' )
			->withConsecutive( ...$dbwDeleteWithConsecutive );
		$mockReferenceIds = $this->createMock( ClientHintsReferenceIds::class );
		$mockReferenceIds->method( 'getReferenceIds' )
			->willReturn( $referenceIds );
		$this->getObjectUnderTest( $dbwMock, $dbrMock )->deleteMappingRows( $mockReferenceIds );
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
				// The cu_useragent_clienthints IDs associated with the reference IDs per type
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1, 2, 3 ]
				],
				// The cu_useragent_clienthints IDs associated with the reference IDs which are orphaned
				//  after deletion of the mapping rows
				[ 2 ]
			]
		];
	}

	public function testDeleteMappingRowsWithEmptyReferenceIdsList() {
		$clientHintReferenceIds = new ClientHintsReferenceIds();
		$clientHintReferenceIds->addReferenceIds( [], UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES );
		$clientHintReferenceIds->addReferenceIds( [], UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT );
		$clientHintReferenceIds->addReferenceIds( [], UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		/** @var Status $result */
		$result = $objectToTest->deleteMappingRows( $clientHintReferenceIds );
		$this->assertStatusGood( $result );
		$this->assertArrayEquals(
			[ 0, 0 ],
			$result->getValue(),
			false,
			false,
			'No mapping rows or cu_useragent_clienthints rows should have been touched.'
		);
	}
}
