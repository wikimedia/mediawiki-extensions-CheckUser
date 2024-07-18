<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

class TemporaryAccountHandler extends AbstractTemporaryAccountNameHandler implements CheckUserQueryInterface {
	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		// The limit is the smaller of the user-provided limit parameter and the maximum row count.
		$limit = min( $this->getValidatedParams()['limit'], $this->config->get( 'CheckUserMaximumRowCount' ) );
		$resultRows = [];
		foreach ( self::RESULT_TABLES as $table ) {
			$prefix = self::RESULT_TABLE_TO_PREFIX[$table];
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'ip' => "{$prefix}ip", 'timestamp' => 'MAX(' . $prefix . 'timestamp)' ] )
				->from( $table )
				->where( [ "{$prefix}actor" => $actorId ] )
				->groupBy( "{$prefix}ip" )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( $limit )
				->caller( __METHOD__ );
			$resultRows = array_merge( $resultRows, iterator_to_array( $queryBuilder->fetchResultSet() ) );
		}
		// Order the results by the timestamp column descending.
		usort( $resultRows, static function ( $a, $b ) {
			return $b->timestamp <=> $a->timestamp;
		} );
		// Get the IP addresses from $resultRows in the order applied by usort.
		$result = array_column( $resultRows, 'ip' );
		// Remove duplicated IPs (if any)
		$result = array_unique( $result );
		// Apply the limit to the IPs list and then return them.
		return [ 'ips' => array_slice( $result, 0, $limit ) ];
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
