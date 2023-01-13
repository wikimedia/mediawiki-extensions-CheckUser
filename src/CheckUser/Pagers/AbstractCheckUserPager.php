<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ActorMigration;
use CentralIdLookup;
use ExtensionRegistry;
use FormOptions;
use Html;
use HtmlArmor;
use IContextSource;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CheckUser\CheckUser\CheckUserPagerNavigationBuilder;
use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Extension\GlobalBlocking\GlobalBlocking;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Navigation\PagerNavigationBuilder;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use RangeChronologicalPager;
use RequestContext;
use SpecialPage;
use TemplateParser;
use Title;
use TitleValue;
use UserGroupMembership;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

abstract class AbstractCheckUserPager extends RangeChronologicalPager {

	/**
	 * Form fields that when paging should be set and managed
	 *  by the token. Used so the client cannot generate results
	 *  that do not match the original request which generated
	 *  the associated CheckUserLog entry.
	 */
	public const TOKEN_MANAGED_FIELDS = [
		'reason',
		'checktype',
		'period',
		'dir',
		'limit',
		'offset',
	];

	/**
	 * Null if $target is a user.
	 * Boolean is $target is a IP / range.
	 *  - False if XFF is not appended
	 *  - True if XFF is appended
	 *
	 * @var null|bool
	 */
	protected $xfor = null;

	/** @var string */
	private $logType;

	/** @var FormOptions */
	protected $opts;

	/** @var UserGroupManager */
	protected $userGroupManager;

	/** @var CentralIdLookup */
	protected $centralIdLookup;

	/** @var UserIdentity */
	protected $target;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var ActorMigration */
	private $actorMigration;

	/**
	 * @var CheckUserLogService
	 */
	private $checkUserLogService;

	/** @var TemplateParser */
	protected $templateParser;

	/** @var UserFactory */
	protected $userFactory;

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
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		$this->opts = $opts;
		$this->target = $target;
		$this->logType = $logType;

		$this->mDb = $loadBalancer->getConnection( DB_REPLICA );

		parent::__construct( $context, $linkRenderer );

		$maximumRowCount = $this->getConfig()->get( 'CheckUserMaximumRowCount' );
		$this->mDefaultLimit = $limit ?? $maximumRowCount;
		if ( $this->opts->getValue( 'limit' ) ) {
			$this->mLimit = min(
				$this->opts->getValue( 'limit' ),
				$this->getConfig()->get( 'CheckUserMaximumRowCount' )
			);
		} else {
			$this->mLimit = $maximumRowCount;
		}

		$this->mLimitsShown = [
			$maximumRowCount / 25,
			$maximumRowCount / 10,
			$maximumRowCount / 5,
			$maximumRowCount / 2,
			$maximumRowCount,
		];

		$this->mLimitsShown = array_map( 'ceil', $this->mLimitsShown );
		$this->mLimitsShown = array_unique( $this->mLimitsShown );

		$this->userGroupManager = $userGroupManager;
		$this->centralIdLookup = $centralIdLookup;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->specialPageFactory = $specialPageFactory;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->actorMigration = $actorMigration;
		$this->checkUserLogService = $checkUserLogService;
		$this->userFactory = $userFactory;

		$this->templateParser = new TemplateParser( __DIR__ . '/../../../templates' );

		// Get any set token data. Used for paging without adding extra logs
		$tokenData = $this->tokenQueryManager->getDataFromRequest( $this->getRequest() );
		if ( !$tokenData ) {
			// Log if the token data is not set. A token will only be generated by
			//  the server for CheckUser for paging links after running a check.
			//  It will also only be valid if not tampered with as it's encrypted.
			//  Paging through the entries won't need an extra log entry.
			$this->checkUserLogService->addLogEntry(
				$this->getUser(),
				$this->logType,
				$target->getId() ? 'user' : 'ip',
				$target->getName(),
				$this->opts->getValue( 'reason' ),
				$target->getId()
			);
		}

