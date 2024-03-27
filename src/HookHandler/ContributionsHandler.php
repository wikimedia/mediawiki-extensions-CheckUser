<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Hook\ContribsPager__getQueryInfoHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;

class ContributionsHandler implements ContribsPager__getQueryInfoHook {
	private Config $config;
	private PermissionManager $permissionManager;
	private IConnectionProvider $dbProvider;
	private TempUserConfig $tempUserConfig;
	private UserOptionsLookup $userOptionsLookup;
	private CheckUserLookupUtils $checkUserLookupUtils;

	/**
	 * @param Config $config
	 * @param PermissionManager $permissionManager
	 * @param IConnectionProvider $dbProvider
	 * @param TempUserConfig $tempUserConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 */
	public function __construct(
		Config $config,
		PermissionManager $permissionManager,
		IConnectionProvider $dbProvider,
		TempUserConfig $tempUserConfig,
		UserOptionsLookup $userOptionsLookup,
		CheckUserLookupUtils $checkUserLookupUtils
	) {
		$this->config = $config;
		$this->permissionManager = $permissionManager;
		$this->dbProvider = $dbProvider;
		$this->tempUserConfig = $tempUserConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
	}

	/**
	 * @inheritDoc
	 */
	public function onContribsPager__getQueryInfo( $pager, &$queryInfo ) {
		if ( !$this->tempUserConfig->isEnabled() ) {
			return;
		}

		$target = $pager->getTarget();
		if ( !IPUtils::isValid( $target ) ) {
			// TODO: Handle ranges and change isValid to isIPAddress: T361867
			return;
		}

		$user = $pager->getUser();
		if (
			!$this->permissionManager->userHasRight(
				$user,
				'checkuser-temporary-account-no-preference'
			) &&
			(
				!$this->permissionManager->userHasRight(
					$user,
					'checkuser-temporary-account'
				) ||
				!$this->userOptionsLookup->getOption(
					$user,
					'checkuser-temporary-account-enable'
				)
			)
		) {
			return;
		}

		if ( $user->getBlock() ) {
			return;
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$ipConds = $this->checkUserLookupUtils->getIPTargetExpr( $target, false, 'cu_changes' );
		if ( !$ipConds ) {
			return;
		}

		$results = $dbr->newSelectQueryBuilder()
			->select( [ 'cuc_actor', 'actor_name' ] )
			->from( 'cu_changes' )
			->where( $ipConds )
			->andWhere(
				$this->tempUserConfig->getMatchCondition(
					$dbr,
					'actor_name',
					IExpression::LIKE
				)
			)
			->caller( __METHOD__ )
			->groupBy( [ 'cuc_actor', 'actor_name' ] )
			->limit( $this->config->get( 'CheckUserMaximumRowCount' ) )
			->join( 'actor', null, 'actor_id=cuc_actor' )
			->fetchResultSet();

		$names = [ $queryInfo['conds']['actor_name'] ];
		foreach ( $results as $row ) {
			$names[] = $row->actor_name;
		}
		$queryInfo['conds']['actor_name'] = $names;
	}
}
