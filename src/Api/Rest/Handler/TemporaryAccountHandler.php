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

		$ips = $this->loadBalancer->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->select( 'cuc_ip' )
			->from( 'cu_changes' )
			->where( [
				'cuc_actor' => $actorId
			] )
			->limit( $limit )
			->distinct()
			// cuc_actor_ip_time index
			->orderby(
				[ 'cuc_actor', 'cuc_ip', 'cuc_timestamp' ],
				SelectQueryBuilder::SORT_DESC
			)
			->caller( __METHOD__ )
			->fetchFieldValues();

		return [ 'ips' => $ips ];
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
