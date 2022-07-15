<?php

namespace MediaWiki\CheckUser\CheckUserPagers;

use ActorMigration;
use CentralIdLookup;
use Exception;
use FormOptions;
use Hooks;
use Html;
use HtmlArmor;
use IContextSource;
use Linker;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Hooks as CUHooks;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
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
	 * Null if $target is a user.
	 * Boolean is $target is a IP / range.
	 *  - False if XFF is not appended
	 *  - True if XFF is appended
	 *
	 * @var null|bool
	 */
	protected $xfor = null;

	/** @var null|string */
	private $lastdate = null;

	/** @var LoggerInterface */
	private $logger;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var UserFactory */
	private $userFactory;

	/** @var RevisionStore */
	private $revisionStore;

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
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $loadBalancer, $specialPageFactory,
			$userIdentityLookup, $actorMigration, $context, $linkRenderer, $limit );
		$this->logger = LoggerFactory::getInstance( 'CheckUser' );
		$this->xfor = $xfor;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->userFactory = $userFactory;
		$this->revisionStore = $revisionStore;
		$this->preCacheMessages();
	}

	/**
	 * Get a streamlined recent changes line with IP data
	 *
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		static $flagCache = [];
		$line = '';
		// Add date headers as needed
		$date = htmlspecialchars(
			$this->getLanguage()->userDate( wfTimestamp( TS_MW, $row->cuc_timestamp ), $this->getUser() )
		);
		if ( $this->lastdate === null ) {
			$this->lastdate = $date;
			$line .= "\n<h4>$date</h4>\n<ul class=\"special\">";
		} elseif ( $date !== $this->lastdate ) {
			$line .= "</ul>\n<h4>$date</h4>\n<ul class=\"special\">";
			$this->lastdate = $date;
		}
		$line .= '<li>';
		// Create diff/hist/page links
		$line .= $this->getLinksFromRow( $row );
		// Show date
		$changesListSeparator = ' ' . Html::element( 'span', [ 'class' => 'mw-changeslist-separator' ] ) . ' ';
		$line .= $changesListSeparator . htmlspecialchars(
				$this->getLanguage()->userTime( wfTimestamp( TS_MW, $row->cuc_timestamp ), $this->getUser() )
			) . $changesListSeparator;
		// Userlinks
		$user = $this->userFactory->newFromUserIdentity(
			new UserIdentityValue( $row->cuc_user, $row->cuc_user_text )
		);
		if ( !IPUtils::isIPAddress( $row->cuc_user_text ) ) {
			$idforlinknfn = -1;
		} else {
			$idforlinknfn = $row->cuc_user;
		}
		$classnouser = false;
		if ( IPUtils::isIPAddress( $row->cuc_user_text ) !== IPUtils::isIPAddress( $user ) ) {
			// User does not exist
			$idforlink = -1;
			$classnouser = true;
		} else {
			$idforlink = $row->cuc_user;
		}
		if ( $classnouser ) {
			$line .= '<span class=\'mw-checkuser-nonexistent-user\'>';
		} else {
			$line .= '<span>';
		}
		$line .= Linker::userLink(
				$idforlinknfn, $row->cuc_user_text, $row->cuc_user_text ) . '</span>';
		$line .= Linker::userToolLinksRedContribs(
			$idforlink,
			$row->cuc_user_text,
			$user->getEditCount(),
			// don't render parentheses in HTML markup (CSS will provide)
			false
		);
		// Get block info
		if ( isset( $flagCache[$row->cuc_user_text] ) ) {
			$flags = $flagCache[$row->cuc_user_text];
		} else {
			$ip = IPUtils::isIPAddress( $row->cuc_user_text ) ? $row->cuc_user_text : '';
			$flags = $this->userBlockFlags( $ip, $row->cuc_user, $user );
			$flagCache[$row->cuc_user_text] = $flags;
		}
		// Add any block information
		if ( count( $flags ) ) {
			$line .= ' ' . implode( ' ', $flags );
		}
		// Action text, hackish ...
		if ( $row->cuc_actiontext ) {
			$line .= ' ' . Linker::formatComment( $row->cuc_actiontext ) . ' ';
		}
		// Comment
		if ( $row->cuc_type == RC_EDIT || $row->cuc_type == RC_NEW ) {
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

				if ( !$revRecord ) {
					// This shouldn't happen, CheckUser points to a revision
					// that isn't in revision nor archive table?
					throw new Exception(
						"Couldn't fetch revision cu_changes table links to (cuc_this_oldid $row->cuc_this_oldid)"
					);
				}
			}
			if ( RevisionRecord::userCanBitfield(
				$revRecord->getVisibility(),
				RevisionRecord::DELETED_COMMENT,
				$this->getUser()
			) ) {
				$line .= Linker::commentBlock( $row->cuc_comment );
			} else {
				$line .= Linker::commentBlock(
					$this->msg( 'rev-deleted-comment' )->text(),
					null,
					false,
					null,
					false
				);
			}
		} else {
			$line .= Linker::commentBlock( $row->cuc_comment );
		}
		$line .= '<br />&#160; &#160; &#160; &#160; <small>';
		// IP
		$line .= ' <strong>IP</strong>: ';
		$line .= $this->getSelfLink( $row->cuc_ip,
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
			$c = $trusted ? '#F0FFF0' : '#FFFFCC';
			$line .= '&#160;&#160;&#160;';
			$line .= '<span class="mw-checkuser-xff" style="background-color: ' . $c . '">' .
				'<strong>XFF</strong>: ';
			$line .= $this->getSelfLink( $row->cuc_xff,
				[
					'user' => $client . '/xff',
					'reason' => $this->opts->getValue( 'reason' )
				]
			);
			$line .= '</span>';
		}
		// User agent
		$line .= '&#160;&#160;&#160;<span class="mw-checkuser-agent" style="color:#888;">' .
			htmlspecialchars( $row->cuc_agent ) . '</span>';

		$line .= "</small></li>\n";

		return $line;
	}

	/**
	 * @param stdClass $row
	 * @return string diff, hist and page other links related to the change
	 */
	protected function getLinksFromRow( $row ): string {
		$links = [];
		// Log items
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
			} else {
				$this->skipQuery = true;
				return $queryInfo;
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
	protected function preprocessResults( $result ) {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $result as $row ) {
			if ( $row->cuc_title !== '' ) {
				$lb->add( $row->cuc_namespace, $row->cuc_title );
			}
			if ( $this->xfor === null ) {
				$userText = str_replace( ' ', '_', $row->cuc_user_text );
				$lb->add( NS_USER, $userText );
				$lb->add( NS_USER_TALK, $userText );
			}
		}
		$lb->execute();
		$result->seek( 0 );
	}

	/**
	 * Always show the navigation bar on the 'Get edits' screen
	 * so that the user can reduce the size of the page if they
	 * are interested in one or two items from the top.
	 *
	 * @return bool Always true.
	 */
	protected function isNavigationBarShown() {
		return true;
	}
}
