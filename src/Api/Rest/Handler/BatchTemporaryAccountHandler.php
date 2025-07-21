<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Config\Config;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
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
		ReadOnlyMode $readOnlyMode,
		private readonly ExtensionRegistry $extensionRegistry
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

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$body = $this->filterAbuseFilterLogIds( $body );
		}

		foreach ( $body['users'] ?? [] as $username => $params ) {
			$identifier = [
				'actorId' => $this->getTemporaryAccountActorId( $username ),
				'revIds' => $params['revIds'] ?? [],
				'logIds' => $params['logIds'] ?? [],
				'lastUsedIp' => $params['lastUsedIp'] ?? false,
			];
			if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
				$identifier['abuseLogIds'] = $params['abuseLogIds'] ?? [];
			}
			$results[$username] = $this->getData( $identifier, $dbr );
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
		[ 'actorId' => $actorId, 'revIds' => $revIds, 'logIds' => $logIds, 'lastUsedIp' => $lastUsedIp ] = $identifier;

		$data = [
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

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$data['abuseLogIps'] = count( $identifier['abuseLogIds'] ) > 0
				? $this->getAbuseFilterLogIPs( $identifier['abuseLogIds'] )
				: null;
		}

		return $data;
	}

	/**
	 * Returns the IPs associated with a given set of abuse_filter_log IDs.
	 *
	 * This method assumes that the AbuseFilter extension is installed.
	 */
	private function getAbuseFilterLogIPs( array $abuseFilterLogIds ): array {
		if ( count( $abuseFilterLogIds ) === 0 ) {
			return [];
		}

		$abuseFilterPrivateLogDetailsLookup = AbuseFilterServices::getLogDetailsLookup();
		$abuseLogIps = $abuseFilterPrivateLogDetailsLookup->getIPsForAbuseFilterLogs(
			$this->getAuthority(), $abuseFilterLogIds
		);

		// Remove any IPs which are false (meaning the user could not see the abuse_filter_log row) and then
		// return the list.
		return array_filter( $abuseLogIps, static fn ( $value ) => $value !== false );
	}

	/**
	 * Filters out afl_id values from the request body that were not performed by the username that they
	 * were associated with in the request body.
	 *
	 * This is done to ensure that the IP reveal logs correctly indicate all the users that were revealed.
	 * Otherwise a user could specify an afl_id for a different temporary account and avoid creating a log.
	 *
	 * @param array $body The data returned by {@link self::getValidatedBody}
	 * @return array The data to use as the value of {@link self::getValidatedBody} from now on
	 */
	private function filterAbuseFilterLogIds( array $body ): array {
		$abuseLogIds = [];
		foreach ( $body['users'] ?? [] as $params ) {
			$abuseLogIds = array_merge( $params['abuseLogIds'] ?? [], $abuseLogIds );
		}

		if ( count( $abuseLogIds ) !== 0 ) {
			$abuseFilterPrivateLogDetailsLookup = AbuseFilterServices::getLogDetailsLookup();
			$groupedAbuseFilterLogIds = $abuseFilterPrivateLogDetailsLookup->groupAbuseFilterLogIdsByPerformer(
				$abuseLogIds
			);

			foreach ( $body['users'] as $username => &$params ) {
				$params['abuseLogIds'] = $groupedAbuseFilterLogIds[$username] ?? [];
			}
		}

		return $body;
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		$optionalUserProperties = [];
		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$optionalUserProperties['abuseLogIds'] = ArrayDef::makeListSchema( 'string' );
		}

		return [
			'users' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ArrayDef::PARAM_SCHEMA => ArrayDef::makeMapSchema( ArrayDef::makeObjectSchema(
					[
						'revIds' => ArrayDef::makeListSchema( 'string' ),
						'logIds' => ArrayDef::makeListSchema( 'string' ),
						'lastUsedIp' => 'boolean',
					],
					$optionalUserProperties
				) ),
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
