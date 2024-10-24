<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use ChangesList;
use HtmlArmor;
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
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\ContributionsPager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
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
		$this->templateParser = new TemplateParser( __DIR__ . '/../../templates' );
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

	/**
	 * @inheritDoc
	 */
	protected function populateAttributes( $row, &$attributes ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			parent::populateAttributes( $row, $attributes );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function formatArticleLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatArticleLink( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		$dir = $this->getLanguage()->getDir();
		$link = $this->getLinkRenderer()->makeExternalLink(
			WikiMap::getForeignURL(
				$row->sourcewiki,
				$row->{$this->pageTitleField}
			),
			// The page is only used for its title and namespace,
			// so this is safe.
			$this->currentPage->getPrefixedText(),
			$this->currentPage,
			'',
			[ 'class' => 'mw-contributions-title' ],
		);
		return Html::rawElement( 'bdi', [ 'dir' => $dir ], $link );
	}

	/**
	 * @inheritDoc
	 */
	protected function formatDiffHistLinks( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatDiffHistLinks( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		// Format the diff link. Don't check whether the user can see
		// the revisions, since this would require a cross-wiki permission
		// check. The user may see a permission error when clicking the
		// link instead.
		if ( $row->{$this->revisionParentIdField} != 0 ) {
			$difftext = $this->getLinkRenderer()->makeExternalLink(
				wfAppendQuery(
					WikiMap::getForeignURL(
						$row->sourcewiki,
						$row->{$this->pageTitleField}
					),
					[
						'diff' => 'prev',
						'oldid' => $row->{$this->revisionIdField},
					]
				),
				new HtmlArmor( $this->messages['diff'] ),
				// The page is only used for its title and namespace,
				// so this is safe.
				$this->currentPage,
				'',
				[ 'class' => 'mw-changeslist-diff' ]
			);
		} else {
			$difftext = $this->messages['diff'];
		}

		$histlink = $this->getLinkRenderer()->makeExternalLink(
			wfAppendQuery(
				WikiMap::getForeignURL(
					$row->sourcewiki,
					$row->{$this->pageTitleField}
				),
				[ 'action' => 'history' ]
			),
			new HtmlArmor( $this->messages['hist'] ),
			// The page is only used for its title and namespace,
			// so this is safe.
			$this->currentPage,
			'',
			[ 'class' => 'mw-changeslist-history' ]
		);

		return Html::rawElement( 'span',
			[ 'class' => 'mw-changeslist-links' ],
			// The spans are needed to ensure the dividing '|' elements are not
			// themselves styled as links.
			Html::rawElement( 'span', [], $difftext ) . ' ' .
			// Space needed for separating two words.
			Html::rawElement( 'span', [], $histlink )
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function formatDateLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatDateLink( $row );
		}

		if ( !$this->currentPage ) {
			return '';
		}

		// Re-implemented from ChangesList::revDateLink so we can inject
		// a foreign URL here instead of a local one. Don't check whether the
		// user can see the revisions, since this would require a cross-wiki
		// permission check. The user may see a permission error when clicking
		// the link instead.
		$ts = $row->{$this->revisionTimestampField};
		$date = $this->getLanguage()->userTimeAndDate( $ts, $this->getAuthority()->getUser() );
		$dateLink = $this->getLinkRenderer()->makeExternalLink(
			wfAppendQuery(
				WikiMap::getForeignURL(
					$row->sourcewiki,
					$row->{$this->pageTitleField}
				),
				[ 'oldid' => $row->{$this->revisionIdField} ]
			),
			$date,
			$this->currentPage,
			'',
			[ 'class' => 'mw-changeslist-date' ]
		);
		return Html::rawElement( 'bdi', [ 'dir' => $this->getLanguage()->getDir() ], $dateLink );
	}

	/**
	 * @inheritDoc
	 */
	protected function formatTopMarkText( $row, &$classes ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatTopMarkText( $row, $classes );
		}

		// PagerTools are omitted here because they require cross-wiki
		// permission checks.
		$topmarktext = '';
		if ( $row->{$this->revisionIdField} === $row->page_latest ) {
			$topmarktext .= '<span class="mw-uctop">' . $this->messages['uctop'] . '</span>';
			$classes[] = 'mw-contributions-current';
		}
		return $topmarktext;
	}

	/**
	 * @inheritDoc
	 */
	protected function formatComment( $row ) {
		// Don't show comments for external revisions, since determining
		// their visibility involves cross-wiki permission checks.
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatComment( $row );
		}

		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function formatUserLink( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatUserLink( $row );
		}

		$dir = $this->getLanguage()->getDir();

		if ( $this->revisionUserIsDeleted( $row ) ) {
			$userPageLink = $this->msg( 'empty-username' )->parse();
			$userTalkLink = $this->msg( 'empty-username' )->parse();
		} else {
			$userTitle = Title::makeTitle( NS_USER, $row->{$this->userNameField} );
			$userTalkTitle = Title::makeTitle( NS_USER_TALK, $row->{$this->userNameField} );

			$classes = 'mw-userlink mw-extuserlink';
			// Note that this checks the local temp user config, and assumes that
			// the external wiki has compatible temp user config.
			if ( $this->tempUserConfig->isTempName( $userTitle->getText() ) ) {
				$classes .= ' mw-tempuserlink';
			}

			$userPageLink = $this->getLinkRenderer()->makeExternalLink(
				WikiMap::getForeignURL(
					$row->sourcewiki,
					$userTitle->getPrefixedText()
				),
				$row->{$this->userNameField},
				$userTitle,
				'',
				[ 'class' => $classes ]
			);

			$userTalkLink = $this->getLinkRenderer()->makeExternalLink(
				WikiMap::getForeignURL(
					$row->sourcewiki,
					$userTalkTitle->getPrefixedText()
				),
				$this->msg( 'talkpagelinktext' ),
				$userTalkTitle,
				'',
				[ 'class' => 'mw-usertoollinks-talk' ]
			);
		}

		return ' <span class="mw-changeslist-separator"></span> '
			. Html::rawElement( 'bdi', [ 'dir' => $dir ], $userPageLink ) . ' '
			. $this->msg( 'parentheses' )->rawParams( $userTalkLink )->escaped() . ' ';
	}

	/**
	 * @inheritDoc
	 */
	protected function formatFlags( $row ) {
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatFlags( $row );
		}

		// This is similar to ContributionsPager::formatFlags, but uses the
		// row since the RevisionRecord is not available for external rows.
		$flags = [];
		if ( $row->{$this->revisionParentIdField} == 0 ) {
			$flags[] = ChangesList::flag( 'newpage' );
		}

		if ( $row->{$this->revisionMinorField} ) {
			$flags[] = ChangesList::flag( 'minor' );
		}
		return $flags;
	}

	/**
	 * @inheritDoc
	 */
	protected function formatVisibilityLink( $row ) {
		// Don't show visibility links if the row is for an external wiki, since
		// determining their usability involves cross-wiki permission checks.
		if ( !$this->isFromExternalWiki( $row ) ) {
			return parent::formatVisibilityLink( $row );
		}

		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function formatTags( $row, &$classes ) {
		// Note that for an external tag that is not translated on this wiki,
		// the raw tag name will be displayed. This is because external tags
		// are not supported by ChangeTags::formatSummaryRow.
		return parent::formatTags( $row, $classes );
	}

	/**
	 * Format a link to the source wiki.
	 *
	 * @param mixed $row
	 * @return string
	 */
	protected function formatSourceWiki( $row ) {
		$link = $this->getLinkRenderer()->makeExternalLink(
			WikiMap::getForeignURL(
				$row->sourcewiki,
				''
			),
			WikiMap::getWikiName( $row->sourcewiki ),
			Title::newMainPage(),
			'',
			[ 'class' => 'mw-changeslist-sourcewiki' ],
		);
		return $link . ' <span class="mw-changeslist-separator"></span> ';
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateParams( $row, &$classes ) {
		$templateParams = parent::getTemplateParams( $row, $classes );
		$sourceWiki = $this->formatSourceWiki( $row );
		$templateParams['sourceWiki'] = $sourceWiki;
		return $templateParams;
	}

	/**
	 * @inheritDoc
	 */
	public function getProcessedTemplate( $templateParams ) {
		return $this->templateParser->processTemplate( 'SpecialGlobalContributionsLine', $templateParams );
	}

	/**
	 * Check whether the revision author is deleted. This re-implements
	 * RevisionRecord::isDeleted, since the RevisionRecord is not
	 * available for external rows.
	 *
	 * @param mixed $row
	 * @return bool
	 */
	public function revisionUserIsDeleted( $row ) {
		return ( $row->{$this->revisionDeletedField} & RevisionRecord::DELETED_USER ) ==
			RevisionRecord::DELETED_USER;
	}

	/**
	 * @inheritDoc
	 */
	public function tryCreatingRevisionRecord( $row, $title = null ) {
		if ( $this->isFromExternalWiki( $row ) ) {
			// RevisionRecord doesn't fully support external revision rows.
			return null;
		}
		return parent::tryCreatingRevisionRecord( $row, $title );
	}

	/**
	 * Bool representing whether or not the revision comes from an external wiki
	 *
	 * @param mixed $row
	 * @return bool
	 */
	protected function isFromExternalWiki( $row ) {
		return isset( $row->sourcewiki ) &&
			!WikiMap::isCurrentWikiDbDomain( $row->sourcewiki );
	}
}
