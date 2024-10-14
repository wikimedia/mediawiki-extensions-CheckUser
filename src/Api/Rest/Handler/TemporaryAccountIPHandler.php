<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Given an IP, return every known temporary account that has edited from it
 */
class TemporaryAccountIPHandler extends AbstractTemporaryAccountIPHandler {

	private CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup;

	/**
	 * @param Config $config
	 * @param JobQueueGroup $jobQueueGroup
	 * @param PermissionManager $permissionManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserNameUtils $userNameUtils
	 * @param IConnectionProvider $dbProvider
	 * @param ActorStore $actorStore
	 * @param BlockManager $blockManager
	 * @param TempUserConfig $tempUserConfig
	 */
	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		TempUserConfig $tempUserConfig,
		CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup
	) {
		parent::__construct(
			$config, $jobQueueGroup, $permissionManager, $userOptionsLookup, $userNameUtils, $dbProvider, $actorStore,
			$blockManager, $tempUserConfig
		);
		$this->checkUserTemporaryAccountsByIPLookup = $checkUserTemporaryAccountsByIPLookup;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $ip, IReadableDatabase $dbr ): array {
		$status = $this->checkUserTemporaryAccountsByIPLookup->get(
			$ip,
			$this->getAuthority(),
			false,
			$this->getValidatedParams()['limit']
		);
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return [];
	}
}
