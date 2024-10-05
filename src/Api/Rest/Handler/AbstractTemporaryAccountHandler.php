<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

abstract class AbstractTemporaryAccountHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	protected Config $config;
	protected JobQueueGroup $jobQueueGroup;
	protected PermissionManager $permissionManager;
	protected UserOptionsLookup $userOptionsLookup;
	protected UserNameUtils $userNameUtils;
	protected IConnectionProvider $dbProvider;
	protected ActorStore $actorStore;
	protected BlockManager $blockManager;

	/**
	 * @param Config $config
	 * @param JobQueueGroup $jobQueueGroup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserNameUtils $userNameUtils
	 * @param IConnectionProvider $dbProvider
	 * @param ActorStore $actorStore
	 * @param BlockManager $blockManager
	 */
	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager
	) {
		$this->config = $config;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->permissionManager = $permissionManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNameUtils = $userNameUtils;
		$this->dbProvider = $dbProvider;
		$this->actorStore = $actorStore;
		$this->blockManager = $blockManager;
	}

	/**
	 * Check if the performer has the right to use this API, and throw if not.
	 */
	protected function checkPermissions() {
		if ( !$this->getAuthority()->isNamed() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'checkuser-rest-access-denied' ),
				401
			);
		}

		if (
			!$this->permissionManager->userHasRight(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account-no-preference'
			) &&
			(
				!$this->permissionManager->userHasRight(
					$this->getAuthority()->getUser(),
					'checkuser-temporary-account'
				) ||
				!$this->userOptionsLookup->getOption(
					$this->getAuthority()->getUser(),
					'checkuser-temporary-account-enable'
				)
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
	}

	/**
	 * @inheritDoc
	 */
	public function run( $identifier ): Response {
		$this->checkPermissions();

		$results = $this->getResults( $identifier );

		$this->jobQueueGroup->push(
			LogTemporaryAccountAccessJob::newSpec(
				$this->getAuthority()->getUser(),
				$this->urlEncodeTitle( $identifier ),
				$this->getLogType()
			)
		);

		$maxAge = $this->config->get( 'CheckUserTemporaryAccountMaxAge' );
		$response = $this->getResponseFactory()->createJson( $results );
		$response->setHeader( 'Cache-Control', "private, max-age=$maxAge" );
		return $response;
	}

	/**
	 * @param string $identifier
	 * @return array associated IP addresses or temporary accounts
	 */
	abstract protected function getResults( $identifier ): array;

	/**
	 * @param int|string $identifier
	 * @param IReadableDatabase $dbr
	 * @return array associated IP addresses or temporary accounts
	 */
	abstract protected function getData( $identifier, IReadableDatabase $dbr ): array;

	/**
	 * @return string log type to record
	 */
	abstract protected function getLogType(): string;

	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}
}
