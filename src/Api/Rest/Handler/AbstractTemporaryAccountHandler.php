<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use JobSpecification;
use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;

abstract class AbstractTemporaryAccountHandler extends SimpleHandler {
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

		$dbr = $this->dbProvider->getReplicaDatabase();
		$actorId = $this->actorStore->findActorIdByName( $name, $dbr );
		$userIdentity = $this->actorStore->getUserIdentityByName( $name );
		if ( $actorId === null || $userIdentity === null ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user', [ $name ] ),
				404
			);
		}

		$blockOnTempAccount = $this->blockManager->getBlock( $userIdentity, null );
		if (
			$blockOnTempAccount &&
			$blockOnTempAccount->getHideName() &&
			!$this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'viewsuppressed' )
		) {
			if ( $this->permissionManager->userHasRight( $this->getAuthority()->getUser(), 'hideuser' ) ) {
				// The user knows that this user exists, because they have the 'hideuser' right. Instead of pretending
				// the user does not exist, we instead should inform the user that they don't have the
				// permission to view this information.
				throw new LocalizedHttpException(
					new MessageValue( 'checkuser-rest-access-denied' ),
					403
				);
			} else {
				// Pretend the username does not exist if the temporary account is hidden and the user does not have the
				// rights to see suppressed information or blocks with 'hideuser' set.
				throw new LocalizedHttpException(
					new MessageValue( 'rest-nonexistent-user', [ $name ] ),
					404
				);
			}
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
}