		$this->getDateRangeCond( '', '' );
	}

	/**
	 * Get the cutoff timestamp and add it to the range conditions for the query
	 *
	 * @param string $startStamp Ignored.
	 * @param string $endStamp Ignored.
	 * @return array the range conditions which are also set in $this->rangeConds
	 */
	public function getDateRangeCond( $startStamp, $endStamp ): array {
		$this->rangeConds = [];
		$period = $this->opts->getValue( 'period' );
		if ( $period ) {
			$cutoffUnixtime = ConvertibleTimestamp::time() - ( $period * 24 * 3600 );
			$cutoffUnixtime -= $cutoffUnixtime % 86400;
			$cutoff = $this->mDb->addQuotes( $this->mDb->timestamp( $cutoffUnixtime ) );
			$this->rangeConds = [ "cuc_timestamp > $cutoff" ];
		}

		return $this->rangeConds;
	}

	/**
	 * Get formatted timestamp(s) to show the time of first and last change.
	 * If both timestamps are the same, it will be shown only once.
	 *
	 * @param string $first Timestamp of the first change
	 * @param string $last Timestamp of the last change
	 * @return string
	 */
	protected function getTimeRangeString( string $first, string $last ): string {
		$s = $this->getFormattedTimestamp( $first );
		if ( $first !== $last ) {
			// @todo i18n issue - hardcoded string
			$s .= ' -- ';
			$s .= $this->getFormattedTimestamp( $last );
		}
		return $s;
	}

	/**
	 * Get a link to block information about the passed block for displaying to the user.
	 *
	 * @param DatabaseBlock $block
	 * @return string
	 */
	protected function getBlockFlag( DatabaseBlock $block ): string {
		if ( $block->getType() === DatabaseBlock::TYPE_AUTO ) {
			$ret = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'BlockList' ),
				$this->msg( 'checkuser-blocked' )->text(),
				[],
				[ 'wpTarget' => "#{$block->getId()}" ]
			);
		} else {
			$userPage = Title::makeTitle( NS_USER, $block->getTargetName() );
			$ret = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-blocked' )->text(),
				[],
				[
					'type' => 'block',
					'page' => $userPage->getPrefixedText()
				]
			);

			// Add the blocked range if the block is on a range
			if ( $block->getType() === DatabaseBlock::TYPE_RANGE ) {
				$ret .= ' - ' . htmlspecialchars( $block->getTargetName() );
			}
		}

		return Html::rawElement(
			'strong',
			[ 'class' => 'mw-changeslist-links' ],
			$ret
		);
	}

	/**
	 * Get an HTML link (<a> element) to Special:CheckUser
	 *
	 * @param string $text content to use within <a> tag
	 * @param array $params query parameters to use in the URL
	 * @return string
	 */
	protected function getSelfLink( string $text, array $params ): string {
		$title = $this->getTitleValue();
		return $this->getLinkRenderer()->makeKnownLink(
			$title,
			new HtmlArmor( '<bdi>' . htmlspecialchars( $text ) . '</bdi>' ),
			[],
			$params
		);
	}

	/**
	 * @param string $page the string title get the TitleValue for.
	 * @return TitleValue the associated TitleValue object
	 */
	protected function getTitleValue( string $page = 'CheckUser' ): TitleValue {
		return new TitleValue(
			NS_SPECIAL,
			$this->specialPageFactory->getLocalNameFor( $page )
		);
	}

	/**
	 * @param string $page the string title get the Title for.
	 * @return Title the associated Title object
	 */
	protected function getPageTitle( string $page = 'CheckUser' ): Title {
		return Title::newFromLinkTarget(
			$this->getTitleValue( $page )
		);
	}

	/**
	 * Get a formatted timestamp string in the current language
	 * for displaying to the user.
	 *
	 * @param string $timestamp
	 * @return string
	 */
	protected function getFormattedTimestamp( string $timestamp ): string {
		return $this->getLanguage()->userTimeAndDate(
			wfTimestamp( TS_MW, $timestamp ), $this->getUser()
		);
	}

	/**
	 * Generates the "no matches for X" message.
	 * Unless the target was an xff also try
	 *  to display the time of the last edit.
	 *
	 * @inheritDoc
	 */
	protected function getEmptyBody(): string {
		if ( $this->xfor ?? true ) {
			$user = $this->userIdentityLookup->getUserIdentityByName( $this->target->getName() );

			$lastEdit = false;

			$revWhere = $this->actorMigration->getWhere( $this->mDb, 'rev_user', $user );
			foreach ( $revWhere['orconds'] as $cond ) {
				$lastEdit = max( $lastEdit, $this->mDb->newSelectQueryBuilder()
					->tables( [ 'revision' ] + $revWhere['tables'] )
					->field( 'rev_timestamp' )
					->conds( $cond )
					->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_DESC )
					->joinConds( $revWhere['joins'] )
					->caller( __METHOD__ )
					->fetchField()
				);
			}
			$lastEdit = max( $lastEdit, $this->mDb->newSelectQueryBuilder()
				->table( 'logging' )
				->field( 'log_timestamp' )
				->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_DESC )
				->join( 'actor', null, 'actor_id=log_actor' )
				->where( [ 'actor_name' => $this->target->getName() ] )
				->caller( __METHOD__ )
				->fetchField()
			);

			if ( $lastEdit ) {
				$lastEditTime = wfTimestamp( TS_MW, $lastEdit );
				$lang = $this->getLanguage();
				$contextUser = $this->getUser();
				// FIXME: don't pass around parsed messages
				return $this->msg( 'checkuser-nomatch-edits',
					$lang->userDate( $lastEditTime, $contextUser ),
					$lang->userTime( $lastEditTime, $contextUser )
				)->parseAsBlock() . "\n";
			}
		}
		return $this->msg( 'checkuser-nomatch' )->parseAsBlock() . "\n";
	}

	/**
	 * @param string $ip
	 * @param UserIdentity $user
	 * @return string[]
	 */
	protected function userBlockFlags( string $ip, UserIdentity $user ): array {
		$flags = [];
		// Needed because User::isBlockedGlobally doesn't seem to have a non User:: method.
		$userObj = $this->userFactory->newFromUserIdentity( $user );

		$block = DatabaseBlock::newFromTarget( $user, $ip );
		if ( $block instanceof DatabaseBlock ) {
			// Locally blocked
			$flags[] = $this->getBlockFlag( $block );
		} elseif (
			$ip == $user->getName() &&
			ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' ) &&
			GlobalBlocking::getUserBlock(
				$this->userFactory->newFromUserIdentity( $user ),
				$ip
			) instanceof AbstractBlock
		) {
			// Globally blocked IP
			$flags[] = '<strong>(' . $this->msg( 'checkuser-gblocked' )->escaped() . ')</strong>';
		} elseif ( $this->userWasBlocked( $user->getName() ) ) {
			// Previously blocked
			$blocklog = $this->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-wasblocked' )->text(),
				[],
				[
					'type' => 'block',
					// @todo Use TitleFormatter and PageReference to avoid the global state
					'page' => Title::makeTitle( NS_USER, $user->getName() )->getPrefixedText()
				]
			);
			$flags[] = Html::rawElement( 'strong', [ 'class' => 'mw-changeslist-links' ], $blocklog );
		}

		// Show if account is local only
		if ( $user->getId() &&
			$this->centralIdLookup
				->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW ) === 0
		) {
			$flags[] = Html::rawElement(
				'strong',
				[ 'class' => 'mw-changeslist-links' ],
				$this->msg( 'checkuser-localonly' )->escaped()
			);
		}
		// Check for extra user rights...
		if ( $user->getId() ) {
			if ( $userObj->isLocked() ) {
				$flags[] = Html::rawElement(
					'strong',
					[ 'class' => 'mw-changeslist-links' ],
					$this->msg( 'checkuser-locked' )->escaped()
				);
			}
			$list = [];
			foreach ( $this->userGroupManager->getUserGroups( $user ) as $group ) {
				$list[] = self::buildGroupLink( $group );
			}
			$groups = $this->getLanguage()->commaList( $list );
			if ( $groups ) {
				$flags[] = Html::rawElement( 'i', [ 'class' => 'mw-changeslist-links' ], $groups );
			}
		}

		return $flags;
	}

	/**
	 * Format a link to a group description page
	 *
	 * @param string $group
	 * @return string
	 */
	protected static function buildGroupLink( string $group ): string {
		static $cache = [];
		if ( !isset( $cache[$group] ) ) {
			$cache[$group] = UserGroupMembership::getLink(
				$group, RequestContext::getMain(), 'html'
			);
		}
		return $cache[$group];
	}

	/**
	 * Get whether the user has ever been blocked.
	 *
	 * @param string $name the username
	 * @return bool whether the user with that username has ever been blocked
	 */
	protected function userWasBlocked( string $name ): bool {
		$userpage = Title::makeTitle( NS_USER, $name );

		return (bool)$this->mDb->newSelectQueryBuilder()
			->table( 'logging' )
			->field( '1' )
			->conds( [
				'log_type' => [ 'block', 'suppress' ],
				'log_action' => 'block',
				'log_namespace' => $userpage->getNamespace(),
				'log_title' => $userpage->getDBkey()
			] )
			->useIndex( 'log_page_time' )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * @param string $target an IP address or CIDR range
	 * @return bool
	 */
	public static function isValidRange( string $target ): bool {
		$CIDRLimit = RequestContext::getMain()->getConfig()->get( 'CheckUserCIDRLimit' );
		if ( IPUtils::isValidRange( $target ) ) {
			[ $ip, $range ] = explode( '/', $target, 2 );
			return !(
				( IPUtils::isIPv4( $ip ) && $range < $CIDRLimit['IPv4'] ) ||
				( IPUtils::isIPv6( $ip ) && $range < $CIDRLimit['IPv6'] )
			);
		}

		return IPUtils::isValid( $target );
	}

	/**
	 * Get the WHERE conditions for an IP address / range, optionally as a XFF.
	 *
	 * @param IDatabase $db
	 * @param string $target an IP address or CIDR range
	 * @param string|bool $xfor
	 * @return array|false array for valid conditions, false if invalid
	 */
	public static function getIpConds( IDatabase $db, string $target, $xfor = false ) {
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

	/** @inheritDoc */
	public function getIndexField() {
		return 'cuc_timestamp';
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		return $this->getNavigationBar() . '<div id="checkuserresults">';
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		return '</div>' . $this->getNavigationBar();
	}

	/** @inheritDoc */
	public function getNavigationBuilder(): PagerNavigationBuilder {
		$pagingQueries = $this->getPagingQueries();
		$baseQuery = array_merge( $this->getDefaultQuery(), [
			// These query parameters are all defined here, even though some are null,
			// to ensure consistent order of parameters when they're used.
			'dir' => null,
			'offset' => $this->getOffsetQuery(),
			'limit' => null,
		] );

		$navBuilder = new CheckUserPagerNavigationBuilder(
			$this->getContext(),
			$this->tokenQueryManager,
			$this->getCsrfTokenSet(),
			$this->getRequest(),
			$this->opts,
			$this->target
		);
		$navBuilder
			->setPage( $this->getTitle() )
			->setLinkQuery( $baseQuery )
			->setLimits( $this->mLimitsShown )
			->setLimitLinkQueryParam( 'limit' )
			->setCurrentLimit( $this->mLimit )
			->setPrevLinkQuery( $pagingQueries['prev'] ?: null )
			->setNextLinkQuery( $pagingQueries['next'] ?: null )
			->setFirstLinkQuery( $pagingQueries['first'] ?: null )
			->setLastLinkQuery( $pagingQueries['last'] ?: null );

		return $navBuilder;
	}
}
