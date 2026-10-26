<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use MediaWiki\Extension\CheckUser\Maintenance\DeleteReadOldRowsInCuChanges;
use MediaWiki\Extension\CheckUser\Maintenance\FixTrailingSpacesInLogs;
use MediaWiki\Extension\CheckUser\Maintenance\MigrateTemporaryAccountIPViewerGroup;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCentralCheckUserIndexTables;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateCheckUserTable;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUpdatedTimestamp;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUrlIdentifier;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateUserAgentTable;
use MediaWiki\Extension\CheckUser\Maintenance\QueueAutoCloseSICases;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaChangesHandler implements LoadExtensionSchemaUpdatesHook, CheckUserQueryInterface {
	/**
	 * @codeCoverageIgnore This is tested by installing or updating MediaWiki
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__ . '/../../schema';
		$maintenanceDb = $updater->getDB();
		$dbType = $maintenanceDb->getType();
		$isCUInstalled = $updater->tableExists( 'cu_changes' );

		$updater->addExtensionTable( 'cu_changes', "$base/$dbType/tables-generated.sql" );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_case',
			"$base/$dbType/tables-virtual-checkuser-generated.sql", true,
		] );

		// Added 1.43, but will need to remain here forever as it creates tables which are not in tables-generated.sql
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_wiki_map',
			"$base/$dbType/cuci_wiki_map.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_temp_edit',
			"$base/$dbType/cuci_temp_edit.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_GLOBAL_DB_DOMAIN, 'addTable', 'cuci_user',
			"$base/$dbType/cuci_user.sql", true,
		] );

		// 1.42
		$updater->addExtensionField(
			'cu_log',
			'cul_result_id',
			"$base/$dbType/patch-cu_log-add-cul_result_id.sql"
		);
		if ( $dbType !== 'sqlite' ) {
			$updater->modifyExtensionField(
				'cu_changes',
				'cuc_id',
				"$base/$dbType/patch-cu_changes-modify-cuc_id-bigint.sql"
			);
		}
		$updater->addPostDatabaseUpdateMaintenance( FixTrailingSpacesInLogs::class );
		// If using SQLite and the cupe_private column no longer exists exists, then this update has
		// already been applied and we should skip it entirely because temporary tables used
		// here would fail due to cupe_private no longer being a column
		if (
			$dbType !== 'sqlite' ||
			$maintenanceDb->fieldExists( 'cu_private_event', 'cupe_private', __METHOD__ )
		) {
			$updater->modifyExtensionField(
				'cu_private_event',
				'cupe_actor',
				"$base/$dbType/patch-cu_private_event-modify-cupe_actor-nullable.sql"
			);
		}
		$updater->addExtensionTable( 'cu_useragent', "$base/$dbType/cu_useragent.sql" );
		$updater->addExtensionField(
			'cu_changes',
			'cuc_agent_id',
			"$base/$dbType/patch-cu_changes-add-cuc_agent_id.sql"
		);
		$updater->addExtensionField(
			'cu_log_event',
			'cule_agent_id',
			"$base/$dbType/patch-cu_log_event-add-cule_agent_id.sql"
		);
		$updater->addExtensionField(
			'cu_private_event',
			'cupe_agent_id',
			"$base/$dbType/patch-cu_private_event-add-cupe_agent_id.sql"
		);

		// 1.43
		// Must be run before the removal of the cuc_only_for_read_old column, as the script needs the column to be
		// present to delete the rows where the column value is 1.
		$updater->addExtensionUpdate( [
			'runMaintenance',
			DeleteReadOldRowsInCuChanges::class,
		] );
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_only_for_read_old',
			"$base/$dbType/patch-cu_changes-drop-cuc_only_for_read_old.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_actiontext',
			"$base/$dbType/patch-cu_changes-drop-cuc_actiontext.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_private',
			"$base/$dbType/patch-cu_changes-drop-cuc_private.sql"
		);
		if ( $isCUInstalled ) {
			// We only need to run this maintenance script if CU is already installed, because otherwise the script
			// will be run for us by the populateCheckUserTable.php script (after it's populated the tables).
			$updater->addPostDatabaseUpdateMaintenance( PopulateCentralCheckUserIndexTables::class );
		}

		// 1.44
		$updater->addPostDatabaseUpdateMaintenance( MigrateTemporaryAccountIPViewerGroup::class );

		// 1.45
		$updater->addExtensionIndex(
			'cu_changes',
			'cuc_actor_ip_hex_time',
			"$base/$dbType/patch-cu_changes-add-index-cuc_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_log_event',
			'cule_actor_ip_hex_time',
			"$base/$dbType/patch-cu_log_event-add-index-cule_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_private_event',
			'cupe_actor_ip_hex_time',
			"$base/$dbType/patch-cu_private_event-add-index-cupe_actor_ip_hex_time.sql"
		);
		$updater->addExtensionIndex(
			'cu_log',
			'cul_target',
			"$base/$dbType/patch-cu_log-add-index-cul_target.sql"
		);
		$updater->dropExtensionIndex(
			'cu_log',
			'cul_type_target',
			"$base/$dbType/patch-cu_log-drop-index-cul_type_target.sql"
		);
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_case',
			"$base/$dbType/patch-cusi_case-def.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_user',
			"$base/$dbType/patch-cusi_user-def.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_signal',
			"$base/$dbType/patch-cusi_signal-def.sql", true,
		] );

		// 1.46
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_url_identifier',
			"$base/$dbType/patch-cusi_case-add-sic_url_identifier.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'runMaintenance', PopulateSicUrlIdentifier::class,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_signal', 'sis_trigger_id',
			"$base/$dbType/patch-cusi_signal-add-sis_trigger_id.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'modifyTableIfFieldNotExists', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-modify-sic_url_identifier.sql", true,
			'sic_url_identifier',
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-add-sic_updated_timestamp.sql", true,
		] );
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_private',
			"$base/$dbType/patch-cu_private_event-drop-cupe_private.sql"
		);
		$updater->addExtensionUpdate( [
			'runMaintenance',
			PopulateUserAgentTable::class,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN,
			'runMaintenance',
			PopulateSicUpdatedTimestamp::class,
		] );
		$updater->addPostDatabaseUpdateMaintenance( QueueAutoCloseSICases::class );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_user', 'siu_info',
			"$base/$dbType/patch-cusi_user-add-siu_info.sql", true,
		] );
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_agent',
			"$base/$dbType/patch-cu_private_event-drop-cupe_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_agent',
			"$base/$dbType/patch-cu_changes-drop-cuc_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_log_event',
			'cule_agent',
			"$base/$dbType/patch-cu_log_event-drop-cule_agent.sql"
		);
		$updater->dropExtensionField(
			'cu_changes',
			'cuc_ip',
			"$base/$dbType/patch-cu_changes-drop-cuc_ip.sql"
		);
		$updater->dropExtensionField(
			'cu_log_event',
			'cule_ip',
			"$base/$dbType/patch-cu_log_event-drop-cule_ip.sql"
		);
		$updater->dropExtensionField(
			'cu_private_event',
			'cupe_ip',
			"$base/$dbType/patch-cu_private_event-drop-cupe_ip.sql"
		);
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'modifyField', 'cusi_case', 'sic_updated_timestamp',
			"$base/$dbType/patch-cusi_case-modify-sic_updated_timestamp-remove_default.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'dropIndex', 'cusi_case', 'sic_created_timestamp_id',
			"$base/$dbType/patch-cusi_case-drop-sic_created_timestamp-indexes.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addField', 'cusi_case', 'sic_status_changed_by',
			"$base/$dbType/patch-cusi_case-add-sic_status_changed_by.sql", true,
		] );

		// 1.47
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'addTable', 'cusi_case_property',
			"$base/$dbType/patch-cusi_case_property-def.sql", true,
		] );
		$updater->addExtensionUpdateOnVirtualDomain( [
			self::VIRTUAL_DB_DOMAIN, 'dropField', 'cusi_case_property', 'sicp_stale',
			"$base/$dbType/patch-cusi_case_property-drop-sicp_stale.sql", true,
		] );

		if ( !$isCUInstalled ) {
			// First time so populate the CheckUser result tables with recentchanges data.
			// Note: We cannot completely rely on updatelog here for old entries
			// as populateCheckUserTable.php doesn't check for duplicates
			$updater->addPostDatabaseUpdateMaintenance( PopulateCheckUserTable::class );
		}
	}
}
