<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class TemporaryAccountHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	protected function getData( int $actorId, IDatabase $dbr ): array {
		$paramLimit = $this->getValidatedParams()['limit'];
		$configLimit = $this->config->get( 'CheckUserMaximumRowCount' );

		if ( isset( $paramLimit ) && $paramLimit < $configLimit ) {
			$limit = $paramLimit;
		} else {
			$limit = $configLimit;
		}

		$result = $this->loadBalancer->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			// T327906: 'cuc_timestamp' is selected
			// only to satisfy Postgres requirement where all ORDER BY
			// fields must be present in SELECT list.
			->select( [ 'cuc_ip', 'cuc_timestamp' ] )
			->from( 'cu_changes' )
			->where( [
				'cuc_actor' => $actorId
			] )
			->limit( $limit )
			->orderby(
				[ 'cuc_timestamp' ],
				SelectQueryBuilder::SORT_DESC
			)
			->caller( __METHOD__ )
			->fetchResultSet();

		$ips = [];

		foreach ( $result as $row ) {
			$ips[] = $row->cuc_ip;
		}

		$unique_ips = array_values( array_unique( $ips ) );

		return [ 'ips' => $unique_ips ];
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['limit'] = [
			self::PARAM_SOURCE => 'query',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => false,
		];
		return $settings;
	}
}