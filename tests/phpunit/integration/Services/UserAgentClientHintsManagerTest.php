<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsManager
 */
class UserAgentClientHintsManagerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use CheckUserClientHintsCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'cu_useragent_clienthints',
				'cu_useragent_clienthints_map',
			]
		);
	}

	/**
	 * Tests that the correct number of rows are inserted
	 * by ::insertClientHintValues and ::insertMappingRows.
	 * It then also tests that ::deleteMappingRows works
	 * as expected.
	 *
	 * Does not test the actual values as this is to be
	 * done via more efficient unit tests.
	 *
	 * @dataProvider provideExampleClientHintData
	 */
	public function testInsertAndDeleteOfClientHintAndMappingRows(
		$clientHintDataItems,
		$referenceIdsToInsert,
		$expectedMappingRowCount,
		$expectedClientHintDataRowCount,
		$referenceIdsToDelete,
		$expectedMappingRowCountAfterDeletion,
		$expectedClientHintDataRowCountAfterDeletion
	) {
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		foreach ( $clientHintDataItems as $key => $clientHintData ) {
			$userAgentClientHintsManager->insertClientHintValues(
				$clientHintData, $referenceIdsToInsert[$key], 'revision'
			);
		}
		$this->assertRowCount(
			$expectedClientHintDataRowCount,
			'cu_useragent_clienthints',
			'uach_id',
			'Number of rows in cu_useragent_clienthints table after insertion of data is not as expected'
		);
		$this->assertRowCount(
			$expectedMappingRowCount,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after insertion of data is not as expected'
		);
		$referenceIdsForDeletion = new ClientHintsReferenceIds( [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => $referenceIdsToDelete
		] );
		$this->assertSame(
			$expectedMappingRowCount - $expectedMappingRowCountAfterDeletion,
			$userAgentClientHintsManager->deleteMappingRows( $referenceIdsForDeletion ),
			'UserAgentClientHintsManager::deleteMappingRows did not return the ' .
			'expected number of mapping rows deleted.'
		);
		$this->assertRowCount(
			$expectedClientHintDataRowCountAfterDeletion,
			'cu_useragent_clienthints',
			'uach_id',
			'Number of rows in cu_useragent_clienthints table after deletion of data is not as expected.'
		);
		$this->assertRowCount(
			$expectedMappingRowCountAfterDeletion,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after deletion of data is not as expected.'
		);
	}

	public static function provideExampleClientHintData() {
		yield 'One set of client hint data' => [
			[ self::getExampleClientHintsDataObjectFromJsApi() ],
			// Reference IDs for the client hint data
			[ 1234 ],
			// Mapping table count
			11,
			// Client hint data count
			11,
			// Reference IDs to be deleted
			[ 1234 ],
			// Mapping table count after deletion
			0,
			// Client hint data count after deletion
			11,
		];

		yield 'Two client hint mapping data items' => [
			[
				self::getExampleClientHintsDataObjectFromJsApi(),
				self::getExampleClientHintsDataObjectFromJsApi(
					"x86",
					"64",
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8"
						],
						[
							"brand" => "Chromium",
							"version" => "114"
						],
						[
							"brand" => "Edge",
							"version" => "114"
						]
					],
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8.0.0.0"
						],
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199"
						],
						[
							"brand" => "Edge",
							"version" => "114.0.5735.198"
						]
					],
					true,
					"",
					"Windows",
					"14.0.0"
				)
			],
			// Reference IDs for the client hint data
			[ 123, 12345 ],
			// Mapping table count
			22,
			// Client hint data count
			15,
			// Reference IDs to be deleted
			[ 12345 ],
			// Mapping table count after deletion
			11,
			// Client hint data count after deletion
			15,
		];
	}
}
