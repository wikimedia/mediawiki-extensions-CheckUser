<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use JobQueueGroup;
use LogicException;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
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
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;

/**
 * Query for all edits from an IP, revealing what temporary accounts
 * are using that IP. This pager uses data from the CheckUser table,
 * as only CU should have knowledge of IP activity and therefore data
 * is limited to 90 days.
 *
 * This query is taken from Special:IPContributions and is temporary.
 * It will be replaced in T356292.
 */
class GlobalContributionsPager extends ContributionsPager implements CheckUserQueryInterface {
	private TempUserConfig $tempUserConfig;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private IConnectionProvider $dbProvider;
	private JobQueueGroup $jobQueueGroup;

	/**
	 * @var int Number of revisions to return per wiki
	 */
	public const REVISION_COUNT_LIMIT = 20;

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
	 * @param IConnectionProvider $dbProvider
	 * @param JobQueueGroup $jobQueueGroup
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
		IConnectionProvider $dbProvider,
		JobQueueGroup $jobQueueGroup,
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
		$this->dbProvider = $dbProvider;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Query the central index tables to find wikis that have recently been edited
	 * from by temporary accounts using the target IP.
	 *
	 * @return array
	 */
	private function fetchWikisToQuery() {
		$targetIPConditions = $this->checkUserLookupUtils->getIPTargetExprForColumn(
			$this->target,
			'cite_ip_hex'
		);
		if ( $targetIPConditions === null ) {
			throw new LogicException( "Attempted IP contributions lookup with non-IP target: $this->target" );
		}

		$cuciDb = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );
		$queryBuilder = $cuciDb->newSelectQueryBuilder()
			->select( 'ciwm_wiki' )
			->from( 'cuci_temp_edit' )
			->distinct()
			->where( $targetIPConditions )
			->join( 'cuci_wiki_map', null, 'cite_ciwm_id = ciwm_id' )
			->caller( __METHOD__ );
		return $queryBuilder->fetchFieldValues();
	}

	public function doQuery() {
		parent::doQuery();

		// If we reach here query has been made for a valid target
		// and if there are rows to display they will be displayed.
		// Log that the user has globally viewed the temporary accounts editing on the target IP or IP range.
		$this->jobQueueGroup->push(
			LogTemporaryAccountAccessJob::newSpec(
				$this->getAuthority()->getUser(),
				$this->target,
				TemporaryAccountLogger::ACTION_VIEW_TEMPORARY_ACCOUNTS_ON_IP_GLOBAL,
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function reallyDoQuery( $offset, $limit, $order ) {
		$wikisToQuery = $this->fetchWikisToQuery();
		$results = [];

		// Use a limit for each wiki (specified in T356292), rather than the page limit.
		[ $tables, $fields, $conds, $fname, $options, $join_conds ] =
			$this->buildQueryInfo( $offset, self::REVISION_COUNT_LIMIT, $order );

		foreach ( $wikisToQuery as $wikiId ) {
			$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );
			$resultSet = $dbr->newSelectQueryBuilder()
				->rawTables( $tables )
				->fields( $fields )
				->conds( $conds )
				->caller( $fname )
				->options( $options )
				->joinConds( $join_conds )
				->fetchResultSet();

			$resultsAsArray = iterator_to_array( $resultSet );
			foreach ( $resultsAsArray as $row ) {
				$row->sourcewiki = $wikiId;
			}

			$results = array_merge(
				$results,
				$resultsAsArray
			);
		}

		// Sort the entire results set by timestamp, then apply the limit.
		usort( $results, static function ( $a, $b ) use ( $order ) {
			$aTimestamp = $a->rev_timestamp;
			$bTimestamp = $b->rev_timestamp;

			if ( $aTimestamp == $bTimestamp ) {
				return 0;
			}
			if ( $order === self::QUERY_DESCENDING ) {
				return ( $aTimestamp > $bTimestamp ) ? -1 : 1;
			} else {
				return ( $aTimestamp < $bTimestamp ) ? -1 : 1;
			}
		} );

		return new FakeResultWrapper( array_slice( $results, 0, $limit ) );
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionQuery() {
		$queryBuilder = $this->getDatabase()->newSelectQueryBuilder();

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
			->from( self::CHANGES_TABLE )
			->join( 'actor', 'cu_changes_actor', 'actor_id=cuc_actor' )
			->join( 'revision', 'cu_changes_revision', 'rev_id=cuc_this_oldid' )
			->join( 'page', 'cu_changes_page', 'page_id=cuc_page_id' )
			->join( 'comment', 'cu_changes_comment', 'comment_id=cuc_comment_id' );

		$ipConditions = $this->checkUserLookupUtils->getIPTargetExpr(
			$this->target,
			false,
			self::CHANGES_TABLE
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
	 * @todo This is not a unique field, so results with identical timestamps could go
	 *  missing between pages.
	 *
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'cuc_timestamp';
	}
}
