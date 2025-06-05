<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\Services\CheckUserCentralIndexLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\CheckUser\Services\CheckUserCentralIndexLookup
 */
class CheckUserCentralIndexLookupTest extends MediaWikiIntegrationTestCase {

	public function addDBDataOnce() {
		// Add some testing wiki_map rows
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_wiki_map' )
			->rows( [ [ 'ciwm_wiki' => 'enwiki' ], [ 'ciwm_wiki' => 'dewiki' ] ] )
			->caller( __METHOD__ )
			->execute();

		$enwikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'enwiki' ] )
			->caller( __METHOD__ )
			->fetchField();
		$dewikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'dewiki' ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->rows( [
				[
					'ciu_central_id' => 1, 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230505060708' ),
				],
				[
					'ciu_central_id' => 2, 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230506060708' ),
				],
				[
					'ciu_central_id' => 2, 'ciu_ciwm_id' => $dewikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230507060708' ),
				],
				// Add some testing cuci_user rows which are not expired
				[
					'ciu_central_id' => 4, 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240505060708' ),
				],
				[
					'ciu_central_id' => 5, 'ciu_ciwm_id' => $enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240506060708' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @dataProvider provideOptions
	 * @param string $timestamp Cutoff timestamp as a 14-character MediaWiki timestamp
	 * @param ?int $batchSize Optional batch size for the lookup, or null to use the default
	 * @param int[] $expectedUserIds Expected user IDs that should be returned by the lookup
	 */
	public function testLookupActiveSinceTimestamp(
		string $timestamp,
		?int $batchSize,
		array $expectedUserIds
	) {
		/** @var CheckUserCentralIndexLookup $cuciLookup */
		$cuciLookup = $this->getServiceContainer()->get( 'CheckUserCentralIndexLookup' );

		$userIds = $batchSize !== null ?
			$cuciLookup->getUsersActiveSinceTimestamp( $timestamp, $batchSize ) :
			$cuciLookup->getUsersActiveSinceTimestamp( $timestamp );

		$this->assertSame( $expectedUserIds, iterator_to_array( $userIds ) );
	}

	public static function provideOptions(): iterable {
		yield 'timestamp covering a subset of users' => [
			'timestamp' => '20240505060707',
			'batchSize' => null,
			'expectedUserIds' => [ 4, 5 ],
		];

		yield 'timestamp covering all users' => [
			'timestamp' => '20220505060707',
			'batchSize' => null,
			'expectedUserIds' => [ 1, 2, 4, 5 ],
		];

		yield 'future timestamp' => [
			'timestamp' => '30220505060707',
			'batchSize' => null,
			'expectedUserIds' => [],
		];

		yield 'timestamp covering all users, in two batches' => [
			'timestamp' => '20220505060707',
			'batchSize' => 2,
			'expectedUserIds' => [ 1, 2, 4, 5 ],
		];

		yield 'timestamp covering all users, in four batches' => [
			'timestamp' => '20220505060707',
			'batchSize' => 1,
			'expectedUserIds' => [ 1, 2, 4, 5 ],
		];
	}
}
