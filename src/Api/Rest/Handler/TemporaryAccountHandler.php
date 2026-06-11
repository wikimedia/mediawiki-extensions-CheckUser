<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\CheckUserQueryInterface;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IReadableDatabase;

class TemporaryAccountHandler extends AbstractTemporaryAccountNameHandler implements CheckUserQueryInterface {

	use TemporaryAccountNameTrait;

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		// The limit is the smaller of the user-provided limit parameter and the maximum row count.
		$limit = min(
			$this->getValidatedParams()['limit'],
			$this->config->get( 'CheckUserMaximumRowCount' )
		);

		return [ 'ips' => $this->getActorIps( $actorId, $limit, $dbr ) ];
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
			IntegerDef::PARAM_MIN => 1,
			self::PARAM_DESCRIPTION => new MessageValue( 'checkuser-rest-param-desc-limit-ips' ),
			self::PARAM_EXAMPLE => 50,
		];
		return $settings;
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-temp-account',
			'properties' => [
				'ips' => [
					'type' => 'array',
					'x-i18n-description' => 'checkuser-rest-property-desc-ips',
					'items' => [
						'type' => 'string',
						'x-i18n-description' => 'checkuser-rest-property-desc-ip-item',
					],
				],
				'autoReveal' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-auto-reveal',
				],
			],
			'required' => [ 'ips' ],
			'example' => [
				'ips' => [ '192.0.2.1', '2001:db8::1' ],
				'autoReveal' => false,
			],
		];
	}
}
