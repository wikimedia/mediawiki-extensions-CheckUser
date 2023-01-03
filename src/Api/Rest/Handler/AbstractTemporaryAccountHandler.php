<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

abstract class AbstractTemporaryAccountHandler extends SimpleHandler {
	/** @var Config */
	protected $config;

	/** @var PermissionManager */
	protected $permissionManager;

	/** @var UserOptionsLookup */
	protected $userOptionsLookup;

	/** @var UserNameUtils */
	protected $userNameUtils;

	/** @var ILoadBalancer */
	protected $loadBalancer;

	/** @var ActorStore */
	protected $actorStore;

	/**
	 * @param Config $config
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserNameUtils $userNameUtils
	 * @param ILoadBalancer $loadBalancer
	 * @param ActorStore $actorStore
	 */
	public function __construct(
		Config $config,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		ILoadBalancer $loadBalancer,
		ActorStore $actorStore
	) {
		$this->config = $config;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNameUtils = $userNameUtils;
		$this->loadBalancer = $loadBalancer;
		$this->actorStore = $actorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function run( string $name ): Response {
		if (
			!$this->permissionManager->userHasRight(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account'
			) ||
			!$this->userOptionsLookup->getOption(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account-enable'
			)
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				$this->getAuthority()->isRegistered() ? 403 : 401
			);
		}

		if ( $this->getAuthority()->getBlock() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied-blocked-user' ),
				403
			);
		}

		if ( !$this->userNameUtils->isTemp( $name ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-invalid-user', [ $name ] ),
				404
			);
		}

		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$actorId = $this->actorStore->findActorIdByName( $name, $dbr );
		if ( $actorId === null ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user', [ $name ] ),
				404
			);
		}

		$data = $this->getData( $actorId, $dbr );

		// TODO: Log access (T325658)

		$maxAge = $this->config->get( 'CheckUserTemporaryAccountMaxAge' );
		$response = $this->getResponseFactory()->createJson( $data );
		$response->setHeader( 'Cache-Control', "private, max-age=$maxAge" );
		return $response;
	}

	/**
	 * @param int $actorId
	 * @param IDatabase $dbr
	 * @return array IP addresses used by the temporary account
	 */
	abstract protected function getData( int $actorId, IDatabase $dbr ): array;

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
