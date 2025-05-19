<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Config\Config;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

class BatchTemporaryAccountHandler extends AbstractTemporaryAccountHandler {

	use TemporaryAccountNameTrait;
	use TemporaryAccountRevisionTrait;
	use TemporaryAccountLogTrait;

	private RevisionStore $revisionStore;
	private CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup;
	private TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		RevisionStore $revisionStore,
		CheckUserPermissionManager $checkUserPermissionsManager,
		CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup,
		TemporaryAccountLoggerFactory $loggerFactory,
		ReadOnlyMode $readOnlyMode
	) {
		parent::__construct(
			$config,
			$jobQueueGroup,
			$permissionManager,
			$userNameUtils,
			$dbProvider,
			$actorStore,
			$blockManager,
			$checkUserPermissionsManager,
			$readOnlyMode
		);
		$this->revisionStore = $revisionStore;
		$this->autoRevealLookup = $autoRevealLookup;
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function run( $identifier = null ): Response {
		return parent::run( $identifier );
	}

	/**
	 * @inheritDoc
	 */
	public function makeLog( $identifier ) {
		$users = $this->getValidatedBody()['users'] ?? [];

		if ( $this->autoRevealLookup->isAutoRevealOn( $this->getAuthority() ) ) {
			$logger = $this->loggerFactory->getLogger();
			$performerName = $this->getAuthority()->getUser()->getName();

			foreach ( $users as $username => $params ) {
				$logger->logViewIPsWithAutoReveal(
					$performerName,
					$username
				);
			}
			return;
		}

		foreach ( $users as $username => $params ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$this->getAuthority()->getUser(),
					$username,
					$this->getLogType()
				)
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		$results = [];

		$dbr = $this->getConnectionProvider()->getReplicaDatabase();
		$body = $this->getValidatedBody();

		foreach ( $body['users'] ?? [] as $username => $params ) {
			$results[$username] = $this->getData( [
				$this->getTemporaryAccountActorId( $username ),
				$params['revIds'] ?? [],
				$params['logIds'] ?? [],
				$params['lastUsedIp'] ?? false,
			], $dbr );
		}

		if ( $this->autoRevealLookup->isAutoRevealAvailable() ) {
			$results['autoReveal'] = $this->autoRevealLookup->isAutoRevealOn(
				$this->getAuthority()
			);
		}

		return $results;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $identifier, IReadableDatabase $dbr ): array {
		[ $actorId, $revIds, $logIds, $lastUsedIp ] = $identifier;

		return [
			'revIps' => count( $revIds ) > 0
				? $this->getRevisionsIps( $actorId, $revIds, $dbr )
				: null,
			'logIps' => count( $logIds ) > 0
				? $this->getLogIps( $actorId, $logIds, $dbr )
				: null,
			'lastUsedIp' => $lastUsedIp
				? ( $this->getActorIps( $actorId, 1, $dbr )[0] ?? null )
				: null,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		return [
			'users' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ArrayDef::PARAM_SCHEMA => ArrayDef::makeMapSchema(
					ArrayDef::makeObjectSchema( [
						'revIds' => ArrayDef::makeListSchema( 'string' ),
						'logIds' => ArrayDef::makeListSchema( 'string' ),
						'lastUsedIp' => 'boolean',
					] )
				),
			]
		] + parent::getBodyParamSettings();
	}

	/**
	 * @inheritDoc
	 */
	protected function getLogType(): string {
		return TemporaryAccountLogger::ACTION_VIEW_IPS;
	}

	/**
	 * @inheritDoc
	 */
	protected function getActorStore(): ActorStore {
		return $this->actorStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getBlockManager(): BlockManager {
		return $this->blockManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getConnectionProvider(): IConnectionProvider {
		return $this->dbProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function getPermissionManager(): PermissionManager {
		return $this->permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionStore(): RevisionStore {
		return $this->revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUserNameUtils(): UserNameUtils {
		return $this->userNameUtils;
	}
}
