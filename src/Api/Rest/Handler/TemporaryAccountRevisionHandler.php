<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;

class TemporaryAccountRevisionHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	protected function getData( int $actorId, IDatabase $dbr ): array {
		$ids = $this->getValidatedParams()['ids'];
		$conds = [
			'cuc_actor' => $actorId,
			'cuc_this_oldid' => $ids,
		];

		$rows = $this->loadBalancer->getConnection( DB_REPLICA )
			->newSelectQueryBuilder()
			->select( [ 'cuc_this_oldid', 'cuc_ip' ] )
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
