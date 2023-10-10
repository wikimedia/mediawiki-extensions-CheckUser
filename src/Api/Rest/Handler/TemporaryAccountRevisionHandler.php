<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;

class TemporaryAccountRevisionHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	protected function getData( int $actorId, IDatabase $dbr ): array {
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
		$conds = [
			'cuc_actor' => $actorId,
			'cuc_this_oldid' => $ids,
		];

		$rows = $this->dbProvider->getReplicaDatabase()
			->newSelectQueryBuilder()
			// T327906: 'cuc_actor' and 'cuc_timestamp' are selected
			// only to satisfy Postgres requirement where all ORDER BY
			// fields must be present in SELECT list.
			->select( [ 'cuc_this_oldid', 'cuc_ip', 'cuc_actor', 'cuc_timestamp' ] )
			->from( 'cu_changes' )
			->where( $conds )
			->orderBy( [ 'cuc_actor', 'cuc_ip', 'cuc_timestamp' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ips = [];
		foreach ( $rows as $row ) {
			// In the unlikely case that there are rows with the same
			// revision ID, the final array will contain the most recent
			$ips[$row->cuc_this_oldid] = $row->cuc_ip;
		}

		return [ 'ips' => $ips ];
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
