<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ActorMigration;
use CentralIdLookup;
use FormOptions;
use Hooks;
use Html;
use HtmlArmor;
use IContextSource;
use Linker;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\CheckUser\Hooks as CUHooks;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use SpecialPage;
use stdClass;
use Title;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

class CheckUserGetEditsPager extends AbstractCheckUserPager {

	/**
	 * @var string[] Used to cache frequently used messages
	 */
	protected $message = [];

	/**
	 * @var array The cached results of AbstractCheckUserPager::userBlockFlags with the key as
	 *  the row's cuc_user_text.
	 */
	private $flagCache = [];

	/**
	 * Null if $target is a user.
	 * Boolean is $target is a IP / range.
	 *  - False if XFF is not appended
	 *  - True if XFF is appended
	 *
	 * @var null|bool
	 */
	protected $xfor = null;

	/** @var array */
	protected $formattedRevisionComments = [];

	/** @var array */
	protected $usernameVisibility = [];

	/** @var LoggerInterface */
	private $logger;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var UserEditTracker */
	private $userEditTracker;

	/**
	 * @param FormOptions $opts
	 * @param UserIdentity $target
	 * @param bool|null $xfor
	 * @param string $logType
	 * @param TokenQueryManager $tokenQueryManager
	 * @param UserGroupManager $userGroupManager
	 * @param CentralIdLookup $centralIdLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param ILoadBalancer $loadBalancer
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param ActorMigration $actorMigration
	 * @param UserFactory $userFactory
	 * @param RevisionStore $revisionStore
	 * @param CheckUserLogService $checkUserLogService
	 * @param CommentFormatter $commentFormatter
	 * @param UserEditTracker $userEditTracker
	 * @param IContextSource|null $context
	 * @param LinkRenderer|null $linkRenderer
	 * @param ?int $limit
	 */
	public function __construct(
		FormOptions $opts,
		UserIdentity $target,
		?bool $xfor,
		string $logType,
		TokenQueryManager $tokenQueryManager,
		UserGroupManager $userGroupManager,
		CentralIdLookup $centralIdLookup,
		LinkBatchFactory $linkBatchFactory,
		ILoadBalancer $loadBalancer,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		ActorMigration $actorMigration,
		UserFactory $userFactory,
		RevisionStore $revisionStore,
		CheckUserLogService $checkUserLogService,
		CommentFormatter $commentFormatter,
		UserEditTracker $userEditTracker,
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $loadBalancer, $specialPageFactory,
			$userIdentityLookup, $actorMigration, $checkUserLogService, $userFactory,
			$context, $linkRenderer, $limit );
		$this->logger = LoggerFactory::getInstance( 'CheckUser' );
		$this->xfor = $xfor;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->revisionStore = $revisionStore;
		$this->commentFormatter = $commentFormatter;
		$this->userEditTracker = $userEditTracker;
		$this->preCacheMessages();
		$this->mGroupByDate = true;
	}

	/**
	 * Get a streamlined recent changes line with IP data
	 *
	 * @inheritDoc
	 */
	public function formatRow( $row ): string {
		$templateParams = [];
		// Create diff/hist/page links
		$templateParams['links'] = $this->getLinksFromRow( $row );
		// Show date
		$templateParams['timestamp'] =
			$this->getLanguage()->userTime( wfTimestamp( TS_MW, $row->cuc_timestamp ), $this->getUser() );
		// Userlinks
		$user = new UserIdentityValue( $row->cuc_user ?? 0, $row->cuc_user_text );
		if ( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW ) {
			$hidden = !$this->usernameVisibility[$row->cuc_this_oldid];
		} else {
			$hidden = $this->userFactory->newFromUserIdentity( $user )->isHidden()
				&& !$this->getAuthority()->isAllowed( 'hideuser' );
		}
		if ( $hidden ) {
			$templateParams['userLink'] = Html::element(
				'span',
				[ 'class' => 'history-deleted' ],
				$this->msg( 'rev-deleted-user' )->text()
			);
		} else {
			if ( !IPUtils::isIPAddress( $user ) && !$user->isRegistered() ) {
				$templateParams['userLinkClass'] = 'mw-checkuser-nonexistent-user';
			}
			$templateParams['userLink'] = Linker::userLink( $user->getId(), $row->cuc_user_text, $row->cuc_user_text );
			$templateParams['userToolLinks'] = Linker::userToolLinksRedContribs(
				$user->getId(),
				$row->cuc_user_text,
				$this->userEditTracker->getUserEditCount( $user ),
				// don't render parentheses in HTML markup (CSS will provide)
				false
			);
			// Add any block information
			$templateParams['flags'] = $this->flagCache[$row->cuc_user_text];
		}
		// Action text, hackish ...
		$templateParams['actionText'] = $this->commentFormatter->format( $row->cuc_actiontext );
		// Comment
		if ( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW ) {
			$templateParams['comment'] = $this->formattedRevisionComments[$row->cuc_this_oldid];
		} else {
			$templateParams['comment'] = $this->commentFormatter->formatBlock( $row->cuc_comment );
		}
		// IP
		$templateParams['ipLink'] = $this->getSelfLink( $row->cuc_ip,
			[
				'user' => $row->cuc_ip,
				'reason' => $this->opts->getValue( 'reason' )
			]
		);
		// XFF
		if ( $row->cuc_xff != null ) {
			// Flag our trusted proxies
			list( $client ) = CUHooks::getClientIPfromXFF( $row->cuc_xff );
			// XFF was trusted if client came from it
			$trusted = ( $client === $row->cuc_ip );
			$templateParams['xffTrusted'] = $trusted;
			$templateParams['xff'] = $this->getSelfLink( $row->cuc_xff,
				[
					'user' => $client . '/xff',
					'reason' => $this->opts->getValue( 'reason' )
				]
			);
		}
		// User agent
		$templateParams['userAgent'] = $row->cuc_agent;

		return $this->templateParser->processTemplate( 'GetEditsLine', $templateParams );
	}

	/**
	 * @param stdClass $row
	 * @return string diff, hist and page other links related to the change
	 */
	protected function getLinksFromRow( stdClass $row ): string {
		$links = [];
		// Log items
		// Due to T315224 triple equals for cuc_type does not work for sqlite.
		if ( $row->cuc_type == RC_LOG ) {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			$links['log'] = Html::rawElement(
				'span',
				[ 'class' => 'mw-changeslist-links' ],
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Log' ),
					new HtmlArmor( $this->message['log'] ),
					[],
					[ 'page' => $title->getPrefixedText() ]
				)
			);
		} else {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			// New pages
			if ( $row->cuc_type == RC_NEW ) {
				$links['diffHistLinks'] = Html::rawElement( 'span', [], $this->message['diff'] );
			} else {
				// Diff link
				$links['diffHistLinks'] = Html::rawElement( 'span', [],
					$this->getLinkRenderer()->makeKnownLink(
						$title,
						new HtmlArmor( $this->message['diff'] ),
						[],
						[
							'curid' => $row->cuc_page_id,
							'diff' => $row->cuc_this_oldid,
							'oldid' => $row->cuc_last_oldid
						]
					)
				);
			}
			// History link
			$links['diffHistLinks'] .= ' ' . Html::rawElement( 'span', [],
				$this->getLinkRenderer()->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['hist'] ),
					[],
					[
						'curid' => $title->exists() ? $row->cuc_page_id : null,
						'action' => 'history'
					]
				)
			);
			$links['diffHistLinks'] = Html::rawElement(
				'span',
				[ 'class' => 'mw-changeslist-links' ],
				$links['diffHistLinks']
			);
			$links['diffHistLinksSeparator'] = Html::element(
				'span',
				[ 'class' => 'mw-changeslist-separator' ]
			);
			// Some basic flags
			if ( $row->cuc_type == RC_NEW ) {
				$links['newpage'] = Html::rawElement(
					'abbr',
					[ 'class' => 'newpage' ],
					$this->message['newpageletter']
				);
			}
			if ( $row->cuc_minor ) {
				$links['minor'] = Html::rawElement(
					"abbr",
					[ 'class' => 'minoredit' ],
					$this->message['minoreditletter']
				);
			}
			// Page link
			$links['title'] = $this->getLinkRenderer()->makeLink( $title );
		}

		Hooks::run( 'SpecialCheckUserGetLinksFromRow', [ $this, $row, &$links ] );
		// @phan-suppress-next-line PhanRedundantCondition May set by hook
		if ( is_array( $links ) ) {
			return implode( ' ', $links );
		} else {
			$this->logger->warning(
				__METHOD__ . ': Expected array from SpecialCheckUserGetLinksFromRow $links param,'
				. ' but received ' . gettype( $links )
			);
			return '';
		}
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	protected function preCacheMessages() {
		if ( $this->message === [] ) {
			$msgKeys = [ 'diff', 'hist', 'minoreditletter', 'newpageletter', 'blocklink', 'log' ];
			foreach ( $msgKeys as $msg ) {
				$this->message[$msg] = $this->msg( $msg )->escaped();
			}
		}
	}

	/** @inheritDoc */
	protected function getEmptyBody(): string {
		return $this->noMatchesMessage( $this->target->getName(), !$this->xfor ) . "\n";
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$queryInfo = [
			'fields' => [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent',
			],
			'tables' => [ 'cu_changes' ],
			'conds' => [],
			'options' => [],
		];
		if ( $this->xfor === null ) {
			$queryInfo['conds']['cuc_user'] = $this->target->getId();
			$queryInfo['options']['USE INDEX'] = 'cuc_user_ip_time';
		} else {
			$queryInfo['options']['USE INDEX'] = $this->xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
			$ipConds = self::getIpConds( $this->mDb, $this->target->getName(), $this->xfor );
			if ( $ipConds ) {
				$queryInfo['conds'] = array_merge( $queryInfo['conds'], $ipConds );
			}
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'cuc_timestamp';
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		return '</ul></div>' . parent::getEndBody();
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getNavigationBar()
			. '<div id="checkuserresults" class="mw-checkuser-get-edits-results">';
	}

	/** @inheritDoc */
	protected function preprocessResults( $result ) {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		$revisions = [];
		$missingRevisions = [];
		foreach ( $result as $row ) {
			if ( $row->cuc_title !== '' ) {
				$lb->add( $row->cuc_namespace, $row->cuc_title );
			}
			if ( $this->xfor === null ) {
				$userText = str_replace( ' ', '_', $row->cuc_user_text );
				$lb->add( NS_USER, $userText );
				$lb->add( NS_USER_TALK, $userText );
			}
			// Add the row to the flag cache
			if ( !isset( $this->flagCache[$row->cuc_user_text] ) ) {
				$user = new UserIdentityValue( $row->cuc_user, $row->cuc_user_text );
				$ip = IPUtils::isIPAddress( $row->cuc_user_text ) ? $row->cuc_user_text : '';
				$flags = $this->userBlockFlags( $ip, $user );
				$this->flagCache[$row->cuc_user_text] = $flags;
			}
			// Batch process comments
			if (
				( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW ) &&
				!array_key_exists( $row->cuc_this_oldid, $revisions )
			) {
				$revRecord = $this->revisionStore->getRevisionById( $row->cuc_this_oldid );
				if ( !$revRecord ) {
					// Assume revision is deleted
					$queryInfo = $this->revisionStore->getArchiveQueryInfo();
					$tmp = $this->mDb->newSelectQueryBuilder()
						->tables( $queryInfo['tables'] )
						->fields( $queryInfo['fields'] )
						->conds( [ 'ar_rev_id' => $row->cuc_this_oldid ] )
						->joinConds( $queryInfo['joins'] )
						->caller( __METHOD__ )
						->fetchRow();
					if ( $tmp ) {
						$revRecord = $this->revisionStore->newRevisionFromArchiveRow( $tmp );
					}
				}
				if ( !$revRecord ) {
					// This shouldn't happen, CheckUser points to a revision
					// that isn't in revision nor archive table?
					$this->logger->warning(
						"Couldn't fetch revision cu_changes table links to (cuc_this_oldid $row->cuc_this_oldid)"
					);
					// Show the comment in this case as the empty string to indicate that it's missing.
					$missingRevisions[$row->cuc_this_oldid] = '';
				} else {
					$revisions[$row->cuc_this_oldid] = $revRecord;

					$this->usernameVisibility[$row->cuc_this_oldid] = RevisionRecord::userCanBitfield(
						$revRecord->getVisibility(),
						RevisionRecord::DELETED_USER,
						$this->getAuthority()
					);
				}
			}
		}
		// Batch format revision comments
		$this->formattedRevisionComments = array_replace(
			$missingRevisions,
			$this->commentFormatter->createRevisionBatch()
				->revisions( $revisions )
				->authority( $this->getAuthority() )
				->samePage( false )
				->useParentheses( false )
				->indexById()
				->execute()
		);
		$lb->execute();
		$result->seek( 0 );
	}

	/**
	 * Always show the navigation bar on the 'Get edits' screen
	 * so that the user can reduce the size of the page if they
	 * are interested in one or two items from the top. The only
	 * exception to this is when there are no results.
	 *
	 * @return bool
	 */
	protected function isNavigationBarShown(): bool {
		return $this->getNumRows() !== 0;
	}
}
