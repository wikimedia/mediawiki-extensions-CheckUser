<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use Config;
use JobQueueGroup;
use JobSpecification;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

abstract class AbstractTemporaryAccountHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	protected Config $config;
	protected JobQueueGroup $jobQueueGroup;
	protected PermissionManager $permissionManager;
	protected UserOptionsLookup $userOptionsLookup;
	protected UserNameUtils $userNameUtils;
	protected ILoadBalancer $loadBalancer;
	protected ActorStore $actorStore;

	/**
	 * @param Config $config
	 * @param JobQueueGroup $jobQueueGroup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserNameUtils $userNameUtils
	 * @param ILoadBalancer $loadBalancer
	 * @param ActorStore $actorStore
	 */
	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		ILoadBalancer $loadBalancer,
		ActorStore $actorStore
	) {
		$this->config = $config;
		$this->jobQueueGroup = $jobQueueGroup;
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
		if ( !$this->getAuthority()->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

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
				403
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

		$this->jobQueueGroup->push(
			new JobSpecification(
				'checkuserLogTemporaryAccountAccess',
				[
					'performer' => $this->getAuthority()->getUser()->getName(),
					'tempUser' => $this->urlEncodeTitle( $name ),
					'timestamp' => (int)wfTimestamp(),
				],
				[],
				null
			)
		);

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

	/** @inheritDoc */
	public function getBodyValidator( $contentType ) {
		if ( $contentType !== 'application/json' ) {
			return new UnsupportedContentTypeBodyValidator( $contentType );
		}

		return new JsonBodyValidator( $this->getTokenParamDefinition() );
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}
}
