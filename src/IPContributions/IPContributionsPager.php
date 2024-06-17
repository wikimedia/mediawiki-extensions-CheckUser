<?php

namespace MediaWiki\CheckUser\IPContributions;

use LogicException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\SelectQueryBuilder;

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
	 * @param ?UserIdentity $target IP address for temporary user contributions lookup
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
		$queryBuilder = new SelectQueryBuilder( $this->getDatabase() );
		$queryBuilder
			->select( [
				// The fields prefixed with rev_ must be named that way so that
				// RevisionStore::isRevisionRow can recognize the row.
				'rev_id',
				'rev_page',
				'rev_actor',
				'rev_user' => 'cu_changes_actor.actor_user',
				'rev_user_text' => 'cu_changes_actor.actor_name',
				'rev_timestamp',
				'rev_minor_edit',
				'rev_deleted',
				'rev_len',
				'rev_parent_id',
				'rev_sha1',
				'rev_comment_text' => 'cu_changes_comment.comment_text',
				'rev_comment_data' => 'cu_changes_comment.comment_data',
				'rev_comment_cid' => 'cu_changes_comment.comment_id',
				'page_latest',
				'page_is_new',
				'page_namespace',
				'page_title',
			] )
			->from( 'cu_changes' )
			->join( 'actor', 'cu_changes_actor', 'actor_id=cuc_actor' )
			->join( 'revision', 'cu_changes_revision', 'rev_id=cuc_this_oldid' )
			->join( 'page', 'cu_changes_page', 'page_id=cuc_page_id' )
			->join( 'comment', 'cu_changes_comment', 'comment_id=cuc_comment_id' );

		$ipConditions = $this->checkUserLookupUtils->getIPTargetExpr(
			$this->target,
			false,
			'cu_changes'
		);
		if ( $ipConditions === null ) {
			throw new LogicException( "Attempted IP contributions lookup with non-IP target: $this->target" );
		}

		$tempUserConditions = $this->tempUserConfig->getMatchCondition(
			$this->getDatabase(),
			'actor_name',
			IExpression::LIKE
		);

		$queryBuilder->where( $ipConditions );
		$queryBuilder->where( $tempUserConditions );

		return $queryBuilder->getQueryInfo();
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
