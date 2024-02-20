<?php

namespace MediaWiki\CheckUser\IPContributions;

use IContextSource;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IExpression;

class IPContributionsPager extends ContributionsPager {
	private TempUserConfig $tempUserConfig;
	private CheckUserLookupUtils $checkUserLookupUtils;

	/**
	 * @param LinkRenderer $linkRenderer
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param HookContainer $hookContainer
	 * @param RevisionStore $revisionStore
	 * @param NamespaceInfo $namespaceInfo
	 * @param CommentFormatter $commentFormatter
	 * @param UserFactory $userFactory
	 * @param TempUserConfig $tempUserConfig
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 * @param IContextSource $context
	 * @param array $options
	 * @param ?UserIdentity $target
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory,
		HookContainer $hookContainer,
		RevisionStore $revisionStore,
		NamespaceInfo $namespaceInfo,
		CommentFormatter $commentFormatter,
		UserFactory $userFactory,
		TempUserConfig $tempUserConfig,
		CheckUserLookupUtils $checkUserLookupUtils,
		IContextSource $context,
		array $options,
		?UserIdentity $target = null
	) {
		parent::__construct(
			$linkRenderer,
			$linkBatchFactory,
			$hookContainer,
			$revisionStore,
			$namespaceInfo,
			$commentFormatter,
			$userFactory,
			$context,
			$options,
			$target
		);
		$this->tempUserConfig = $tempUserConfig;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionQuery() {
		$revQuery = $this->revisionStore->getQueryInfo( [ 'page', 'user' ] );
		$queryInfo = [
			'tables' => $revQuery['tables'],
			'fields' => array_merge( $revQuery['fields'], [ 'page_is_new' ] ),
			'conds' => [],
			'options' => [],
			'join_conds' => $revQuery['joins'],
		];

		array_unshift( $queryInfo['tables'], 'cu_changes' );
		$queryInfo['join_conds']['revision'] = [
			'JOIN', [ 'rev_id = cuc_this_oldid' ]
		];

		$queryInfo['conds'][] = $this->checkUserLookupUtils->getIPTargetExpr(
			$this->target,
			false,
			'cu_changes'
		);

		$queryInfo['conds'][] = $this->tempUserConfig->getMatchCondition(
			$this->getDatabase(),
			'actor_name',
			IExpression::LIKE
		);

		return $queryInfo;
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'cuc_timestamp';
		// TODO: parent needs fixing for multi-column pagination so we can do this:
		// return [ [ 'cuc_timestamp', 'cuc_id' ] ];
	}
}
