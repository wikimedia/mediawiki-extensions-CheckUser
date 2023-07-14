<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use IDatabase;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\IConnectionProvider;
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

	/**
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

	private function getObjectUnderTest( $dbwMock, $dbrMock ): UserAgentClientHintsManager {
		return $this->newServiceInstance( UserAgentClientHintsManager::class, [
			'connectionProvider' => $this->getMockedConnectionProvider( $dbwMock, $dbrMock ),
			'logger' => LoggerFactory::getInstance( 'CheckUser' ),
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

	/**
	 * @dataProvider provideDeleteMappingRows
	 */
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
}
