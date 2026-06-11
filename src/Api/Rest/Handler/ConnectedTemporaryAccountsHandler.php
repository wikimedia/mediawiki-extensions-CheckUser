<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

class ConnectedTemporaryAccountsHandler extends AbstractTemporaryAccountNameHandler {

	use TemporaryAccountNameTrait;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		CheckUserPermissionManager $checkUserPermissionsManager,
		CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup,
		TemporaryAccountLoggerFactory $loggerFactory,
		ReadOnlyMode $readOnlyMode,
		private readonly CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup,
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
			$autoRevealLookup,
			$loggerFactory,
			$readOnlyMode
		);
	}

	/**
	 * Skip logging, as the specific functions used perform their own logging operations.
	 *
	 * @inheritDoc
	 */
	public function makeLog( $identifier ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		$connectedAccounts = [];
		$ipsUsedCount = [];

		// Get connected accounts; limit is hardcoded per T412212
		$target = $this->actorStore->getActorById( $actorId, $dbr );
		if ( $target ) {
			$connectedAccounts = $this->checkUserTemporaryAccountsByIPLookup->getActiveTempAccountNames(
				$this->getAuthority(),
				$target,
				101
			)->value ?? [];
			$ipsUsedCount = $this->checkUserTemporaryAccountsByIPLookup->getIpsUsedCount( $target );
		}

		return [
			'connectedAccounts' => $connectedAccounts,
			'ipsUsedCount' => $ipsUsedCount,
		];
	}

	/** @inheritDoc */
	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'x-i18n-description' => 'checkuser-rest-response-desc-connected-temp-accounts',
			'properties' => [
				'connectedAccounts' => [
					'type' => 'array',
					'x-i18n-description' => 'checkuser-rest-property-desc-connected-accounts',
					'items' => [
						'type' => 'string',
						'x-i18n-description' => 'checkuser-rest-property-desc-temp-account-name-item',
					],
				],
				'ipsUsedCount' => [
					'type' => 'integer',
					'x-i18n-description' => 'checkuser-rest-property-desc-ips-used-count',
				],
				'autoReveal' => [
					'type' => 'boolean',
					'x-i18n-description' => 'checkuser-rest-property-desc-auto-reveal',
				],
			],
			'required' => [ 'connectedAccounts', 'ipsUsedCount' ],
			'example' => [
				'connectedAccounts' => [ '~2026-12345', '~2026-67890' ],
				'ipsUsedCount' => 2,
				'autoReveal' => false,
			],
		];
	}
}
