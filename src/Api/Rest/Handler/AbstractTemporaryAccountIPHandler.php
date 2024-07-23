<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Handle IP validation for endpoints that take an IP as a parameter
 */
abstract class AbstractTemporaryAccountIPHandler extends AbstractTemporaryAccountHandler {
	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		if ( !IPUtils::isValid( $identifier ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-invalid-ip', [ $identifier ] ),
				404
			);
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		return $this->getData( $identifier, $dbr );
	}

	/**
	 * @inheritDoc
	 */
	protected function getLogType(): string {
		return TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP;
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'ip' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => $this->config->get( 'CheckUserMaximumRowCount' ),
				IntegerDef::PARAM_MAX => $this->config->get( 'CheckUserMaximumRowCount' ),
				IntegerDef::PARAM_MIN => 1
			]
		];
	}
}
