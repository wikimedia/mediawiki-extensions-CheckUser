<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use DatabaseLogEntry;
use LogEventsList;
use LogPage;
use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class TemporaryAccountLogHandler extends AbstractTemporaryAccountNameHandler {
	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		$ids = $this->getValidatedParams()['ids'];
		if ( !count( $ids ) ) {
			throw new LocalizedHttpException(
				DataMessageValue::new( 'paramvalidator-missingparam', [], 'missingparam' )
					->plaintextParams( "ids" ),
				400,
				[
					'error' => 'parameter-validation-failed',
					'name' => 'ids',
					'value' => '',
					'failureCode' => "missingparam",
					'failureData' => null,
				]
			);
		}

		$ids = $this->filterOutHiddenLogs( $ids );

		if ( !count( $ids ) ) {
			// If all logs were filtered out, return a results list with no IPs
			// which is what happens when there is no CU data for the log events.
			return [ 'ips' => [] ];
		}

		$rows = $dbr->newSelectQueryBuilder()
			// T327906: 'cule_actor' and 'cule_timestamp' are selected
			// only to satisfy Postgres requirement where all ORDER BY
			// fields must be present in SELECT list.
			->select( [ 'cule_log_id', 'cule_ip', 'cule_actor', 'cule_timestamp' ] )
			->from( 'cu_log_event' )
			->where( [
				'cule_actor' => $actorId,
				'cule_log_id' => $ids,
			] )
			->orderBy( [ 'cule_actor', 'cule_ip', 'cule_timestamp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ips = [];
		foreach ( $rows as $row ) {
			// In the unlikely case that there are rows with the same
			// log ID, the final array will contain the most recent
			$ips[$row->cule_log_id] = $row->cule_ip;
		}

		// If some of our log IDs had no data, then try to see if we have this data in the
		// log_search table by looking to see if the log entries had associated revision
		// IDs (which are more likely to have CU data in the case of a page creation).
		$idsMissingAnIP = array_diff( $ids, array_keys( $ips ) );
		if ( count( $idsMissingAnIP ) ) {
			$associatedRevisionIPs = $dbr->newSelectQueryBuilder()
				->select( [ 'ls_log_id', 'cuc_ip', 'cuc_timestamp' ] )
				->from( 'logging' )
				->join( 'log_search', null, 'log_id=ls_log_id' )
				->join( 'cu_changes', null, 'ls_value=cuc_this_oldid' )
				->where( [
					'log_id' => $ids,
					'log_actor' => $actorId,
					'ls_field' => 'associated_rev_id',
				] )
				->caller( __METHOD__ )
				->orderBy( 'cuc_timestamp' )
				->fetchResultSet();

			foreach ( $associatedRevisionIPs as $row ) {
				// In the unlikely case that there are rows with the same
				// revision ID in cu_changes, the final array will contain the most recent
				$ips[$row->ls_log_id] = $row->cuc_ip;
			}
		}

		return [ 'ips' => $ips ];
	}

	/**
	 * Filter out log IDs where the authority does not have permissions to view the performer of the log.
	 *
	 * @param int[] $ids
	 * @return int[]
	 */
	protected function filterOutHiddenLogs( array $ids ): array {
		// Look up the logs from the DB with IDs in $ids
		$logs = $this->performLogsLookup( $ids );

		$filteredIds = [];
		foreach ( $logs as $row ) {
			// Only include the logs where the authority has permissions to view the performer.
			if ( LogEventsList::userCanBitfield(
				$row->log_deleted,
				LogPage::DELETED_USER,
				$this->getAuthority()
			) ) {
				$filteredIds[] = $row->log_id;
			}
		}

		return $filteredIds;
	}

	protected function performLogsLookup( array $ids ): IResultWrapper {
		return DatabaseLogEntry::newSelectQueryBuilder( $this->dbProvider->getReplicaDatabase() )
			->where( [ 'log_id' => $ids ] )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['ids'] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true,
			ParamValidator::PARAM_ISMULTI => true,
		];
		return $settings;
	}
}
