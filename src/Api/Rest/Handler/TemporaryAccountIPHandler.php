<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use JobQueueGroup;
use MediaWiki\Block\BlockManager;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\ActorStore;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Given an IP, return every known temporary account that has edited from it
 */
class TemporaryAccountIPHandler extends AbstractTemporaryAccountIPHandler {
	private TempUserConfig $tempUserConfig;
	private UserFactory $userFactory;

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
	 * @param UserFactory $userFactory
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
		UserFactory $userFactory
	) {
		parent::__construct(
			$config, $jobQueueGroup, $permissionManager, $userOptionsLookup, $userNameUtils, $dbProvider, $actorStore,
			$blockManager
		);
		$this->tempUserConfig = $tempUserConfig;
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	protected function getData( $ip, IReadableDatabase $dbr ): array {
		// The limit is the smaller of the user-provided limit parameter and the maximum row count.
		$limit = min( $this->getValidatedParams()['limit'], $this->config->get( 'CheckUserMaximumRowCount' ) );
		// T327906: 'cuc_timestamp' is selected to satisfy a Postgres requirement
		// where all ORDER BY fields must be present in SELECT list.
		$rows = $dbr->newSelectQueryBuilder()
			->fields( [ 'actor_name', 'timestamp' => 'MAX(cuc_timestamp)' ] )
			->table( 'cu_changes' )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->where( [ 'cuc_ip' => $ip ] )
			->where( $this->tempUserConfig->getMatchCondition( $dbr, 'actor_name', IExpression::LIKE ) )
			->groupBy( 'actor_name' )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$accounts = [];
		$canSeeHidden = $this->getAuthority()->isAllowed( 'hideuser' );
		foreach ( $rows as $row ) {
			$account = $row->actor_name;

			// Don't return hidden accounts to authorities who cannot view them
			if ( $canSeeHidden || !$this->userFactory->newFromName( $account )->isHidden() ) {
				$accounts[] = $account;
			}
		}
		return $accounts;
	}
}
