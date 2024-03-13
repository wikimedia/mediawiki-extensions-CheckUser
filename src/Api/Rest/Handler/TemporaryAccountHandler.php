<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class TemporaryAccountHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	protected function getData( int $actorId, IReadableDatabase $dbr ): array {
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'cuc_ip' ] )
			->from( 'cu_changes' )
			->where( [
				'cuc_actor' => $actorId
			] )
			->groupBy( 'cuc_ip' )
			->orderby(
				[ 'Max(cuc_timestamp)' ],
				SelectQueryBuilder::SORT_DESC
			)
			->limit( min( $this->getValidatedParams()['limit'], $this->config->get( 'CheckUserMaximumRowCount' ) ) )
			->caller( __METHOD__ )
			->fetchFieldValues();

		return [ 'ips' => $result ];
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
			ParamValidator::PARAM_DEFAULT => $this->config->get( 'CheckUserMaximumRowCount' ),
			IntegerDef::PARAM_MAX => $this->config->get( 'CheckUserMaximumRowCount' ),
			IntegerDef::PARAM_MIN => 1
		];
		return $settings;
	}
}
