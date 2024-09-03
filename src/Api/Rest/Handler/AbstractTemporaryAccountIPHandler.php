<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Handle IP validation for endpoints that take an IP as a parameter
 */
abstract class AbstractTemporaryAccountIPHandler extends AbstractTemporaryAccountHandler {

	private TempUserConfig $tempUserConfig;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		TempUserConfig $tempUserConfig
	) {
		parent::__construct(
			$config, $jobQueueGroup, $permissionManager, $userOptionsLookup, $userNameUtils, $dbProvider, $actorStore,
			$blockManager
		);
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		if ( !$this->tempUserConfig->isKnown() ) {
			// Pretend the route doesn't exist if temporary accounts are not known, as the APIs need them to be enabled
			// to actually be of any use.
			throw new LocalizedHttpException( new MessageValue( 'rest-no-match' ), 404 );
		}

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
