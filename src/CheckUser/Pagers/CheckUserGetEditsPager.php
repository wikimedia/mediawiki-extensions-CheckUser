<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ActorMigration;
use CentralIdLookup;
use Html;
use HtmlArmor;
use IContextSource;
use Linker;
use LogFormatter;
use LogicException;
use LogPage;
use ManualLogEntry;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsBatchFormatterResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Html\FormOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use SpecialPage;
use stdClass;
use Wikimedia\AtEase\AtEase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class CheckUserGetEditsPager extends AbstractCheckUserPager {

	/**
	 * @var string[] Used to cache frequently used messages
	 */
	protected array $message = [];

	/**
	 * @var array The cached results of AbstractCheckUserPager::userBlockFlags with the key as
	 *  the row's user_text.
	 */
	private array $flagCache = [];

	/** @var array A map of revision IDs to the formatted comment associated with that revision. */
	protected array $formattedRevisionComments = [];

	/** @var array A map where usernames are keys and whether they are hidden are values. */
	protected array $usernameVisibility = [];

	/**
	 * @var ClientHintsBatchFormatterResults Formatted ClientHintsData objects that can be looked up by a reference ID.
	 */
	protected ClientHintsBatchFormatterResults $formattedClientHintsData;

	private LoggerInterface $logger;
	private LinkBatchFactory $linkBatchFactory;
	private RevisionStore $revisionStore;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private CommentFormatter $commentFormatter;
	private UserEditTracker $userEditTracker;
	private HookRunner $hookRunner;
	private CheckUserUtilityService $checkUserUtilityService;
	private CommentStore $commentStore;
	private UserAgentClientHintsLookup $clientHintsLookup;
	private UserAgentClientHintsFormatter $clientHintsFormatter;

	/**
	 * @param FormOptions $opts
	 * @param UserIdentity $target
	 * @param bool|null $xfor
	 * @param string $logType
	 * @param TokenQueryManager $tokenQueryManager
	 * @param UserGroupManager $userGroupManager
	 * @param CentralIdLookup $centralIdLookup
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IConnectionProvider $dbProvider
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param ActorMigration $actorMigration
	 * @param UserFactory $userFactory
	 * @param RevisionStore $revisionStore
	 * @param ArchivedRevisionLookup $archivedRevisionLookup
	 * @param CheckUserLogService $checkUserLogService
	 * @param CommentFormatter $commentFormatter
	 * @param UserEditTracker $userEditTracker
	 * @param HookRunner $hookRunner
	 * @param CheckUserUtilityService $checkUserUtilityService
	 * @param CommentStore $commentStore
	 * @param UserAgentClientHintsLookup $clientHintsLookup
	 * @param UserAgentClientHintsFormatter $clientHintsFormatter
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
		IConnectionProvider $dbProvider,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		ActorMigration $actorMigration,
		UserFactory $userFactory,
		RevisionStore $revisionStore,
		ArchivedRevisionLookup $archivedRevisionLookup,
		CheckUserLogService $checkUserLogService,
		CommentFormatter $commentFormatter,
		UserEditTracker $userEditTracker,
		HookRunner $hookRunner,
		CheckUserUtilityService $checkUserUtilityService,
		CommentStore $commentStore,
		UserAgentClientHintsLookup $clientHintsLookup,
		UserAgentClientHintsFormatter $clientHintsFormatter,
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $dbProvider, $specialPageFactory,
			$userIdentityLookup, $actorMigration, $checkUserLogService, $userFactory,
			$context, $linkRenderer, $limit );
		$this->checkType = SpecialCheckUser::SUBTYPE_GET_EDITS;
		$this->logger = LoggerFactory::getInstance( 'CheckUser' );
		$this->xfor = $xfor;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->revisionStore = $revisionStore;
		$this->commentFormatter = $commentFormatter;
		$this->userEditTracker = $userEditTracker;
		$this->hookRunner = $hookRunner;
		$this->checkUserUtilityService = $checkUserUtilityService;
		$this->commentStore = $commentStore;
		$this->clientHintsLookup = $clientHintsLookup;
		$this->clientHintsFormatter = $clientHintsFormatter;
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
			$this->getLanguage()->userTime( wfTimestamp( TS_MW, $row->timestamp ), $this->getUser() );
		// Normalise user text if IP for clarity and compatibility with ipLink below
		$user_text = $row->user_text;
		'@phan-var string $user_text';
		if ( IPUtils::isIPAddress( $user_text ) ) {
			$user_text = IPUtils::prettifyIP( $user_text ) ?? $user_text;
		}
		// Userlinks
		$user = new UserIdentityValue( $row->user ?? 0, $user_text );
		if ( $row->type == RC_EDIT || $row->type == RC_NEW ) {
			$hidden = !$this->usernameVisibility[$row->this_oldid];
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
			$userLinks = self::buildUserLinks(
				$user->getId(),
				$user_text,
				$this->userEditTracker->getUserEditCount( $user )
			);
			$templateParams['userLink'] = $userLinks['userLink'];
			$templateParams['userToolLinks'] = $userLinks['userToolLinks'];
			// Add any block information
			$templateParams['flags'] = $this->flagCache[$row->user_text];
		}
		$templateParams['actionText'] = $this->getActionText( $row, $user );
		// Comment
		if ( $row->type == RC_EDIT || $row->type == RC_NEW ) {
			$templateParams['comment'] = $this->formattedRevisionComments[$row->this_oldid];
		} else {
			$comment = $this->commentStore->getComment( 'comment', $row );
			$templateParams['comment'] = $this->commentFormatter->formatBlock( $comment->text );
		}
		// IP
		$ip = IPUtils::prettifyIP( $row->ip ) ?? $row->ip ?? '';
		$templateParams['ipLink'] = $this->getSelfLink( $ip,
			[
				'user' => $ip,
				'reason' => $this->opts->getValue( 'reason' )
			]
		);
		// XFF
		if ( $row->xff != null ) {
			// Flag our trusted proxies
			[ $client ] = $this->checkUserUtilityService->getClientIPfromXFF( $row->xff );
			// XFF was trusted if client came from it
			$trusted = ( $client === $row->ip );
			$templateParams['xffTrusted'] = $trusted;
			$templateParams['xff'] = $this->getSelfLink( $row->xff,
				[
					'user' => $client . '/xff',
					'reason' => $this->opts->getValue( 'reason' )
				]
			);
		}
		// User agent
		$templateParams['userAgent'] = $row->agent;
		// Display Client Hints data if display is enabled
		if ( $this->displayClientHints ) {
			// If ::getStringForReferenceId returns null, the mustache template will
			// interpret this as false and then not display the Client Hints data
			// in the same way that if $this->displayClientHints data was false.
			$templateParams['clientHints'] = $this->formattedClientHintsData->getStringForReferenceId(
				$row->client_hints_reference_id,
				$row->client_hints_reference_type
			);
		}

		return $this->templateParser->processTemplate( 'GetEditsLine', $templateParams );
	}

	/**
	 * Gets the actiontext associated with the given $row.
	 *
	 * @param stdClass $row The database row
	 * @param UserIdentity $user The user who is the performer for this row.
	 * @return string The actiontext
	 */
	private function getActionText( stdClass $row, UserIdentity $user ): string {
		if ( $this->eventTableReadNew && $row->type == RC_LOG && isset( $row->log_type ) && $row->log_type ) {
			// Log action text taken from the LogFormatter for the entry being displayed.
			$logEntry = new ManualLogEntry( $row->log_type, $row->log_action );
			if ( $row->log_params !== null ) {
				// Suppress E_NOTICE from PHP's unserialize if the log parameters are legacy parameters.
				// This is similar to DatabaseLogEntry::getParameters.
				AtEase::suppressWarnings();
				$parsedLogParams = ManualLogEntry::extractParams( $row->log_params );
				AtEase::restoreWarnings();
				if ( $parsedLogParams === false ) {
					// Use the LogPage::extractParams method to extract the log parameters as they are probably
					// legacy parameters.
					$parsedLogParams = LogPage::extractParams( $row->log_params );
					$logEntry->setLegacy( true );
				}
				$logEntry->setParameters( $parsedLogParams );
			}
			$logEntry->setPerformer( $user );
			if ( $row->title ) {
				$logEntry->setTarget( Title::makeTitle( $row->namespace, $row->title ) );
			} elseif ( $row->page_id ) {
				$logEntry->setTarget( Title::newFromID( $row->page_id ) );
			}
			$logEntry->setTimestamp( $row->timestamp );
			$logEntry->setDeleted( $row->log_deleted );
			$logFormatter = LogFormatter::newFromEntry( $logEntry );
			$logFormatter->setAudience( LogFormatter::FOR_THIS_USER );
			return $logFormatter->getActionText();
		} else {
			// Action text, hackish ...
			return $this->commentFormatter->format( $row->actiontext ?? '' );
		}
	}

	/**
	 * @param stdClass $row
	 * @return string diff, hist and page other links related to the change
	 */
	protected function getLinksFromRow( stdClass $row ): string {
		$links = [];
		// Log items
		// Due to T315224 triple equals for type does not work for sqlite.
		if ( $row->type == RC_LOG ) {
			$title = Title::makeTitle( $row->namespace, $row->title );
			$links['log'] = '';
			if ( $this->eventTableReadNew && isset( $row->log_id ) ) {
				$links['log'] = Html::rawElement( 'span', [],
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'Log' ),
						new HtmlArmor( $this->message['checkuser-log-link-text'] ),
						[],
						[ 'logid' => $row->log_id ]
					)
				) . ' ';
			}
			$links['log'] .= Html::rawElement( 'span', [],
					$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Log' ),
					new HtmlArmor( $this->message['checkuser-logs-link-text'] ),
					[],
					[ 'page' => $title->getPrefixedText() ]
				)
			);
			$links['log'] = Html::rawElement(
				'span',
				[ 'class' => 'mw-changeslist-links' ],
				$links['log']
			);
		} else {
			$title = Title::makeTitle( $row->namespace, $row->title );
			// New pages
			if ( $row->type == RC_NEW ) {
				$links['diffHistLinks'] = Html::rawElement( 'span', [], $this->message['diff'] );
			} else {
				// Diff link
				$links['diffHistLinks'] = Html::rawElement( 'span', [],
					$this->getLinkRenderer()->makeKnownLink(
						$title,
						new HtmlArmor( $this->message['diff'] ),
						[],
						[
							'curid' => $row->page_id,
							'diff' => $row->this_oldid,
							'oldid' => $row->last_oldid
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
						'curid' => $title->exists() ? $row->page_id : null,
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
			if ( $row->type == RC_NEW ) {
				$links['newpage'] = Html::rawElement(
					'abbr',
					[ 'class' => 'newpage' ],
					$this->message['newpageletter']
				);
			}
			if ( $row->minor ) {
				$links['minor'] = Html::rawElement(
					"abbr",
					[ 'class' => 'minoredit' ],
					$this->message['minoreditletter']
				);
			}
			// Page link
			$links['title'] = $this->getLinkRenderer()->makeLink( $title );
		}

		$this->hookRunner->onSpecialCheckUserGetLinksFromRow( $this, $row, $links );
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
			$msgKeys = [
				'diff', 'hist', 'minoreditletter', 'newpageletter',
				'blocklink', 'checkuser-log-link-text', 'checkuser-logs-link-text'
			];
			foreach ( $msgKeys as $msg ) {
				$this->message[$msg] = $this->msg( $msg )->escaped();
			}
		}
	}

	/**
	 * Build (or fetch, if previously built) links for a user
	 *
	 * @param int $userId User identifier
	 * @param string $userText User name or IP address
	 * @param ?int $edits User edit count
	 *
	 * @return array{userLink:string,userToolLinks:string} A map with two keys, forwarding the supplied arguments:
	 *   - userLink: (string) the result of Linker::userLink
	 *   - userToolLinks: (string) the result of Linker::userToolLinksRedContribs
	 */
	protected static function buildUserLinks( int $userId, string $userText, ?int $edits ): array {
		static $cache = [];
		if ( !isset( $cache[$userText] ) ) {
			// Simple enough to keep as an associative array instead of a data class
			$cache[$userText] = [
				"userLink" => Linker::userLink( $userId, $userText, $userText ),
				"userToolLinks" => Linker::userToolLinksRedContribs(
					$userId,
					$userText,
					$edits,
					// don't render parentheses in HTML markup (CSS will provide)
					false
				)
			];
		}

		return $cache[$userText];
	}

	/** @inheritDoc */
	public function getQueryInfo( ?string $table = null ): array {
		if ( $table === null ) {
			throw new LogicException(
				"This ::getQueryInfo method must be provided with the table to generate " .
				"the correct query info"
			);
		}

		if ( $table === self::CHANGES_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuChanges();
		} elseif ( $table === self::LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuLogEvent();
		} elseif ( $table === self::PRIVATE_LOG_EVENT_TABLE ) {
			$queryInfo = $this->getQueryInfoForCuPrivateEvent();
		}

		$queryInfo['options']['USE INDEX'] = [ $table => $this->getIndexName( $table ) ];

		if ( $this->xfor === null ) {
			$queryInfo['conds']['actor_user'] = $this->target->getId();
		} else {
			$ipConds = self::getIpConds( $this->mDb, $this->target->getName(), $this->xfor, $table );
			if ( $ipConds ) {
				$queryInfo['conds'] = array_merge( $queryInfo['conds'], $ipConds );
			}
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuChanges(): array {
		$commentQuery = $this->commentStore->getJoin( 'cuc_comment' );
		$queryInfo = [
			'fields' => [
				'namespace' => 'cuc_namespace',
				'title' => 'cuc_title',
				'actiontext' => 'cuc_actiontext',
				'timestamp' => 'cuc_timestamp',
				'minor' => 'cuc_minor',
				'page_id' => 'cuc_page_id',
				'type' => 'cuc_type',
				'this_oldid' => 'cuc_this_oldid',
				'last_oldid' => 'cuc_last_oldid',
				'ip' => 'cuc_ip',
				'xff' => 'cuc_xff',
				'agent' => 'cuc_agent',
				'user' => 'actor_user',
				'user_text' => 'actor_name',
				// Needed for rows with cuc_type as RC_LOG.
				'comment_text',
				'comment_data',
			],
			'tables' => [ 'cu_changes', 'actor_cuc_user' => 'actor' ] + $commentQuery['tables'],
			'conds' => [],
			'join_conds' => [
				'actor_cuc_user' => [ 'JOIN', 'actor_cuc_user.actor_id=cuc_actor' ]
			] + $commentQuery['joins'],
			'options' => [],
		];
		// When reading new, only select results from cu_changes that are
		// for read new (defined as those with cuc_only_for_read_old set to 0).
		if ( $this->eventTableReadNew ) {
			$queryInfo['conds']['cuc_only_for_read_old'] = 0;
		}
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES
				];
			$queryInfo['fields']['client_hints_reference_type'] = UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuLogEvent(): array {
		$commentQuery = $this->commentStore->getJoin( 'log_comment' );
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cule_timestamp',
				'title' => 'log_title',
				'page_id' => 'log_page',
				'namespace' => 'log_namespace',
				'ip' => 'cule_ip',
				'ip_hex' => 'cule_ip_hex',
				'xff' => 'cule_xff',
				'xff_hex' => 'cule_xff_hex',
				'agent' => 'cule_agent',
				'user' => 'actor_user',
				'user_text' => 'actor_name',
				'comment_text',
				'comment_data',
				'log_type' => 'log_type',
				'log_action' => 'log_action',
				'log_params' => 'log_params',
				'log_deleted' => 'log_deleted',
				'log_id' => 'cule_log_id',
			],
			'tables' => [
				'cu_log_event', 'logging_cule_log_id' => 'logging', 'actor_log_actor' => 'actor'
			] + $commentQuery['tables'],
			'conds' => [],
			'join_conds' => [
				'logging_cule_log_id' => [ 'JOIN', 'logging_cule_log_id.log_id=cule_log_id' ],
				'actor_log_actor' => [ 'JOIN', 'actor_log_actor.actor_id=log_actor' ],
			] + $commentQuery['joins'],
			'options' => [],
		];
		if ( $this->mDb->getType() == 'postgres' ) {
			// On postgres the cuc_type type is a smallint.
			$queryInfo['fields'] += [
				'type' => 'CAST(' . RC_LOG . ' AS smallint)'
			];
		} else {
			// Other DBs can handle converting RC_LOG to the
			// correct type.
			$queryInfo['fields'] += [
				'type' => RC_LOG
			];
		}
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
				];
			$queryInfo['fields']['client_hints_reference_type'] = UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuPrivateEvent(): array {
		$commentQuery = $this->commentStore->getJoin( 'cupe_comment' );
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cupe_timestamp',
				'title' => 'cupe_title',
				'page_id' => 'cupe_page',
				'namespace' => 'cupe_namespace',
				'ip' => 'cupe_ip',
				'ip_hex' => 'cupe_ip_hex',
				'xff' => 'cupe_xff',
				'xff_hex' => 'cupe_xff_hex',
				'agent' => 'cupe_agent',
				'user' => 'actor_user',
				'user_text' => 'actor_name',
				'comment_text',
				'comment_data',
				'log_type' => 'cupe_log_type',
				'log_action' => 'cupe_log_action',
				'log_params' => 'cupe_params',
				// cu_private_event log events cannot be deleted or suppressed.
				'log_deleted' => 0,
			],
			'tables' => [ 'cu_private_event', 'actor_cupe_actor' => 'actor' ] + $commentQuery['tables'],
			'conds' => [],
			'join_conds' => [
				'actor_cupe_actor' => [ 'JOIN', 'actor_cupe_actor.actor_id=cupe_actor' ]
			] + $commentQuery['joins'],
			'options' => [],
		];
		if ( $this->mDb->getType() == 'postgres' ) {
			// On postgres the cuc_type type is a smallint.
			$queryInfo['fields'] += [
				'type' => 'CAST(' . RC_LOG . ' AS smallint)'
			];
		} else {
			// Other DBs can handle converting RC_LOG to the
			// correct type.
			$queryInfo['fields'] += [
				'type' => RC_LOG
			];
		}
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
				];
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getCheckUserHelperFieldsetHTML() . $this->getNavigationBar()
			. '<div id="checkuserresults" class="mw-checkuser-get-edits-results">';
	}

	/** @inheritDoc */
	protected function preprocessResults( $result ) {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		$revisions = [];
		$missingRevisions = [];
		$referenceIds = new ClientHintsReferenceIds();
		foreach ( $result as $row ) {
			if ( $this->displayClientHints ) {
				$referenceIds->addReferenceIds( $row->client_hints_reference_id, $row->client_hints_reference_type );
			}
			if ( $row->title !== '' ) {
				$lb->add( $row->namespace, $row->title );
			}
			if ( $this->xfor === null ) {
				$userText = str_replace( ' ', '_', $row->user_text );
				$lb->add( NS_USER, $userText );
				$lb->add( NS_USER_TALK, $userText );
			}
			// Add the row to the flag cache
			if ( !isset( $this->flagCache[$row->user_text] ) ) {
				$user = new UserIdentityValue( $row->user ?? 0, $row->user_text );
				$ip = IPUtils::isIPAddress( $row->user_text ) ? $row->user_text : '';
				$flags = $this->userBlockFlags( $ip, $user );
				$this->flagCache[$row->user_text] = $flags;
			}
			// Batch process comments
			if (
				( $row->type == RC_EDIT || $row->type == RC_NEW ) &&
				!array_key_exists( $row->this_oldid, $revisions )
			) {
				$revRecord = $this->revisionStore->getRevisionById( $row->this_oldid );
				if ( !$revRecord ) {
					// Assume revision is deleted
					$revRecord = $this->archivedRevisionLookup->getArchivedRevisionRecord( null, $row->this_oldid );
				}
				if ( !$revRecord ) {
					// This shouldn't happen, CheckUser points to a revision
					// that isn't in revision nor archive table?
					$this->logger->warning(
						"Couldn't fetch revision cu_changes table links to (this_oldid $row->this_oldid)"
					);
					// Show the comment in this case as the empty string to indicate that it's missing.
					$missingRevisions[$row->this_oldid] = '';
				} else {
					$revisions[$row->this_oldid] = $revRecord;

					$this->usernameVisibility[$row->this_oldid] = RevisionRecord::userCanBitfield(
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
		// Lookup the Client Hints data objects from the DB
		// and then batch format the ClientHintsData objects
		// for display.
		if ( $this->displayClientHints ) {
			// When no Client Hints data was found for a edit or for all edits in the results,
			// no associated formatted Client Hints data string will be stored in
			// $this->formattedClientHintsData for the edits without Client Hints data.
			// Calling the getter method will handle this by returning null.
			$clientHintsData = $this->clientHintsLookup->getClientHintsByReferenceIds( $referenceIds );
			$this->formattedClientHintsData = $this->clientHintsFormatter
				->batchFormatClientHintsData( $clientHintsData );
		}
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
