<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Maintenance;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCasePropertyManagerService;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Maintenance script that backfills case properties as defined by
 * SuggestedInvestigationsCasePropertyManagerService as all cases are
 * expected to have all case properties.
 */
class PopulateSiCaseProperties extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 200 );
		$this->requireExtension( 'CheckUser' );
		$this->addDescription( 'Backfill cusi_case_property for cases without properties' );
		$this->addOption(
			'property-key',
			'Specific property key (see SuggestedInvestigationsCasePropertyManagerService). Defaults to all.',
			false,
			true
		);
		$this->addOption(
			'sleep',
			'Sleep time (in seconds) between every batch. Default: 0',
			false,
			true
		);
	}

	/** @inheritDoc */
	public function execute() {
		$propertyKeysToUpdate = array_keys( SuggestedInvestigationsCasePropertyManagerService::ALL_PROPERTIES );
		$propertyKey = $this->getOption( 'property-key', null );
		if ( $propertyKey ) {
			$this->output( "Populating sicp_property $propertyKey in cusi_case_property...\n" );
			if ( !in_array( $propertyKey, $propertyKeysToUpdate ) ) {
				$this->output( "Unknown property key. Aborting.\n" );
				return false;
			}
			$propertyKeysToUpdate = [ $propertyKey ];
		} else {
			$this->output( "Populating all sicp_property keys in cusi_case_property...\n" );
		}

		// If suggested investigations is not enabled, then return early.
		if ( !$this->getConfig()->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			$this->output( "Nothing to do as CheckUser Suggested Investigations is not enabled.\n" );
			return true;
		}

		$dbProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase( CheckUserQueryInterface::VIRTUAL_DB_DOMAIN );

		$count = 0;
		foreach ( $propertyKeysToUpdate as $propertyKeyToUpdate ) {
			do {
				$caseIdsToUpdate = $dbr->newSelectQueryBuilder()
					->select( [ 'sic_id' ] )
					->from( 'cusi_case' )
					->leftJoin(
						'cusi_case_property',
						'cusi_case_property',
						[
							'cusi_case.sic_id = cusi_case_property.sicp_sic_id',
							'cusi_case_property.sicp_property' => (int)$propertyKeyToUpdate,
						]
					)
					->where( $dbr->expr( 'sicp_property', '=', null ) )
					->distinct()
					->limit( $this->getBatchSize() ?? 200 )
					->caller( __METHOD__ )
					->fetchFieldValues();
				$caseIdsToUpdate = array_map( 'intval', $caseIdsToUpdate );

				if ( !count( $caseIdsToUpdate ) ) {
					break;
				}

				$casePropertyManagerService = $this->getServiceContainer()
					->get( 'CheckUserSuggestedInvestigationsCasePropertyManager' );
				$casePropertyManagerService->updatePropertiesForCases( $caseIdsToUpdate, [ $propertyKeyToUpdate ] );
				$count += count( $caseIdsToUpdate );
				$this->output( "... $count case properties updated\n" );
				sleep( intval( $this->getOption( 'sleep', 0 ) ) );
				$this->waitForReplication();
			} while ( count( $caseIdsToUpdate ) > 0 );
		}

		$this->output( "Done. Updated case properties for $count case(s)\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateSiCaseProperties::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
