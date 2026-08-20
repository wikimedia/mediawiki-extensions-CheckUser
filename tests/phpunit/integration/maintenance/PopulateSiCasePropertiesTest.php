<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PopulateSiCaseProperties;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentityValue;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateSiCaseProperties
 */
class PopulateSiCasePropertiesTest extends MaintenanceBaseTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateSiCaseProperties::class;
	}

	public function testWhenSuggestedInvestigationsIsDisabled() {
		$this->disableSuggestedInvestigations();

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating all sicp_property keys in cusi_case_property...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Nothing to do as CheckUser Suggested Investigations is not enabled',
			$actualOutputString
		);
	}

	public function testWithPropertyArgument() {
		$this->maintenance->loadWithArgv( [
			'--property-key',
			SuggestedInvestigationsCasePropertyManagerService::PROPERTY_SHARED_PAGE_EDITS_COUNT,
		] );
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating sicp_property 1 in cusi_case_property...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Updated case properties for 0 case(s)',
			$actualOutputString
		);
	}

	public function testWithInvalidPropertyArgument() {
		$this->maintenance->loadWithArgv( [
			'--property-key',
			9999,
		] );
		$this->assertFalse(
			$this->maintenance->execute(),
			'::execute should return false due to the invalid key'
		);

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Unknown property key. Aborting.',
			$actualOutputString
		);
	}

	public function testWhenSuggestedInvestigationsCaseTableIsEmpty() {
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating all sicp_property keys in cusi_case_property...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Updated case properties for 0 case(s)',
			$actualOutputString
		);
	}

	public function testExecuteNoCasesToUpdate() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$firstCaseId = $caseManager->createCase(
			[ new UserIdentityValue( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ]
		);

		// Assert that the row already exists because it was created alongside the case
		$dbw = $this->getDB( DB_PRIMARY );
		$casePropertyStateInitial = $dbw->newSelectQueryBuilder()
			->select( [ 'sicp_sic_id', 'sicp_property', 'sicp_value' ] )
			->from( 'cusi_case_property' )
			->where( [
				'sicp_sic_id' => $firstCaseId,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals( [
			(object)[
				'sicp_sic_id' => '1',
				'sicp_property' => '1',
				'sicp_value' => '0',
			],
		], iterator_to_array( $casePropertyStateInitial ) );

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating all sicp_property keys in cusi_case_property...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Updated case properties for 0 case(s)',
			$actualOutputString
		);

		// Assert that the case properties for the cae remain unchanged
		$casePropertyStateFinal = $dbw->newSelectQueryBuilder()
			->select( [ 'sicp_sic_id', 'sicp_property', 'sicp_value' ] )
			->from( 'cusi_case_property' )
			->where( [
				'sicp_sic_id' => $firstCaseId,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals( [
			(object)[
				'sicp_sic_id' => '1',
				'sicp_property' => '1',
				'sicp_value' => '0',
			],
		], iterator_to_array( $casePropertyStateFinal ) );
	}

	public function testExecute() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$firstCaseId = $caseManager->createCase(
			[ new UserIdentityValue( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ]
		);

		// Clear all rows in cusi_case_property to simulate cases created before the introduction of the feature
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'cusi_case_property' )
			->where( $dbw->expr( 'sicp_property', '!=', null ) )
			->caller( __METHOD__ )
			->execute();
		$casePropertiesCountInitial = $dbw->newSelectQueryBuilder()
			->select( [ 'count' => 'COUNT(*)' ] )
			->from( 'cusi_case_property' )
			->where( [
				'sicp_sic_id' => $firstCaseId,
			] )
			->caller( __METHOD__ )
			->fetchRow();

		$this->assertSame( 0, (int)$casePropertiesCountInitial->count );

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating all sicp_property keys in cusi_case_property...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Updated case properties for 1 case(s)',
			$actualOutputString
		);

		// Assert that the row was inserted as expected
		$casePropertyStateFinal = $dbw->newSelectQueryBuilder()
			->select( [ 'sicp_sic_id', 'sicp_property', 'sicp_value' ] )
			->from( 'cusi_case_property' )
			->where( [
				'sicp_sic_id' => $firstCaseId,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$this->assertEquals( [
			(object)[
				'sicp_sic_id' => '1',
				'sicp_property' => '1',
				'sicp_value' => '0',
			],
		], iterator_to_array( $casePropertyStateFinal ) );
	}
}
