<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ActorMigration;
use CentralIdLookup;
use IContextSource;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\FormOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use SpecialPage;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

class CheckUserGetIPsPager extends AbstractCheckUserPager {

	/**
	 * @param FormOptions $opts
	 * @param UserIdentity $target
	 * @param string $logType
	 * @param TokenQueryManager $tokenQueryManager
	 * @param UserGroupManager $userGroupManager
	 * @param CentralIdLookup $centralIdLookup
	 * @param ILoadBalancer $loadBalancer
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param ActorMigration $actorMigration
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserFactory $userFactory
	 * @param CheckUserUnionSelectQueryBuilderFactory $checkUserUnionSelectQueryBuilderFactory
	 * @param IContextSource|null $context
	 * @param LinkRenderer|null $linkRenderer
	 * @param ?int $limit
	 */
	public function __construct(
		FormOptions $opts,
		UserIdentity $target,
		string $logType,
		TokenQueryManager $tokenQueryManager,
		UserGroupManager $userGroupManager,
		CentralIdLookup $centralIdLookup,
		ILoadBalancer $loadBalancer,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		ActorMigration $actorMigration,
		CheckUserLogService $checkUserLogService,
		UserFactory $userFactory,
		CheckUserUnionSelectQueryBuilderFactory $checkUserUnionSelectQueryBuilderFactory,
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager, $userGroupManager, $centralIdLookup,
			$loadBalancer, $specialPageFactory, $userIdentityLookup, $actorMigration, $checkUserLogService,
			$userFactory, $checkUserUnionSelectQueryBuilderFactory, $context, $linkRenderer, $limit );
		$this->checkType = SpecialCheckUser::SUBTYPE_GET_IPS;
	}

	/** @inheritDoc */
	public function formatRow( $row ): string {
		$lang = $this->getLanguage();
		$ip = $row->ip;
		$templateParams = [];
		$templateParams['ipLink'] = $this->getSelfLink( $ip,
			[
				'user' => $ip,
				'reason' => $this->opts->getValue( 'reason' ),
			]
		);

		// If we get some results, it helps to know if the IP in general
		// has a lot more edits, e.g. "tip of the iceberg"...
		$ipEdits = $this->getCountForIPedits( $ip );
		if ( $ipEdits ) {
			$templateParams['ipEditCount'] =
				$this->msg( 'checkuser-ipeditcount' )->numParams( $ipEdits )->escaped();
			$templateParams['showIpCounts'] = true;
		}

		if ( IPUtils::isValidIPv6( $ip ) ) {
			$templateParams['ip64Link'] = $this->getSelfLink( '/64',
				[
					'user' => $ip . '/64',
					'reason' => $this->opts->getValue( 'reason' ),
				]
			);
			$ipEdits64 = $this->getCountForIPedits( $ip . '/64' );
			if ( $ipEdits64 && ( !$ipEdits || $ipEdits64 > $ipEdits ) ) {
				$templateParams['ip64EditCount'] =
					$this->msg( 'checkuser-ipeditcount-64' )->numParams( $ipEdits64 )->escaped();
				$templateParams['showIpCounts'] = true;
			}
		}
		$templateParams['blockLink'] = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'Block', $ip ),
			$this->msg( 'blocklink' )->text()
		);
		$templateParams['timeRange'] = $this->getTimeRangeString( $row->first, $row->last );
		$templateParams['editCount'] = $lang->formatNum( $row->count );

		// If this IP is blocked, give a link to the block log
		$templateParams['blockInfo'] = $this->getIPBlockInfo( $ip );
		$templateParams['toolLinks'] = $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse();
		return $this->templateParser->processTemplate( 'GetIPsLine', $templateParams );
	}

	/**
	 * Get information about any active blocks on a IP.
	 *
	 * @param string $ip the IP to get block info on.
	 * @return string
	 */
	protected function getIPBlockInfo( string $ip ): string {
		$block = DatabaseBlock::newFromTarget( null, $ip );
		if ( $block instanceof DatabaseBlock ) {
			return $this->getBlockFlag( $block );
		}
		return '';
	}

	/**
	 * Return "checkuser-ipeditcount" number or false
	 *  if the number is the same as the number of edits
	 *  made by the user on the IP
	 *
	 * @param string $ip_or_range
	 * @return int|false
	 */
	protected function getCountForIPedits( string $ip_or_range ) {
		$conds = $this->getIpConds( $this->mDb, $ip_or_range );
		if ( !$conds ) {
			return false;
		}
		// We are only using startOffset for the period feature.
		if ( $this->startOffset ) {
			$conds[] = $this->mDb->buildComparison( '>=', [ $this->getTimestampField() => $this->startOffset ] );
		}

		// Get counts for this IP / IP range
		$query = $this->mDb->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->conds( $conds )
			->caller( __METHOD__ );
		$ipEdits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $ipEdits <= 1000 ) {
			$ipEdits = $query->fetchRowCount();
		}

		// Get counts for the target on this IP / IP range
		$conds['actor_user'] = $this->target->getId();
		$query = $this->mDb->newSelectQueryBuilder()
			->table( 'cu_changes' )
			->join( 'actor', 'cu_changes_actor', 'cu_changes_actor.actor_id = cuc_actor' )
			->conds( $conds )
			->caller( __METHOD__ );
		$userOnIpEdits = $query->estimateRowCount();
		// If small enough, get a more accurate count
		if ( $userOnIpEdits <= 1000 ) {
			$userOnIpEdits = $query->fetchRowCount();
		}

		if ( $ipEdits > $userOnIpEdits ) {
			return $ipEdits;
		}
		return false;
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		return [
			'fields' => [
				'ip' => 'cuc_ip',
				'ip_hex' => 'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			'tables' => [ 'cu_changes', 'actor' ],
			'conds' => [ 'actor_user' => $this->target->getId() ],
			'join_conds' => [ 'actor' => [ 'JOIN', 'actor_id=cuc_actor' ] ],
			'options' => [
				'GROUP BY' => [ 'ip', 'ip_hex' ],
				'USE INDEX' => [ 'cu_changes' => 'cuc_actor_ip_time' ]
			],
		];
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'last';
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getNavigationBar()
			. '<div id="checkuserresults" class="mw-checkuser-get-ips-results"><ul>';
	}

	/**
	 * Temporary measure until Get IPs query is fixed for pagination.
	 *
	 * @return bool
	 */
	protected function isNavigationBarShown() {
		return false;
	}
}
