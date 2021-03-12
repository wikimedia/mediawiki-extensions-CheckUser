<?php

namespace MediaWiki\CheckUser\Specials;

use ActorMigration;
use CentralAuthUser;
use CentralIdLookup;
use DeferredUpdates;
use Exception;
use ExtensionRegistry;
use Hooks;
use Html;
use HtmlArmor;
use Linker;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\Hooks as CUHooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use OOUI\IconWidget;
use RequestContext;
use SpecialBlock;
use SpecialPage;
use stdClass;
use Title;
use User;
use UserBlockedError;
use UserGroupMembership;
use UserNamePrefixSearch;
use WikiMap;
use Wikimedia\AtEase\AtEase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use WikiPage;
use WikitextContent;
use Xml;

class SpecialCheckUser extends SpecialPage {
	/**
	 * @var string[] Used to cache frequently used messages
	 */
	protected $message = [];

	/**
	 * @var null|string
	 */
	private $lastdate = null;

	/**
	 * Reason for executing a CheckUser
	 * @var string
	 */
	protected $reason = '';

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var BlockPermissionCheckerFactory */
	private $blockPermissionCheckerFactory;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param BlockPermissionCheckerFactory $blockPermissionCheckerFactory
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		BlockPermissionCheckerFactory $blockPermissionCheckerFactory
	) {
		parent::__construct( 'CheckUser', 'checkuser' );

		$this->linkBatchFactory = $linkBatchFactory;
		$this->blockPermissionCheckerFactory = $blockPermissionCheckerFactory;
	}

	public function doesWrites() {
		// logging
		return true;
	}

	public function execute( $subpage ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CheckUser' );
		$this->checkPermissions();
		// Logging and blocking requires writing so stop from here if read-only mode
		$this->checkReadOnly();

		// Blocked users are not allowed to run checkuser queries (bug T157883)
		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError( $block );
		}

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $request->getText( 'user', $request->getText( 'ip', $subpage ) );
		$user = trim( $user );
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		// Normalise 'user' parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		$userTitle = Title::makeTitleSafe( NS_USER, $user );
		$user = $userTitle ? $userTitle->getText() : '';

		if ( $permissionManager->userHasRight( $this->getUser(), 'checkuser-log' ) ) {
			$subtitleLink = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$this->msg( 'checkuser-showlog' )->text()
			);
			if ( $user !== '' ) {
				$subtitleLink .= ' | ' . $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog', $user ),
					$this->msg( 'checkuser-recent-checks' )->text()
				);
			}
			$out->addSubtitle( $subtitleLink );
		}

		if ( $this->getConfig()->get( 'CheckUserEnableSpecialInvestigate' ) ) {
			$out->enableOOUI();
			$out->addModuleStyles( 'oojs-ui.styles.icons-interactions' );
			$icon = new IconWidget( [ 'icon' => 'lightbulb' ] );
			$investigateLink = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Investigate' ),
				$this->msg( 'checkuser-link-investigate-label' )->text()
			);
			$out->setIndicators( [ 'investigate-link' => $icon . $investigateLink ] );
		}

		$this->reason = $request->getText( 'reason' );
		$blockreason = $request->getText( 'blockreason', '' );
		$disableUserTalk = $request->getBool( 'blocktalk', false );
		$disableEmail = $request->getBool( 'blockemail', false );
		$checktype = $request->getVal( 'checktype' );
		$period = $request->getInt( 'period' );
		$users = $request->getArray( 'users' );
		$tag = $request->getBool( 'usetag' ) ?
			trim( $request->getVal( 'tag' ) ) : '';
		$talkTag = $request->getBool( 'usettag' ) ?
			trim( $request->getVal( 'talktag' ) ) : '';

		$blockParams = [
			'reason' => $blockreason,
			'talk' => $disableUserTalk,
			'email' => $disableEmail,
			'reblock' => $request->getBool( 'reblock' )
		];

		$ip = $name = $xff = '';
		$m = [];
		if ( IPUtils::isIPAddress( $user ) ) {
			// A single IP address or an IP range
			$ip = IPUtils::sanitizeIP( $user );
		} elseif ( preg_match( '/^(.+)\/xff$/', $user, $m ) && IPUtils::isIPAddress( $m[1] ) ) {
			// A single IP address or range with XFF string included
			$xff = IPUtils::sanitizeIP( $m[1] );
		} else {
			// A user?
			$name = $user;
		}

		$this->showIntroductoryText();
		$this->showForm( $user, $checktype, $ip, $xff, $name, $period );

		// Perform one of the various submit operations...
		if ( $request->wasPosted() ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'checkuser-token-fail' );
			} elseif ( $request->getVal( 'action' ) === 'block' ) {
				$this->doMassUserBlock( $users, $blockParams, $tag, $talkTag );
			} elseif ( !$this->checkReason() ) {
				$out->addWikiMsg( 'checkuser-noreason' );
			} elseif ( $checktype == 'subuserips' ) {
				$this->doUserIPsRequest( $name, $period );
			} elseif ( $xff && $checktype == 'subedits' ) {
				$this->doIPEditsRequest( $xff, true, $period );
			} elseif ( $ip && $checktype == 'subedits' ) {
				$this->doIPEditsRequest( $ip, false, $period );
			} elseif ( $name && $checktype == 'subedits' ) {
				$this->doUserEditsRequest( $user, $period );
			} elseif ( $xff && $checktype == 'subipusers' ) {
				$this->doIPUsersRequest( $xff, true, $period, $tag, $talkTag );
			} elseif ( $checktype == 'subipusers' ) {
				$this->doIPUsersRequest( $ip, false, $period, $tag, $talkTag );
			}
		}
		// Add CIDR calculation convenience JS form
		$this->addJsCIDRForm();
		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( 'mediawiki.interface.helpers.styles' );
	}

	protected function showIntroductoryText() {
		$cidrLimit = $this->getConfig()->get( 'CheckUserCIDRLimit' );
		$this->getOutput()->addWikiMsg(
			'checkuser-summary',
			$cidrLimit['IPv4'],
			$cidrLimit['IPv6']
		);
	}

	/**
	 * Show the CheckUser query form
	 *
	 * @param string $user
	 * @param string $checktype
	 * @param ?string $ip
	 * @param ?string $xff
	 * @param string $name
	 * @param int $period
	 */
	protected function showForm( $user, $checktype, $ip, $xff, $name, $period ) {
		$action = $this->getPageTitle()->getLocalURL();
		// Fill in requested type if it makes sense
		$encipusers = $encedits = $encuserips = false;
		if ( $checktype == 'subipusers' && ( $ip || $xff ) ) {
			$encipusers = true;
		} elseif ( $checktype == 'subuserips' && $name ) {
			$encuserips = true;
		} elseif ( $checktype == 'subedits' ) {
			$encedits = true;
		// Defaults otherwise
		} elseif ( $ip || $xff ) {
			$encedits = true;
		} else {
			$encuserips = true;
		}

		$form = Xml::openElement( 'form', [ 'action' => $action,
			'name' => 'checkuserform', 'id' => 'checkuserform', 'method' => 'post' ] );
		$form .= '<fieldset><legend>' . $this->msg( 'checkuser-query' )->escaped() . '</legend>';
		$form .= Xml::openElement( 'table', [ 'style' => 'border:0' ] );
		$form .= '<tr>';
		$form .= '<td>' . $this->msg( 'checkuser-target' )->escaped() . '</td>';
		// User field should fit things like "2001:0db8:85a3:08d3:1319:8a2e:0370:7344/100/xff"
		$form .= '<td>' . Xml::input( 'user', 46, $user, [ 'id' => 'checktarget' ] );
		$form .= '&#160;' . $this->getPeriodMenu( $period ) . '</td>';
		$form .= '</tr><tr>';
		$form .= '<td></td>';
		$form .= Xml::openElement( 'td', [ 'class' => 'checkuserradios' ] );
		$form .= Xml::openElement( 'table', [ 'style' => 'border:0' ] );
		$form .= '<tr>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subuserips', $encuserips, [ 'id' => 'subuserips' ] );
		$form .= ' ' . Xml::label( $this->msg( 'checkuser-ips' )->text(), 'subuserips' ) . '</td>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subedits', $encedits, [ 'id' => 'subedits' ] );
		$form .= ' ' . Xml::label( $this->msg( 'checkuser-edits' )->text(), 'subedits' ) . '</td>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subipusers', $encipusers, [ 'id' => 'subipusers' ] );
		$form .= ' ' .
			Xml::label( $this->msg( 'checkuser-users' )->text(), 'subipusers' ) . '</td>';
		$form .= '</tr>';
		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'td' );
		$form .= '</tr><tr>';
		$form .= '<td>' . $this->msg( 'checkuser-reason' )->escaped() . '</td>';
		$form .= '<td>' . Xml::input( 'reason', 46, $this->reason,
			[ 'maxlength' => '150', 'id' => 'checkreason' ] );
		$form .= '&#160; &#160;' . Xml::submitButton( $this->msg( 'checkuser-check' )->text(),
			[ 'id' => 'checkusersubmit', 'name' => 'checkusersubmit' ] ) . '</td>';
		$form .= '</tr>';
		$form .= Xml::closeElement( 'table' );
		$form .= '</fieldset>';
		$form .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$form .= Xml::closeElement( 'form' );

		$this->getOutput()->addHTML( $form );
	}

	/**
	 * Get a selector of time period options
	 * @param int $selected Currently selected option
	 * @return string
	 */
	protected function getPeriodMenu( $selected ) {
		$s = '<label for="period">' .
			$this->msg( 'checkuser-period' )->escaped() . '</label>&#160;';
		$s .= Xml::openElement(
			'select',
			[ 'name' => 'period', 'id' => 'period', 'style' => 'margin-top:.2em;' ]
		);
		$s .= Xml::option( $this->msg( 'checkuser-week-1' )->text(), '7', $selected === 7 );
		$s .= Xml::option( $this->msg( 'checkuser-week-2' )->text(), '14', $selected === 14 );
		$s .= Xml::option( $this->msg( 'checkuser-month' )->text(), '31', $selected === 31 );
		$s .= Xml::option( $this->msg( 'checkuser-all' )->text(), '0', $selected === 0 );
		$s .= Xml::closeElement( 'select' ) . "\n";
		return $s;
	}

	/**
	 * Make a quick JS form for admins to calculate block ranges
	 */
	protected function addJsCIDRForm() {
		$s = '<fieldset id="mw-checkuser-cidrform" style="display:none; clear:both;">' .
			'<legend>' . $this->msg( 'checkuser-cidr-label' )->escaped() . '</legend>';
		$s .= '<textarea id="mw-checkuser-iplist" dir="ltr" rows="5" cols="50"></textarea><br />';
		$s .= $this->msg( 'checkuser-cidr-res' )->escaped() . '&#160;' .
			Xml::input( 'mw-checkuser-cidr-res', 35, '', [ 'id' => 'mw-checkuser-cidr-res' ] ) .
			'&#160;<strong id="mw-checkuser-ipnote"></strong>';
		$s .= '</fieldset>';
		$this->getOutput()->addHTML( $s );
	}

	/**
	 * @return bool
	 */
	protected function checkReason() {
		return ( !$this->getConfig()->get( 'CheckUserForceSummary' ) || strlen( $this->reason ) );
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

	/**
	 * Block a list of selected users
	 * @param array $users
	 * @param array $blockParams
	 * @param string $tag
	 * @param string $talkTag
	 */
	protected function doMassUserBlock( $users, $blockParams, $tag = '', $talkTag = '' ) {
		$usersCount = count( $users );
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		if ( !$permissionManager->userHasRight( $this->getUser(), 'block' )
			|| $this->getUser()->getBlock()
			|| !$usersCount
		) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-failure' );
			return;
		} elseif ( $usersCount > $this->getConfig()->get( 'CheckUserMaxBlocks' ) ) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-limit' );
			return;
		} elseif ( !$blockParams['reason'] ) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-noreason' );
			return;
		}

		$blockedUsers = $this->doMassUserBlockInternal(
			$users,
			$blockParams,
			$tag,
			$talkTag
		);
		$blockedCount = count( $blockedUsers );
		if ( $blockedCount > 0 ) {
			$lang = $this->getLanguage();
			$this->getOutput()->addWikiMsg( 'checkuser-block-success',
				$lang->listToText( $blockedUsers ),
				$lang->formatNum( $blockedCount )
			);
		} else {
			$this->getOutput()->addWikiMsg( 'checkuser-block-failure' );
		}
	}

	/**
	 * Block a list of selected users
	 *
	 * @param string[] $users
	 * @param array $blockParams
	 * @param string $tag replaces user pages
	 * @param string $talkTag replaces user talk pages
	 * @return string[] List of html-safe usernames which were actually were blocked
	 */
	protected function doMassUserBlockInternal(
		$users,
		array $blockParams,
		$tag = '',
		$talkTag = ''
	) {
		$safeUsers = [];
		foreach ( $users as $name ) {
			$u = User::newFromName( $name, false );
			// Do some checks to make sure we can block this user first
			if ( $u === null ) {
				// Invalid user
				continue;
			}
			$isIP = IPUtils::isIPAddress( $u->getName() );
			if ( !$u->getId() && !$isIP ) {
				// Not a registered user or an IP
				continue;
			}

			if ( $u->getBlock() && !$blockParams['reblock'] ) {
				continue;
			}

			if (
				!isset( $blockParams['email' ] ) ||
				$blockParams['email'] === false ||
				$this->blockPermissionCheckerFactory
					->newBlockPermissionChecker(
						$u,
						$this->getUser()
					)
					->checkEmailPermissions()
			) {
				$res = SpecialBlock::processForm( [
					'Target' => $u->getName(),
					'Reason' => [ $blockParams['reason'] ],
					'Expiry' => $isIP ? '1 week' : 'indefinite',
					'HardBlock' => !$isIP,
					'CreateAccount' => true,
					'AutoBlock' => true,
					'DisableEmail' => $blockParams['email'] ?? false,
					'DisableUTEdit' => $blockParams['talk'],
					'Reblock' => $blockParams['reblock'],
					'Confirm' => true,
					'Watch' => false,
				], $this->getContext() );

				if ( $res === true ) {
					$userPage = $u->getUserPage();

					$safeUsers[] = "[[{$userPage->getPrefixedText()}|{$userPage->getText()}]]";

					// Tag user page and user talk page
					$this->tagPage( $userPage, $tag, $blockParams['reason'] );
					$this->tagPage( $u->getTalkPage(), $talkTag, $blockParams['reason'] );
				}

			}
		}

		return $safeUsers;
	}

	/**
	 * Make an edit to the given page with the tag provided
	 *
	 * @param Title $title
	 * @param string $tag
	 * @param string $summary
	 */
	protected function tagPage( Title $title, $tag, $summary ) {
		// Check length to avoid mistakes
		if ( strlen( $tag ) > 2 ) {
			$page = WikiPage::factory( $title );
			$flags = 0;
			if ( $page->exists() ) {
				$flags |= EDIT_MINOR;
			}
			$page->doEditContent( new WikitextContent( $tag ), $summary,
				$flags, false, $this->getUser() );
		}
	}

	/**
	 * Give a "no matches found for X" message.
	 * If $checkLast, then mention the last edit by this user or IP.
	 *
	 * @param string $userName
	 * @param bool $checkLast
	 * @return string
	 */
	protected function noMatchesMessage( $userName, $checkLast = true ) {
		if ( $checkLast ) {
			$dbr = wfGetDB( DB_REPLICA );
			$actorMigration = ActorMigration::newMigration();
			$user = User::newFromName( $userName, false );

			$lastEdit = false;

			$revWhere = $actorMigration->getWhere( $dbr, 'rev_user', $user );
			foreach ( $revWhere['orconds'] as $cond ) {
				$lastEdit = max( $lastEdit, $dbr->selectField(
					[ 'revision' ] + $revWhere['tables'],
					'rev_timestamp',
					$cond,
					__METHOD__,
					[ 'ORDER BY' => 'rev_timestamp DESC' ],
					$revWhere['joins']
				) );
			}
			$logWhere = $actorMigration->getWhere( $dbr, 'log_user', $user );
			foreach ( $logWhere['orconds'] as $cond ) {
				$lastEdit = max( $lastEdit, $dbr->selectField(
					[ 'logging' ] + $logWhere['tables'],
					'log_timestamp',
					$cond,
					__METHOD__,
					[ 'ORDER BY' => 'log_timestamp DESC' ],
					$logWhere['joins']
				) );
			}

			if ( $lastEdit ) {
				$lastEditTime = wfTimestamp( TS_MW, $lastEdit );
				$lang = $this->getLanguage();
				$contextUser = $this->getUser();
				// FIXME: don't pass around parsed messages
				return $this->msg( 'checkuser-nomatch-edits',
					$lang->userDate( $lastEditTime, $contextUser ),
					$lang->userTime( $lastEditTime, $contextUser )
				)->parseAsBlock();
			}
		}
		return $this->msg( 'checkuser-nomatch' )->parseAsBlock();
	}

	/**
	 * Show all the IPs used by a user
	 *
	 * @param string $user
	 * @param int $period
	 * @return void
	 */
	protected function doUserIPsRequest( $user, $period = 0 ) {
		$out = $this->getOutput();

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( $userTitle !== null ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		// IPs are passed in as a blank string
		if ( !$user ) {
			$out->addWikiMsg( 'nouserspecified' );
			return;
		}
		// Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		// If user is not IP or nonexistent
		if ( !$user_id ) {
			$out->addWikiMsg( 'nosuchusershort', $user );
			return;
		}

		// Record check...
		self::addLogEntry( 'userips', 'user', $user, $this->reason, $user_id );

		$result = $this->doUserIPsDBRequest( $user_id, $period );
		$this->doUserIPsRequestOutput( $result, $user, $period );
	}

	/**
	 * Issue a DB query for doUserIPsRequestOutput
	 *
	 * @param int $user_id
	 * @param int $period
	 * @param int|null $limit
	 * @return IResultWrapper
	 */
	protected function doUserIPsDBRequest( $user_id, $period = 0, $limit = null ) : IResultWrapper {
		if ( $limit === null ) {
			// We add 1 to the row count here because the number of rows returned is used to determine
			// whether the data has been truncated.
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' ) + 1;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$conds = [ 'cuc_user' => $user_id ];
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		// Ordering by the latest timestamp makes a small filesort on the IP list
		return $dbr->select(
			'cu_changes',
			[
				'cuc_ip',
				'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => 'last DESC',
				'GROUP BY' => [ 'cuc_ip', 'cuc_ip_hex' ],
				'LIMIT' => $limit,
				'USE INDEX' => 'cuc_user_ip_time',
			]
		);
	}

	/**
	 * Return "checkuser-ipeditcount" number
	 *
	 * @param array $ips_hex
	 * @param string $ip
	 * @param int $period
	 * @return int
	 */
	protected function getCountForIPedits( array $ips_hex, $ip, $period = 0 ) {
		$dbr = wfGetDB( DB_REPLICA );
		$conds = [ 'cuc_ip_hex' => $ips_hex[$ip] ];
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		$ipedits = $dbr->estimateRowCount(
			'cu_changes',
			'*',
			$conds,
			__METHOD__
		);
		// If small enough, get a more accurate count
		if ( $ipedits <= 1000 ) {
			$ipedits = $dbr->selectField(
				'cu_changes',
				'COUNT(*)',
				$conds,
				__METHOD__
			);
		}

		return $ipedits;
	}

	/**
	 * @param IResultWrapper $result
	 * @param int|null $limit
	 * @return array
	 */
	protected function getIPSets( IResultWrapper $result, $limit = null ) : array {
		if ( $limit === null ) {
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' );
		}

		$ipSets = [
			'edits' => [],
			'first' => [],
			'last' => [],
			'hex' => [],
			'exceed' => false
		];
		$counter = 0;

		foreach ( $result as $row ) {
			if ( $counter >= $limit ) {
				$ipSets['exceed'] = true;
				break;
			}
			$ipSets['edits'][$row->cuc_ip] = $row->count;
			$ipSets['first'][$row->cuc_ip] = $row->first;
			$ipSets['last'][$row->cuc_ip] = $row->last;
			$ipSets['hex'][$row->cuc_ip] = $row->cuc_ip_hex;
			++$counter;
		}
		// Count pinging might take some time...make sure it is there
		AtEase::suppressWarnings();
		set_time_limit( 60 );
		AtEase::restoreWarnings();

		return $ipSets;
	}

	/**
	 * Result output for doUserIPsRequest
	 *
	 * @param IResultWrapper $result
	 * @param string $user
	 * @param int $period
	 * @return void
	 */
	protected function doUserIPsRequestOutput( IResultWrapper $result, $user, $period ) {
		$out = $this->getOutput();
		$lang = $this->getLanguage();

		if ( !$result->numRows() ) {
			$out->addHTML( $this->noMatchesMessage( $user ) . "\n" );
			return;
		}

		$ipSets = $this->getIPSets( $result );
		$ips_edits = $ipSets['edits'];
		$ips_first = $ipSets['first'];
		$ips_last = $ipSets['last'];
		$ips_hex = $ipSets['hex'];

		if ( $ipSets['exceed'] ) {
			$out->addWikiMsg( 'checkuser-limited' );
		}

		$s = '<div id="checkuserresults"><ul>';
		foreach ( $ips_edits as $ip => $edits ) {
			$s .= '<li>';
			$s .= $this->getSelfLink( $ip,
				[
					'user' => $ip,
					'reason' => $this->reason,
				]
			);
			$s .= ' ' . $this->msg( 'parentheses' )->rawParams(
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'Block', $ip ),
						$this->msg( 'blocklink' )->text()
					)
				)->escaped();
			$s .= ' ' . $this->getTimeRangeString( $ips_first[$ip], $ips_last[$ip] ) . ' ';
			$s .= ' <strong>[' . htmlspecialchars( $lang->formatNum( $edits ) ) . ']</strong>';

			// If we get some results, it helps to know if the IP in general
			// has a lot more edits, e.g. "tip of the iceberg"...
			$ipedits = $this->getCountForIPedits( $ips_hex, $ip, $period );
			if ( $ipedits > $ips_edits[$ip] ) {
				$s .= ' <i>(' .
					$this->msg( 'checkuser-ipeditcount' )->numParams( $ipedits )->escaped() .
					')</i>';
			}

			// If this IP is blocked, give a link to the block log
			$s .= $this->getIPBlockInfo( $ip );
			$s .= '<div style="margin-left:5%">';
			$s .= '<small>' . $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse() .
				'</small>';
			$s .= '</div>';
			$s .= "</li>\n";
		}
		$s .= '</ul></div>';
		$out->addHTML( $s );
	}

	protected function getIPBlockInfo( $ip ) {
		$block = DatabaseBlock::newFromTarget( null, $ip, false );
		if ( $block instanceof DatabaseBlock ) {
			return $this->getBlockFlag( $block );
		}
		return '';
	}

	/**
	 * Get a link to block information about the passed block for displaying to the user.
	 *
	 * @param DatabaseBlock $block
	 * @return string
	 */
	protected function getBlockFlag( DatabaseBlock $block ) {
		if ( $block->getType() == DatabaseBlock::TYPE_AUTO ) {
			$ret = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'BlockList' ),
				$this->msg( 'checkuser-blocked' )->text(),
				[],
				[ 'wpTarget' => "#{$block->getId()}" ]
			);
		} else {
			$userPage = Title::makeTitle( NS_USER, $block->getTarget() );
			$ret = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-blocked' )->text(),
				[],
				[
					'type' => 'block',
					'page' => $userPage->getPrefixedText()
				]
			);
		}

		// Add the blocked range if the block is on a range
		if ( $block->getType() == DatabaseBlock::TYPE_RANGE ) {
			$ret .= ' - ' . htmlspecialchars( $block->getTarget() );
		}

		return '<strong>' .
			$this->msg( 'parentheses' )->rawParams( $ret )->escaped()
			. '</strong>';
	}

	/**
	 * Shows all changes made by an IP address or range
	 *
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param int $period
	 * @return void
	 */
	protected function doIPEditsRequest( $ip, $xfor = false, $period = 0 ) {
		$out = $this->getOutput();
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';

		// Invalid IPs are passed in as a blank string
		if ( !$ip || !self::isValidRange( $ip ) ) {
			$out->addWikiMsg( 'badipaddress' );
			return;
		}

		$logType = $xfor ? 'ipedits-xff' : 'ipedits';

		// Record check in the logs
		self::addLogEntry( $logType, 'ip', $ip, $this->reason );

		// Ordered in descent by timestamp. Can cause large filesorts on range scans.
		// Check how many rows will need sorting ahead of time to see if this is too big.
		// Also, if we only show 5000, too many will be ignored as well.
		$rangecount = $this->getIPEditsCount( $ip, $xfor, $index, $period );
		if ( $rangecount > $this->getConfig()->get( 'CheckUserMaximumRowCount' ) ) {
			// See what is best to do after testing the waters...
			$result = $this->IPEditsTooManyDB( $ip, $xfor, $index, $period );
			$this->IPEditsTooMany( $result, $ip, $xfor );
			return;
		} elseif ( $rangecount === 0 ) {
			$out->addHTML( $this->noMatchesMessage( $ip, !$xfor ) . "\n" );
			return;
		}

		// OK, do the real query...
		$result = $this->doIPEditsDBRequest( $ip, $xfor, $index, $period );
		$this->doIPEditsRequestOutput( $result, $ip, $xfor );
	}

	/**
	 * Get count for target IP range edits
	 *
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param string $index
	 * @param int $period
	 * @param int $timeLimit
	 * @return int
	 */
	protected function getIPEditsCount( $ip, $xfor, $index, $period = 0, $timeLimit = 60 ) {
		// Is not a IP range
		if ( strpos( $ip, '/' ) === false ) {
			return -1;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( $conds === false ) {
			return -1;
		}
		// Quick index check only OK if no time constraint
		if ( $period ) {
			$time_conds = $this->getTimeConds( $period );
			if ( $time_conds !== false ) {
				$conds[] = $time_conds;
			}
			$rangecount = $dbr->selectField(
				'cu_changes',
				'COUNT(*)',
				$conds,
				__METHOD__,
				[ 'USE INDEX' => $index ]
			);
		} else {
			$rangecount = $dbr->estimateRowCount(
				'cu_changes',
				'*',
				$conds,
				__METHOD__,
				[ 'USE INDEX' => $index ]
			);
		}
		// Sorting might take some time...make sure it is there
		AtEase::suppressWarnings();
		set_time_limit( $timeLimit );
		AtEase::restoreWarnings();

		return $rangecount;
	}

	/**
	 * Issue a DB query for IPEditsTooMany
	 *
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param string $index
	 * @param int $period
	 * @param int|null $limit
	 * @return IResultWrapper
	 */
	protected function IPEditsTooManyDB(
		$ip, $xfor, $index, $period = 0, $limit = null
	) : IResultWrapper {
		if ( $limit === null ) {
			// We add 1 to the row count here because the number of rows returned is used to determine
			// whether the data has been truncated.
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' ) + 1;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( $conds === false ) {
			return new FakeResultWrapper( [] );
		}
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		return $dbr->select(
			'cu_changes',
			[
				'cuc_ip_hex',
				'count' => 'COUNT(*)',
				'first' => 'MIN(cuc_timestamp)',
				'last' => 'MAX(cuc_timestamp)',
			],
			$conds,
			__METHOD__,
			[
				'GROUP BY' => 'cuc_ip_hex',
				'ORDER BY' => 'cuc_ip_hex',
				'LIMIT' => $limit,
				'USE INDEX' => $index,
			]
		);
	}

	/**
	 * Return "checkuser-too-many" error with some hints
	 *
	 * @param IResultWrapper $result
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param int|null $limit
	 */
	protected function IPEditsTooMany( IResultWrapper $result, $ip, $xfor, $limit = null ) {
		if ( $limit === null ) {
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' );
		}

		$out = $this->getOutput();
		$lang = $this->getLanguage();

		// List out each IP that has edits
		$s = $this->msg( 'checkuser-too-many', $lang->formatNum( $limit ) )->parseAsBlock();
		$s .= '<ol>';

		$counter = 0;
		foreach ( $result as $row ) {
			if ( $counter >= $limit ) {
				$out->addWikiMsg( 'checkuser-limited' );
				break;
			}
			// Convert the IP hexes into normal form
			if ( strpos( $row->cuc_ip_hex, 'v6-' ) !== false ) {
				$ip = substr( $row->cuc_ip_hex, 3 );
				$ip = IPUtils::hexToOctet( $ip );
			} else {
				$ip = long2ip( (int)\Wikimedia\base_convert( $row->cuc_ip_hex, 16, 10, 8 ) );
			}
			$s .= '<li>';
			$s .= $this->getSelfLink( $ip,
				[
					'user' => $ip,
					'reason' => $this->reason,
					'checktype' => 'subipusers'
				]
			);
			$s .= ' ' . $this->getTimeRangeString( $row->first, $row->last ) . ' ';
			$s .= ' [<strong>' . htmlspecialchars( $lang->formatNum( $row->count ) ) .
				"</strong>]</li>\n";
			++$counter;
		}
		$s .= '</ol>';

		$out->addHTML( $s );
	}

	/**
	 * Issue a DB query for doIPEditsRequestOutput
	 *
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param string $index
	 * @param int $period
	 * @param int|null $limit
	 * @return IResultWrapper
	 */
	protected function doIPEditsDBRequest(
		$ip, $xfor, $index, $period = 0, $limit = null
	) : IResultWrapper {
		if ( $limit === null ) {
			// We add 1 to the row count here because the number of rows returned is used to determine
			// whether the data has been truncated.
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' ) + 1;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( $conds === false ) {
			return new FakeResultWrapper( [] );
		}
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		return $dbr->select(
			'cu_changes',
			[
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent',
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => $limit,
				'USE INDEX' => $index,
			]
		);
	}

	/**
	 * Result output for doIPEditsRequest
	 *
	 * @param IResultWrapper $result
	 * @param string $ip
	 * @param bool $xfor
	 * @return void
	 */
	protected function doIPEditsRequestOutput( IResultWrapper $result, $ip, $xfor ) {
		$out = $this->getOutput();

		if ( !$result->numRows() ) {
			$out->addHTML( $this->noMatchesMessage( $ip, !$xfor ) . "\n" );
			return;
		}

		// Cache common messages
		$this->preCacheMessages();
		// Try to optimize this query
		$lb = $this->linkBatchFactory->newLinkBatch();
		foreach ( $result as $row ) {
			$userText = str_replace( ' ', '_', $row->cuc_user_text );
			if ( $row->cuc_title !== '' ) {
				$lb->add( $row->cuc_namespace, $row->cuc_title );
			}
			$lb->add( NS_USER, $userText );
			$lb->add( NS_USER_TALK, $userText );
		}
		$lb->execute();
		$result->seek( 0 );
		// List out the edits
		$s = '<div id="checkuserresults">';
		$counter = 0;
		foreach ( $result as $row ) {
			if ( $counter >= $this->getConfig()->get( 'CheckUserMaximumRowCount' ) ) {
				$out->addWikiMsg( 'checkuser-limited' );
				break;
			}
			$s .= $this->CUChangesLine( $row );
			++$counter;
		}
		$s .= '</ul></div>';

		$out->addHTML( $s );
	}

	/**
	 * @param IResultWrapper $rows Results with cuc_namespace and cuc_title field
	 */
	protected function doLinkCache( IResultWrapper $rows ) {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $rows as $row ) {
			if ( $row->cuc_title !== '' ) {
				$lb->add( $row->cuc_namespace, $row->cuc_title );
			}
		}
		$lb->execute();
		$rows->seek( 0 );
	}

	/**
	 * Shows all changes made by a particular user
	 *
	 * @param string $user
	 * @param int $period
	 * @return void
	 */
	protected function doUserEditsRequest( $user, $period = 0 ) {
		$out = $this->getOutput();

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( $userTitle !== null ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		// IPs are passed in as a blank string
		if ( !$user ) {
			$out->addWikiMsg( 'nouserspecified' );
			return;
		}
		// Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		// If user is not IP or nonexistent
		if ( !$user_id ) {
			$out->addHTML( $this->msg( 'nosuchusershort', $user )->parseAsBlock() );
			return;
		}

		// Record check...
		self::addLogEntry( 'useredits', 'user', $user, $this->reason, $user_id );

		// Cache common messages
		$this->preCacheMessages();
		$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' );

		// Sorting might take some time...make sure it is there
		AtEase::suppressWarnings();
		set_time_limit( 60 );
		AtEase::restoreWarnings();

		// OK, do the real query...
		$result = $this->doUserEditsDBRequest( $user_id, $period, $limit );
		$this->doUserEditsRequestOutput( $result, $user, $limit );
	}

	/**
	 * get count for the user edits
	 *
	 * @param int $user_id
	 * @param int $period
	 * @return int
	 */
	protected function getCountsForUserEdits( $user_id, $period = 0 ) {
		$dbr = wfGetDB( DB_REPLICA );
		$conds = [ 'cuc_user' => $user_id ];
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		if ( $period ) {
			return $dbr->selectField(
				'cu_changes',
				'COUNT(*)',
				$conds,
				__METHOD__,
				[ 'USE INDEX' => 'cuc_user_ip_time' ]
			);
		} else {
			return $dbr->estimateRowCount(
				'cu_changes',
				'*',
				$conds,
				__METHOD__,
				[ 'USE INDEX' => 'cuc_user_ip_time' ]
			);
		}
	}

	/**
	 * Issue a DB query for doUserEditsRequestOutput
	 *
	 * @param int $user_id
	 * @param int $period
	 * @param int|null $limit
	 * @return IResultWrapper
	 */
	protected function doUserEditsDBRequest( $user_id, $period = 0, $limit = null ) : IResultWrapper {
		if ( $limit === null ) {
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' );
		}

		$dbr = wfGetDB( DB_REPLICA );
		$conds = [ 'cuc_user' => $user_id ];
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		return $dbr->select(
			'cu_changes', [
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment',
				'cuc_actiontext', 'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type',
				'cuc_this_oldid', 'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent',
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => $limit,
				'USE INDEX' => 'cuc_user_ip_time'
			]
		);
	}

	/**
	 * Result output for doUserEditsRequest
	 *
	 * @param IResultWrapper $result
	 * @param string $user
	 * @param int|null $limit
	 * @return void
	 */
	protected function doUserEditsRequestOutput( IResultWrapper $result, $user, $limit = null ) {
		if ( $limit === null ) {
			$limit = $this->getConfig()->get( 'CheckUserMaximumRowCount' );
		}

		$out = $this->getOutput();

		if ( !$result->numRows() ) {
			$out->addHTML( $this->noMatchesMessage( $user ) . "\n" );
			return;
		}

		if ( $result->numRows() >= $limit ) {
			// If the actual row count is at or over the limit, provide a warning
			// that the results may have been truncated
			$out->addHTML( $this->msg( 'checkuser-limited' )->parse() );
		}

		$this->doLinkCache( $result );
		// List out the edits
		$html = '<div id="checkuserresults">';
		foreach ( $result as $row ) {
			$html .= $this->CUChangesLine( $row );
		}
		$html .= '</ul></div>';

		$out->addHTML( $html );
	}

	/**
	 * Lists all users in recent changes who used an IP, newest to oldest down
	 * Outputs usernames, latest and earliest found edit date, and count
	 * List unique IPs used for each user in time order, list corresponding user agent
	 *
	 * @param ?string $ip
	 * @param bool $xfor
	 * @param int $period
	 * @param string $tag
	 * @param string $talkTag
	 * @return void
	 */
	protected function doIPUsersRequest(
		$ip, $xfor = false, $period = 0, $tag = '', $talkTag = ''
	) {
		$out = $this->getOutput();
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';

		// Invalid IPs are passed in as a blank string
		if ( !$ip || !self::isValidRange( $ip ) ) {
			$out->addWikiMsg( 'badipaddress' );
			return;
		}

		$logType = $xfor ? 'ipusers-xff' : 'ipusers';

		// Log the check...
		self::addLogEntry( $logType, 'ip', $ip, $this->reason );

		// Are there too many edits?
		$rangecount = $this->getIPUsersCount( $ip, $xfor, $index, $period );
		if ( $rangecount > 10000 ) {
			$result = $this->IPUsersTooManyDB( $ip, $xfor, $index, $period );
			$this->IPUsersTooMany( $result, $ip, $xfor );
			return;
		} elseif ( $rangecount === 0 ) {
			$out->addHTML( $this->noMatchesMessage( $ip, !$xfor ) . "\n" );
			return;
		}

		// OK, do the real query...
		$result = $this->doIPUsersDBRequest( $ip, $xfor, $index, $period );
		$this->doIPUsersRequestOutput( $result, $ip, $xfor, $tag, $talkTag );
	}

	/**
	 * Get count for target edits
	 *
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $index
	 * @param int $period
	 * @param int $timeLimit
	 * @return int
	 */
	protected function getIPUsersCount( $ip, $xfor, $index, $period = 0, $timeLimit = 120 ) {
		return $this->getIPEditsCount( $ip, $xfor, $index, $period, $timeLimit );
	}

	/**
	 * Issue a DB query for IPUsersTooMany
	 *
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $index
	 * @param int $period
	 * @return IResultWrapper
	 */
	protected function IPUsersTooManyDB( $ip, $xfor, $index, $period = 0 ) : IResultWrapper {
		return $this->IPEditsTooManyDB( $ip, $xfor, $index, $period );
	}

	/**
	 * Return 'checkuser-too-many' error with some hints
	 *
	 * @param IResultWrapper $result
	 * @param string $ip
	 * @param bool $xfor
	 * @return void
	 */
	protected function IPUsersTooMany( IResultWrapper $result, $ip, $xfor ) {
		$this->IPEditsTooMany( $result, $ip, $xfor );
	}

	/**
	 * Issue a DB query for doIPUsersRequestOutput
	 *
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $index
	 * @param int $period
	 * @param int $limit
	 * @return IResultWrapper
	 */
	protected function doIPUsersDBRequest(
		$ip, $xfor, $index, $period = 0, $limit = 10000
	) : IResultWrapper {
		$dbr = wfGetDB( DB_REPLICA );
		$conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( $conds === false ) {
			return new FakeResultWrapper( [] );
		}
		$time_conds = $this->getTimeConds( $period );
		if ( $time_conds !== false ) {
			$conds[] = $time_conds;
		}

		return $dbr->select(
			'cu_changes',
			[
				'cuc_user_text', 'cuc_timestamp', 'cuc_user', 'cuc_ip', 'cuc_agent', 'cuc_xff',
			],
			$conds,
			__METHOD__,
			[
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => $limit,
				'USE INDEX' => $index,
			]
		);
	}

	/**
	 * @param IResultWrapper $result
	 * @return array[]
	 */
	protected function getUserSets( IResultWrapper $result ) : array {
		$userSets = [
			'first' => [],
			'last' => [],
			'edits' => [],
			'ids' => [],
			'infosets' => [],
			'agentsets' => []
		];

		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->cuc_user_text, $userSets['edits'] ) ) {
				$userSets['last'][$row->cuc_user_text] = $row->cuc_timestamp;
				$userSets['edits'][$row->cuc_user_text] = 0;
				$userSets['ids'][$row->cuc_user_text] = $row->cuc_user;
				$userSets['infosets'][$row->cuc_user_text] = [];
				$userSets['agentsets'][$row->cuc_user_text] = [];
			}
			$userSets['edits'][$row->cuc_user_text] += 1;
			$userSets['first'][$row->cuc_user_text] = $row->cuc_timestamp;
			// Treat blank or NULL xffs as empty strings
			$xff = empty( $row->cuc_xff ) ? null : $row->cuc_xff;
			$xff_ip_combo = [ $row->cuc_ip, $xff ];
			// Add this IP/XFF combo for this username if it's not already there
			if ( !in_array( $xff_ip_combo, $userSets['infosets'][$row->cuc_user_text] ) ) {
				$userSets['infosets'][$row->cuc_user_text][] = $xff_ip_combo;
			}
			// Add this agent string if it's not already there; 10 max.
			if ( count( $userSets['agentsets'][$row->cuc_user_text] ) < 10 ) {
				if ( !in_array( $row->cuc_agent, $userSets['agentsets'][$row->cuc_user_text] ) ) {
					$userSets['agentsets'][$row->cuc_user_text][] = $row->cuc_agent;
				}
			}
		}

		return $userSets;
	}

	/**
	 * Result output for doIPUsersRequest
	 *
	 * @param IResultWrapper $result
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $tag
	 * @param string $talkTag
	 * @return void
	 */
	protected function doIPUsersRequestOutput(
		IResultWrapper $result, $ip, $xfor, $tag = '', $talkTag = ''
	) {
		$out = $this->getOutput();

		if ( !$result->numRows() ) {
			$out->addHTML( $this->noMatchesMessage( $ip, !$xfor ) . "\n" );
			return;
		}

		$userSets = $this->getUserSets( $result );
		$users_first = $userSets['first'];
		$users_last = $userSets['last'];
		$users_edits = $userSets['edits'];
		$users_ids = $userSets['ids'];
		$users_agentsets = $userSets['agentsets'];
		$users_infosets = $userSets['infosets'];

		$centralAuthToollink = ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' )
			? $this->getConfig()->get( 'CheckUserCAtoollink' ) : false;
		$globalBlockingToollink = ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' )
			? $this->getConfig()->get( 'CheckUserGBtoollink' ) : false;
		$linkrenderer = $this->getLinkRenderer();
		$splang = $this->getLanguage();
		$aliases = $splang->getSpecialPageAliases();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		// @todo FIXME: This form (and checkboxes) shouldn't be initiated for users without 'block' right
		$action = htmlspecialchars( $this->getPageTitle()->getLocalURL( 'action=block' ) );
		$s = "<form name='checkuserblock' id='checkuserblock' action=\"$action\" method='post'>";
		$s .= '<div id="checkuserresults"><ul>';
		foreach ( $users_edits as $name => $count ) {
			$s .= '<li>';
			$s .= Xml::check( 'users[]', false, [ 'value' => $name ] ) . '&#160;';
			// Load user object
			$usernfn = User::newFromName( $name, false );
			// Add user page and tool links
			if ( !IPUtils::isIPAddress( $usernfn ) ) {
				$idforlinknfn = -1;
				$user = User::newFromId( $users_ids[$name] );
			} else {
				$idforlinknfn = $users_ids[$name];
				$user = $usernfn;
			}
			$classnouser = false;
			if ( IPUtils::isIPAddress( $name ) !== IPUtils::isIPAddress( $user ) ) {
				// User does not exist
				$idforlink = -1;
				$classnouser = true;
			} else {
				$idforlink = $users_ids[$name];
			}
			if ( $classnouser === true ) {
				$s .= '<span class=\'mw-checkuser-nonexistent-user\'>';
			} else {
				$s .= '<span>';
			}
			$s .= Linker::userLink( $idforlinknfn, $name, $name ) . '</span> ';
			$ip = IPUtils::isIPAddress( $name ) ? $name : '';
			$s .= Linker::userToolLinksRedContribs(
				$idforlink,
				$name,
				$user->getEditCount(),
				// don't render parentheses in HTML markup (CSS will provide)
				false
			) . ' ';
			if ( $ip ) {
				$s .= $this->msg( 'checkuser-userlinks-ip', $name )->parse();
			} elseif ( !$classnouser ) {
				if ( $this->msg( 'checkuser-userlinks' )->exists() ) {
					$s .= ' ' . $this->msg( 'checkuser-userlinks', $name )->parse();
				}
			}
			// Add CheckUser link
			$s .= ' ' . $this->msg( 'parentheses' )->rawParams(
				$this->getSelfLink(
					$this->msg( 'checkuser-check' )->text(),
					[
						'user' => $name,
						'reason' => $this->reason
					]
				)
			)->escaped();
			// Add global user tools links
			// Add CentralAuth link for real registered users
			if ( $centralAuthToollink !== false
				&& !IPUtils::isIPAddress( $name )
				&& !$classnouser
			) {
				// Get CentralAuth SpecialPage name in UserLang from the first Alias name
				$spca = $aliases['CentralAuth'][0];
				$calinkAlias = str_replace( '_', ' ', $spca );
				$centralCAUrl = WikiMap::getForeignURL(
					$centralAuthToollink,
					'Special:CentralAuth'
				);
				if ( $centralCAUrl === false ) {
					throw new Exception(
						"Could not retrieve URL for CentralAuth: {$centralAuthToollink}"
					);
				}
				$linkCA = Html::element( 'a',
					[
						'href' => $centralCAUrl . "/" . $name,
						'title' => $this->msg( 'centralauth' )->text(),
					],
					$calinkAlias
				);
				$s .= ' ' . $this->msg( 'parentheses' )->rawParams( $linkCA )->escaped();
			}
			// Add Globalblocking link to CentralWiki
			if ( $globalBlockingToollink !== false
				&& IPUtils::isIPAddress( $name )
			) {
				// Get GlobalBlock SpecialPage name in UserLang from the first Alias name
				$centralGBUrl = WikiMap::getForeignURL(
					$globalBlockingToollink['centralDB'],
					'Special:GlobalBlock'
				);
				$spgb = $aliases['GlobalBlock'][0];
				$gblinkAlias = str_replace( '_', ' ', $spgb );
				if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
					$gbUserGroups = CentralAuthUser::getInstance( $this->getUser() )->getGlobalGroups();
					// Link to GB via WikiMap since CA require it
					if ( $centralGBUrl === false ) {
						throw new Exception(
							'Could not retrieve URL for global blocking toollink'
						);
					}
					$linkGB = Html::element( 'a',
						[
							'href' => $centralGBUrl . "/" . $name,
							'title' => $this->msg( 'globalblocking-block-submit' )->text(),
						],
						$gblinkAlias
					);
				} elseif ( $centralGBUrl !== false ) {
					// Case wikimap configured without CentralAuth extension
					$user = $this->getUser();
					// Get effective Local user groups since there is a wikimap but there is no CA
					$gbUserGroups = $user->getEffectiveGroups();
					$linkGB = Html::element( 'a',
						[
							'href' => $centralGBUrl . "/" . $name,
							'title' => $this->msg( 'globalblocking-block-submit' )->text(),
						],
						$gblinkAlias
					);
				} else {
					// Load local user group instead
					$gbUserGroups = [ '' ];
					$user = $this->getUser();
					$gbtitle = self::getTitleFor( 'GlobalBlock' );
					$linkGB = $linkrenderer->makeKnownLink(
						$gbtitle,
						$gblinkAlias,
						[ 'title' => $this->msg( 'globalblocking-block-submit' ) ]
					);
					$gbUserCanDo = $permissionManager->userHasRight( $user, 'globalblock' );
					if ( $gbUserCanDo === true ) {
						$globalBlockingToollink['groups'] = $gbUserGroups;
					}
				}
				// Only load the script for users in the configured global(local) group(s) or
				// for local user with globalblock permission if there is no WikiMap
				if ( count( array_intersect( $globalBlockingToollink['groups'], $gbUserGroups ) ) ) {
					$s .= ' ' . $this->msg( 'parentheses' )->rawParams( $linkGB )->escaped();
				}
			}
			// Show edit time range
			$s .= ' ' . $this->getTimeRangeString( $users_first[$name], $users_last[$name] ) . ' ';
			// Total edit count
			// @todo FIXME: i18n issue: Hard coded brackets.
			$s .= ' [<strong>' . htmlspecialchars( $count ) . '</strong>]<br />';
			// Check if this user or IP is blocked. If so, give a link to the block log...
			$flags = $this->userBlockFlags( $ip, $users_ids[$name], $user );
			$s .= implode( ' ', $flags );
			$s .= '<ol>';
			// List out each IP/XFF combo for this username
			for ( $i = ( count( $users_infosets[$name] ) - 1 ); $i >= 0; $i-- ) {
				// users_infosets[$name][$i] is array of [ $row->cuc_ip, XFF ];
				list( $clientIP, $xffString ) = $users_infosets[$name][$i];
				// IP link
				$s .= '<li>';
				$s .= $this->getSelfLink( $clientIP, [ 'user' => $clientIP ] );
				// XFF string, link to /xff search
				if ( $xffString ) {
					// Flag our trusted proxies
					list( $client ) = CUHooks::getClientIPfromXFF( $xffString );
					// XFF was trusted if client came from it
					$trusted = ( $client === $clientIP );
					$c = $trusted ? '#F0FFF0' : '#FFFFCC';
					$s .= '&#160;&#160;&#160;<span style="background-color: ' . $c .
						'"><strong>XFF</strong>: ';
					$s .= $this->getSelfLink( $xffString, [ 'user' => $client . '/xff' ] ) .
						'</span>';
				}
				$s .= "</li>\n";
			}
			$s .= '</ol><br /><ol>';
			// List out each agent for this username
			for ( $i = ( count( $users_agentsets[$name] ) - 1 ); $i >= 0; $i-- ) {
				$agent = $users_agentsets[$name][$i];
				$s .= '<li><i dir="ltr">' . htmlspecialchars( $agent ) . "</i></li>\n";
			}
			$s .= '</ol>';
			$s .= '</li>';
		}
		$s .= "</ul></div>\n";
		if ( $permissionManager->userHasRight( $this->getUser(), 'block' )
			&& !$this->getUser()->getBlock()
		) {
			// FIXME: The block <form> is currently added for users without 'block' right
			// - only the user-visible form is shown appropriately
			$s .= $this->getBlockForm( $tag, $talkTag );
			$s .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		}
		$s .= "</form>\n";

		$out->addHTML( $s );
	}

	/**
	 * @param string $tag
	 * @param string $talkTag
	 * @return string
	 */
	protected function getBlockForm( $tag, $talkTag ) {
		$config = $this->getConfig();
		$checkUserCAMultiLock = $config->get( 'CheckUserCAMultiLock' );
		if ( $checkUserCAMultiLock !== false ) {
			if ( !ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
				// $wgCheckUserCAMultiLock shouldn't be enabled if CA is not loaded
				throw new Exception( '$wgCheckUserCAMultiLock requires CentralAuth extension.' );
			}

			$caUserGroups = CentralAuthUser::getInstance( $this->getUser() )->getGlobalGroups();
			// Only load the script for users in the configured global group(s)
			if ( count( array_intersect( $checkUserCAMultiLock['groups'], $caUserGroups ) ) ) {
				$out = $this->getOutput();
				$centralMLUrl = WikiMap::getForeignURL(
					$checkUserCAMultiLock['centralDB'],
					// Use canonical name instead of local name so that it works
					// even if the local language is different from central wiki
					'Special:MultiLock'
				);
				if ( $centralMLUrl === false ) {
					throw new Exception(
						"Could not retrieve URL for {$checkUserCAMultiLock['centralDB']}"
					);
				}
				$out->addJsConfigVars( 'wgCUCAMultiLockCentral', $centralMLUrl );
				$out->addModules( 'ext.checkUser' );
			}
		}

		$s = "<fieldset>\n";
		$s .= '<legend>' . $this->msg( 'checkuser-massblock' )->escaped() . "</legend>\n";
		$s .= $this->msg( 'checkuser-massblock-text' )->parseAsBlock() . "\n";
		$s .= '<table><tr>' .
			'<td>' . Xml::check( 'usetag', false, [ 'id' => 'usetag' ] ) . '</td>' .
			'<td>' . Xml::label( $this->msg( 'checkuser-blocktag' )->text(), 'usetag' ) .
			'</td>' .
			'<td>' . Xml::input( 'tag', 46, $tag, [ 'id' => 'blocktag' ] ) . '</td>' .
			'</tr><tr>' .
			'<td>' . Xml::check( 'usettag', false, [ 'id' => 'usettag' ] ) . '</td>' .
			'<td>' . Xml::label( $this->msg( 'checkuser-blocktag-talk' )->text(), 'usettag' ) .
			'</td>' .
			'<td>' . Xml::input( 'talktag', 46, $talkTag, [ 'id' => 'talktag' ] ) . '</td>';
		if ( $config->get( 'BlockAllowsUTEdit' ) ) {
			$s .= '</tr><tr>' .
				'<td>' . Xml::check( 'blocktalk', false, [ 'id' => 'blocktalk' ] ) . '</td>' .
				'<td>' . Xml::label( $this->msg( 'checkuser-blocktalk' )->text(), 'blocktalk' ) .
				'</td>';
		}
		if (
			$this->blockPermissionCheckerFactory
				->newBlockPermissionChecker(
					null,
					$this->getUser()
				)
				->checkEmailPermissions()
		) {
			$s .= '</tr><tr>' .
				'<td>' . Xml::check( 'blockemail', false, [ 'id' => 'blockemail' ] ) . '</td>' .
				'<td>' . Xml::label( $this->msg( 'checkuser-blockemail' )->text(), 'blockemail' )
				. '</td>';
		}
		$s .= '<tr><td>' . Xml::check( 'reblock', false, [ 'id' => 'reblock' ] ) . '</td>';
		$s .= '<td>' . Xml::label( $this->msg( 'checkuser-reblock' )->text(), 'reblock' )
			. '</td></tr>';
		$s .= '</tr></table>';
		$s .= '<p>' . $this->msg( 'checkuser-reason' )->escaped() . '&#160;';
		$s .= Xml::input( 'blockreason', 46, '', [ 'maxlength' => '150', 'id' => 'blockreason' ] );
		$s .= '&#160;' . Xml::submitButton( $this->msg( 'checkuser-massblock-commit' )->text(),
			[ 'id' => 'checkuserblocksubmit', 'name' => 'checkuserblock' ] ) . "</p>\n";
		$s .= "</fieldset>\n";

		return $s;
	}

	/**
	 * Get an HTML link (<a> element) to Special:CheckUser
	 *
	 * @param string $text content to use within <a> tag
	 * @param array $params query parameters to use in the URL
	 * @return string
	 */
	private function getSelfLink( $text, array $params ) {
		static $title;
		if ( $title === null ) {
			$title = $this->getPageTitle();
		}
		return $this->getLinkRenderer()->makeKnownLink(
			$title,
			new HtmlArmor( '<bdi>' . htmlspecialchars( $text ) . '</bdi>' ),
			[],
			$params
		);
	}

	/**
	 * @param string $ip
	 * @param int $userId
	 * @param User $user
	 * @return array
	 */
	protected function userBlockFlags( $ip, $userId, $user ) {
		$flags = [];

		$block = DatabaseBlock::newFromTarget( $user, $ip, false );
		if ( $block instanceof DatabaseBlock ) {
			// Locally blocked
			$flags[] = $this->getBlockFlag( $block );
		} elseif ( $ip == $user->getName() && $user->isBlockedGlobally( $ip ) ) {
			// Globally blocked IP
			$flags[] = '<strong>(' . $this->msg( 'checkuser-gblocked' )->escaped() . ')</strong>';
		} elseif ( self::userWasBlocked( $user->getName() ) ) {
			// Previously blocked
			$userpage = $user->getUserPage();
			$blocklog = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-wasblocked' )->text(),
				[],
				[
					'type' => 'block',
					'page' => $userpage->getPrefixedText()
				]
			);
			// @todo FIXME: Hard coded parentheses.
			$flags[] = '<strong>(' . $blocklog . ')</strong>';
		}

		// Show if account is local only
		if ( $user->getId() &&
			CentralIdLookup::factory()
				->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW ) === 0
		) {
			// @todo FIXME: i18n issue: Hard coded parentheses.
			$flags[] = '<strong>(' . $this->msg( 'checkuser-localonly' )->escaped() . ')</strong>';
		}
		// Check for extra user rights...
		if ( $userId ) {
			if ( $user->isLocked() ) {
				// @todo FIXME: i18n issue: Hard coded parentheses.
				$flags[] = '<b>(' . $this->msg( 'checkuser-locked' )->escaped() . ')</b>';
			}
			$list = [];
			foreach ( $user->getGroups() as $group ) {
				$list[] = self::buildGroupLink( $group, $user->getName() );
			}
			$groups = $this->getLanguage()->commaList( $list );
			if ( $groups ) {
				// @todo FIXME: i18n issue: Hard coded parentheses.
				$flags[] = '<i>(' . $groups . ')</i>';
			}
		}

		return $flags;
	}

	/**
	 * Get a streamlined recent changes line with IP data
	 *
	 * @param stdClass $row
	 * @return string
	 */
	protected function CUChangesLine( $row ) {
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
		$line .= ' . . ' . htmlspecialchars(
			$this->getLanguage()->userTime( wfTimestamp( TS_MW, $row->cuc_timestamp ), $this->getUser() )
			) . ' . . ';
		// Userlinks
		$user = User::newFromId( $row->cuc_user );
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
		if ( $classnouser === true ) {
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
			$user = User::newFromName( $row->cuc_user_text, false );
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
			$revRecord = MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionById( $row->cuc_this_oldid );
			if ( !$revRecord ) {
				// Assume revision is deleted
				$dbr = wfGetDB( DB_REPLICA );
				$queryInfo = MediaWikiServices::getInstance()
					->getRevisionStore()
					->getArchiveQueryInfo();
				$tmp = $dbr->selectRow(
					$queryInfo['tables'],
					$queryInfo['fields'],
					[ 'ar_rev_id' => $row->cuc_this_oldid ],
					__METHOD__,
					[],
					$queryInfo['joins']
				);
				if ( $tmp ) {
					$revRecord = MediaWikiServices::getInstance()
						->getRevisionFactory()
						->newRevisionFromArchiveRow( $tmp );
				}

				if ( !$revRecord ) {
					// This shouldn't happen, CheckUser points to a revision
					// that isn't in revision nor archive table?
					throw new Exception(
						"Couldn't fetch revision cu_changes table links to (cuc_this_oldid {$row->cuc_this_oldid})"
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
				'reason' => $this->reason
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
					'reason' => $this->reason
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
	 * Get formatted timestamp(s) to show the time of first and last change.
	 * If both timestamps are the same, it will be shown only once.
	 *
	 * @param string $first Timestamp of the first change
	 * @param string $last Timestamp of the last change
	 * @return string
	 */
	protected function getTimeRangeString( $first, $last ) {
		$s = $this->getFormattedTimestamp( $first );
		if ( $first !== $last ) {
			// @todo i18n issue - hardcoded string
			$s .= ' -- ';
			$s .= $this->getFormattedTimestamp( $last );
		}
		return $this->msg( 'parentheses' )->params( $s )->escaped();
	}

	/**
	 * Get a formatted timestamp string in the current language
	 * for displaying to the user.
	 *
	 * @param string $timestamp
	 * @return string
	 */
	protected function getFormattedTimestamp( $timestamp ) {
		return $this->getLanguage()->userTimeAndDate(
			wfTimestamp( TS_MW, $timestamp ), $this->getUser()
		);
	}

	/**
	 * @param stdClass $row
	 * @return string diff, hist and page other links related to the change
	 */
	protected function getLinksFromRow( $row ) {
		$links = [];
		// Log items
		if ( $row->cuc_type == RC_LOG ) {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			// @todo FIXME: Hard coded parentheses.
			$links['log'] = '(' . $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				new HtmlArmor( $this->message['log'] ),
				[],
				[ 'page' => $title->getPrefixedText() ]
			) . ')';
		} else {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			// New pages
			if ( $row->cuc_type == RC_NEW ) {
				$links['diff'] = '(' . $this->message['diff'] . ') ';
			} else {
				// Diff link
				// @todo FIXME: Hard coded parentheses.
				$links['diff'] = ' (' . $this->getLinkRenderer()->makeKnownLink(
					$title,
					new HtmlArmor( $this->message['diff'] ),
					[],
					[
						'curid' => $row->cuc_page_id,
						'diff' => $row->cuc_this_oldid,
						'oldid' => $row->cuc_last_oldid
					]
				) . ') ';
			}
			// History link
			// @todo FIXME: Hard coded parentheses.
			$links['history'] = ' (' . $this->getLinkRenderer()->makeKnownLink(
				$title,
				new HtmlArmor( $this->message['hist'] ),
				[],
				[
					'curid' => $title->exists() ? $row->cuc_page_id : null,
					'action' => 'history'
				]
			) . ') . . ';
			// Some basic flags
			if ( $row->cuc_type == RC_NEW ) {
				$links['newpage'] = '<span class="newpage">' . $this->message['newpageletter'] .
					'</span>';
			}
			if ( $row->cuc_minor ) {
				$links['minor'] = '<span class="minor">' . $this->message['minoreditletter'] .
					'</span>';
			}
			// Page link
			$links['title'] = $this->getLinkRenderer()->makeLink( $title );
		}

		Hooks::run( 'SpecialCheckUserGetLinksFromRow', [ $this, $row, &$links ] );
		// @phan-suppress-next-line PhanRedundantCondition May set by hook
		if ( is_array( $links ) ) {
			return implode( ' ', $links );
		} else {
			wfDebugLog( __CLASS__,
				__METHOD__ . ': Expected array from SpecialCheckUserGetLinksFromRow $links param,'
				. ' but received ' . gettype( $links )
			);
			return '';
		}
	}

	protected static function userWasBlocked( $name ) {
		$userpage = Title::makeTitle( NS_USER, $name );
		$dbr = wfGetDB( DB_REPLICA );

		// Remove after T270620 is resolved
		$index = $dbr->indexExists( 'logging', 'page_time', __METHOD__ )
			? 'page_time'
			: 'log_page_time';

		return (bool)$dbr->selectField( 'logging', '1',
			[
				'log_type' => [ 'block', 'suppress' ],
				'log_action' => 'block',
				'log_namespace' => $userpage->getNamespace(),
				'log_title' => $userpage->getDBkey()
			],
			__METHOD__,
			[ 'USE INDEX' => $index ]
		);
	}

	/**
	 * Format a link to a group description page
	 *
	 * @param string $group
	 * @param string $username
	 * @return string
	 */
	protected static function buildGroupLink( $group, $username ) {
		static $cache = [];
		if ( !isset( $cache[$group] ) ) {
			$cache[$group] = UserGroupMembership::getLink(
				$group, RequestContext::getMain(), 'html'
			);
		}
		return $cache[$group];
	}

	/**
	 * @param string $target an IP address or CIDR range
	 * @return bool
	 */
	public static function isValidRange( $target ) {
		$CIDRLimit = \RequestContext::getMain()->getConfig()->get( 'CheckUserCIDRLimit' );
		if ( IPUtils::isValidRange( $target ) ) {
			list( $ip, $range ) = explode( '/', $target, 2 );
			if ( ( IPUtils::isIPv4( $ip ) && $range < $CIDRLimit['IPv4'] ) ||
				( IPUtils::isIPv6( $ip ) && $range < $CIDRLimit['IPv6'] ) ) {
					// range is too wide
					return false;
			}
			return true;
		}

		return IPUtils::isValid( $target );
	}

	/**
	 * @param IDatabase $db
	 * @param string $target an IP address or CIDR range
	 * @param string|bool $xfor
	 * @return array|false array for valid conditions, false if invalid
	 */
	public static function getIpConds( IDatabase $db, $target, $xfor = false ) {
		$type = $xfor ? 'xff' : 'ip';

		if ( !self::isValidRange( $target ) ) {
			return false;
		}

		if ( IPUtils::isValidRange( $target ) ) {
			list( $start, $end ) = IPUtils::parseRange( $target );
			return [ 'cuc_' . $type . '_hex BETWEEN ' . $db->addQuotes( $start ) .
				' AND ' . $db->addQuotes( $end ) ];
		} elseif ( IPUtils::isValid( $target ) ) {
				return [ "cuc_{$type}_hex" => IPUtils::toHex( $target ) ];
		}
		// invalid IP
		return false;
	}

	protected function getTimeConds( $period ) {
		if ( !$period ) {
			return false;
		}
		$dbr = wfGetDB( DB_REPLICA );
		$cutoff_unixtime = time() - ( $period * 24 * 3600 );
		$cutoff_unixtime -= $cutoff_unixtime % 86400;
		$cutoff = $dbr->addQuotes( $dbr->timestamp( $cutoff_unixtime ) );
		return "cuc_timestamp > $cutoff";
	}

	public static function addLogEntry( $logType, $targetType, $target, $reason, $targetID = 0 ) {
		$user = RequestContext::getMain()->getUser();

		if ( $targetType == 'ip' ) {
			list( $rangeStart, $rangeEnd ) = IPUtils::parseRange( $target );
			$targetHex = $rangeStart;
			if ( $rangeStart == $rangeEnd ) {
				$rangeStart = $rangeEnd = '';
			}
		} else {
			$targetHex = $rangeStart = $rangeEnd = '';
		}

		$timestamp = time();
		$data = [
			'cul_user' => $user->getId(),
			'cul_user_text' => $user->getName(),
			'cul_reason' => $reason,
			'cul_type' => $logType,
			'cul_target_id' => $targetID,
			'cul_target_text' => trim( $target ),
			'cul_target_hex' => $targetHex,
			'cul_range_start' => $rangeStart,
			'cul_range_end' => $rangeEnd
		];
		$fname = __METHOD__;

		DeferredUpdates::addCallableUpdate(
			function () use ( $data, $timestamp, $fname ) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->insert(
					'cu_log',
					[
						'cul_timestamp' => $dbw->timestamp( $timestamp )
					] + $data,
					$fname
				);
			},
			// fail on error and show no output
			DeferredUpdates::PRESEND
		);
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$user = User::newFromName( $search );
		if ( !$user ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return UserNamePrefixSearch::search( 'public', $search, $limit, $offset );
	}

	protected function getGroupName() {
		return 'users';
	}
}
