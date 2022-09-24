<?php

namespace MediaWiki\CheckUser\CheckUser;

use ActorMigration;
use CentralIdLookup;
use FormOptions;
use Html;
use HTMLForm;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetEditsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser;
use MediaWiki\CheckUser\CheckUser\Widgets\HTMLTextFieldNoDisabledStyling;
use MediaWiki\CheckUser\CheckUserLogService;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\CentralId\CentralIdLookupFactory;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserRigorOptions;
use Message;
use OOUI\IconWidget;
use SpecialPage;
use Title;
use UserBlockedError;
use Wikimedia\AtEase\AtEase;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;
use WikitextContent;

class SpecialCheckUser extends SpecialPage {
	/**
	 * The possible subtypes represented as constants.
	 * The constants represent the old string values
	 * for backwards compatibility.
	 */
	public const SUBTYPE_GET_IPS = 'subuserips';

	public const SUBTYPE_GET_EDITS = 'subedits';

	public const SUBTYPE_GET_USERS = 'subipusers';

	/**
	 * @var FormOptions the form parameters.
	 */
	protected $opts;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var BlockPermissionCheckerFactory */
	private $blockPermissionCheckerFactory;

	/** @var BlockUserFactory */
	private $blockUserFactory;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var CentralIdLookup */
	private $centralIdLookup;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var UserFactory */
	private $userFactory;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var CheckUserLogService */
	private $checkUserLogService;

	/** @var CommentFormatter */
	private $commentFormatter;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/** @var UserNameUtils */
	private $userNameUtils;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param BlockPermissionCheckerFactory $blockPermissionCheckerFactory
	 * @param BlockUserFactory $blockUserFactory
	 * @param UserGroupManager $userGroupManager
	 * @param CentralIdLookupFactory $centralIdLookupFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param PermissionManager $permissionManager
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param TokenQueryManager $tokenQueryManager
	 * @param ILoadBalancer $loadBalancer
	 * @param ActorMigration $actorMigration
	 * @param UserFactory $userFactory
	 * @param RevisionStore $revisionStore
	 * @param CheckUserLogService $checkUserLogService
	 * @param CommentFormatter $commentFormatter
	 * @param UserEditTracker $userEditTracker
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		BlockPermissionCheckerFactory $blockPermissionCheckerFactory,
		BlockUserFactory $blockUserFactory,
		UserGroupManager $userGroupManager,
		CentralIdLookupFactory $centralIdLookupFactory,
		WikiPageFactory $wikiPageFactory,
		PermissionManager $permissionManager,
		UserIdentityLookup $userIdentityLookup,
		TokenQueryManager $tokenQueryManager,
		ILoadBalancer $loadBalancer,
		ActorMigration $actorMigration,
		UserFactory $userFactory,
		RevisionStore $revisionStore,
		CheckUserLogService $checkUserLogService,
		CommentFormatter $commentFormatter,
		UserEditTracker $userEditTracker,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( 'CheckUser', 'checkuser' );

		$this->linkBatchFactory = $linkBatchFactory;
		$this->blockPermissionCheckerFactory = $blockPermissionCheckerFactory;
		$this->blockUserFactory = $blockUserFactory;
		$this->userGroupManager = $userGroupManager;
		$this->centralIdLookup = $centralIdLookupFactory->getLookup();
		$this->wikiPageFactory = $wikiPageFactory;
		$this->permissionManager = $permissionManager;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->loadBalancer = $loadBalancer;
		$this->actorMigration = $actorMigration;
		$this->userFactory = $userFactory;
		$this->revisionStore = $revisionStore;
		$this->checkUserLogService = $checkUserLogService;
		$this->commentFormatter = $commentFormatter;
		$this->userEditTracker = $userEditTracker;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
	}

	public function doesWrites() {
		// logging
		return true;
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
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

		$request = $this->getRequest();

		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'checktype', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'offset', '' );
		$opts->add( 'limit', 0 );
		$opts->add( 'dir', '' );
		$opts->add( 'token', '' );
		$opts->add( 'action', '' );
		$opts->add( 'users', [] );
		$opts->add( 'blockreason', '' );
		$opts->add( 'blocktalk', false );
		$opts->add( 'blockemail', false );
		$opts->add( 'reblock', false );
		$opts->add( 'usetag', false );
		$opts->add( 'usettag', false );
		$opts->add( 'blocktag', '' );
		$opts->add( 'talktag', '' );
		$opts->fetchValuesFromRequest( $request );

		// If the client has provided a token, they are trying to paginate.
		//  If the token is valid, then use the values from this and later
		//  don't log this as a new check.
		$tokenData = $this->tokenQueryManager->getDataFromRequest( $this->getRequest() );
		$validatedRequest = $this->getRequest();
		$user = '';
		if ( $tokenData ) {
			foreach (
				array_diff( AbstractCheckUserPager::TOKEN_MANAGED_FIELDS, array_keys( $tokenData ) ) as $key
			) {
				$opts->reset( $key );
				$validatedRequest->unsetVal( $key );
			}
			foreach ( $tokenData as $key => $value ) {
				// Update the FormOptions
				if ( $key === 'user' ) {
					$user = $value;
				} else {
					$opts->setValue( $key, $value, true );
				}
				// Update the actual request so that IndexPager.php reads the validated values.
				//  (used for dir, offset and limit)
				$validatedRequest->setVal( $key, $value );
			}
		} else {
			$user = trim(
				$request->getText( 'user', $request->getText( 'ip', $subPage ?? '' ) )
			);
		}
		$this->getContext()->setRequest( $validatedRequest );
		$this->opts = $opts;

		// Normalise 'user' parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		$userTitle = Title::makeTitleSafe( NS_USER, $user );
		$user = $userTitle ? $userTitle->getText() : '';

		$out = $this->getOutput();
		if ( $this->permissionManager->userHasRight( $this->getUser(), 'checkuser-log' ) ) {
			$subtitleLink = Html::rawElement(
				'span',
				[],
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$this->msg( 'checkuser-showlog' )->text()
				)
			);
			if ( $user !== '' ) {
				$subtitleLink .= Html::rawElement(
					'span',
					[],
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'CheckUserLog', $user ),
						$this->msg( 'checkuser-recent-checks' )->text()
					)
				);
			}
			$out->addSubtitle( Html::rawElement(
					'span',
					[ 'class' => 'mw-checkuser-links-no-parentheses' ],
					$subtitleLink
				)
			);
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

		$userIdentity = null;
		$isIP = false;
		$xfor = false;
		$m = [];
		if ( IPUtils::isIPAddress( $user ) ) {
			// A single IP address or an IP range
			$userIdentity = UserIdentityValue::newAnonymous( IPUtils::sanitizeIP( $user ) );
			$isIP = true;
		} elseif ( preg_match( '/^(.+)\/xff$/', $user, $m ) && IPUtils::isIPAddress( $m[1] ) ) {
			// A single IP address or range with XFF string included
			$userIdentity = UserIdentityValue::newAnonymous( IPUtils::sanitizeIP( $m[1] ) );
			$xfor = true;
			$isIP = true;
		} else {
			// A user?
			if ( $user ) {
				$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $user );
			}
		}

		$this->showIntroductoryText();
		$this->showForm( $user, $isIP );

		// Perform one of the various submit operations...
		if ( $request->wasPosted() ) {
			$checkType = $this->opts->getValue( 'checktype' );
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'checkuser-token-fail' );
			} elseif ( $this->opts->getValue( 'action' ) === 'block' ) {
				$this->doMassUserBlock();
			} elseif ( !$this->checkReason() ) {
				$out->addWikiMsg( 'checkuser-noreason' );
			} elseif ( $checkType == self::SUBTYPE_GET_IPS ) {
				if ( $isIP || !$user ) {
					$out->addWikiMsg( 'nouserspecified' );
				} elseif ( !$userIdentity || !$userIdentity->getId() ) {
					$out->addWikiMsg( 'nosuchusershort', $user );
				} else {
					$pager = $this->getPager( self::SUBTYPE_GET_IPS, $userIdentity, 'userips' );
					$out->addHtml( $pager->getBody() );
				}
			} elseif ( $checkType == self::SUBTYPE_GET_EDITS ) {
				if ( $isIP && $userIdentity ) {
					// Target is a IP or range
					if ( !AbstractCheckUserPager::isValidRange( $userIdentity->getName() ) ) {
						$out->addWikiMsg( 'checkuser-range-outside-limit', $userIdentity->getName() );
					} else {
						$logType = $xfor ? 'ipedits-xff' : 'ipedits';

						// Ordered in descent by timestamp. Can cause large filesorts on range scans.
						$pager = $this->getPager( self::SUBTYPE_GET_EDITS, $userIdentity, $logType, $xfor );
						$out->addHTML( $pager->getBody() );
					}
				} else {
					// Target is a username
					if ( !$user ) {
						$out->addWikiMsg( 'nouserspecified' );
					} elseif ( !$userIdentity || !$userIdentity->getId() ) {
						$out->addHTML( $this->msg( 'nosuchusershort', $user )->parseAsBlock() );
					} else {
						// Sorting might take some time
						AtEase::suppressWarnings();
						set_time_limit( 60 );
						AtEase::restoreWarnings();

						$pager = $this->getPager( self::SUBTYPE_GET_EDITS, $userIdentity, 'useredits' );
						$out->addHTML( $pager->getBody() );
					}
				}
			} elseif ( $checkType == self::SUBTYPE_GET_USERS ) {
				if ( !$isIP || !$userIdentity ) {
					$out->addWikiMsg( 'badipaddress' );
				} elseif ( !AbstractCheckUserPager::isValidRange( $userIdentity->getName() ) ) {
					$out->addWikiMsg( 'checkuser-range-outside-limit', $userIdentity->getName() );
				} else {
					$logType = $xfor ? 'ipusers-xff' : 'ipusers';

					$pager = $this->getPager( self::SUBTYPE_GET_USERS, $userIdentity, $logType, $xfor );
					$out->addHTML( $pager->getBody() );
				}
			}
		}
		// Add CIDR calculation convenience JS form
		$this->addJsCIDRForm();
		$out->addModules( 'ext.checkUser' );
		$out->addModuleStyles( [
			'mediawiki.interface.helpers.styles',
			'ext.checkUser.styles',
		] );
	}

	protected function showIntroductoryText() {
		$config = $this->getConfig();
		$cidrLimit = $config->get( 'CheckUserCIDRLimit' );
		$maximumRowCount = $config->get( 'CheckUserMaximumRowCount' );
		$this->getOutput()->addWikiMsg(
			'checkuser-summary',
			$cidrLimit['IPv4'],
			$cidrLimit['IPv6'],
			Message::numParam( $maximumRowCount )
		);
	}

	/**
	 * Show the CheckUser query form
	 *
	 * @param string $user
	 * @param bool $isIP
	 */
	protected function showForm( string $user, bool $isIP ) {
		// Fill in requested type if it makes sense
		$ipAllowed = true;
		$checktype = $this->opts->getValue( 'checktype' );
		if ( $checktype == self::SUBTYPE_GET_USERS && $isIP ) {
			$checkTypeValidated = $checktype;
			$ipAllowed = false;
		} elseif ( $checktype == self::SUBTYPE_GET_IPS && !$isIP ) {
			$checkTypeValidated = $checktype;
		} elseif ( $checktype == self::SUBTYPE_GET_EDITS ) {
			$checkTypeValidated = $checktype;
		// Defaults otherwise
		} elseif ( $isIP ) {
			$checkTypeValidated = self::SUBTYPE_GET_EDITS;
		} else {
			$checkTypeValidated = self::SUBTYPE_GET_IPS;
			$ipAllowed = false;
		}

		$fields = [
			'target' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => $ipAllowed,
				'iprange' => $ipAllowed,
				'name' => 'user',
				'label-message' => 'checkuser-target',
				'default' => $user,
				'id' => 'checktarget',
			],
			'radiooptions' => [
				'type' => 'radio',
				'options-messages' => [
					'checkuser-ips' => self::SUBTYPE_GET_IPS,
					'checkuser-edits' => self::SUBTYPE_GET_EDITS,
					'checkuser-users' => self::SUBTYPE_GET_USERS,
				],
				'id' => 'checkuserradios',
				'default' => $checkTypeValidated,
				'name' => 'checktype',
				'nodata' => 'yes',
				'flatlist' => true,
			],
			'period' => [
				'type' => 'select',
				'id' => 'period',
				'label-message' => 'checkuser-period',
				'options-messages' => [
					'checkuser-week-1' => 7,
					'checkuser-week-2' => 14,
					'checkuser-month' => 30,
					'checkuser-month-2' => 60,
					'checkuser-all' => 0,
				],
				'default' => $this->opts->getValue( 'period' ),
				'name' => 'period',
			],
			'reason' => [
				'type' => 'text',
				'default' => $this->opts->getValue( 'reason' ),
				'label-message' => 'checkuser-reason',
				'size' => 46,
				'maxlength' => 150,
				'id' => 'checkreason',
				'name' => 'reason',
			],
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form->setMethod( 'post' )
			->setWrapperLegendMsg( 'checkuser-query' )
			->setSubmitTextMsg( 'checkuser-check' )
			->setId( 'checkuserform' )
			->setSubmitId( 'checkusersubmit' )
			->setSubmitName( 'checkusersubmit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Make a quick JS form for admins to calculate block ranges
	 */
	protected function addJsCIDRForm() {
		$fields = [
			'iplist' => [
				'type' => 'textarea',
				'dir' => 'ltr',
				'rows' => 5,
				'id' => 'mw-checkuser-iplist',
			],
			'ipresult' => [
				'class' => HTMLTextFieldNoDisabledStyling::class,
				'size' => 35,
				'label-message' => 'checkuser-cidr-res',
				'id' => 'mw-checkuser-cidr-res',
				'name' => 'mw-checkuser-cidr-res',
			],
			'ipnote' => [
				'type' => 'info',
				'id' => 'mw-checkuser-ipnote',
			]
		];
		$fieldset = new HTMLFieldsetCheckUser( $fields, $this->getContext(), '' );
		$s = $fieldset->setWrapperLegendMsg( 'checkuser-cidr-label' )
			->prepareForm()
			->suppressDefaultSubmit( true )
			->getHTML( false );
		$s = Html::rawElement(
			'span',
			[
				'class' => [ 'mw-htmlform', 'mw-htmlform-ooui' ],
				'id' => 'mw-checkuser-cidrform',
				'style' => 'display:none;'
			],
			$s
		);
		$this->getOutput()->addHTML( $s );
	}

	/**
	 * @return bool
	 */
	protected function checkReason(): bool {
		return ( !$this->getConfig()->get( 'CheckUserForceSummary' ) || strlen( $this->opts->getValue( 'reason' ) ) );
	}

	/**
	 * Block a list of selected users
	 * with options provided in the POST request.
	 */
	protected function doMassUserBlock() {
		$users = $this->opts->getValue( 'users' );
		$blockParams = [
			'reason' => $this->opts->getValue( 'blockreason' ),
			'email' => $this->opts->getValue( 'blockemail' ),
			'talk' => $this->opts->getValue( 'blocktalk' ),
			'reblock' => $this->opts->getValue( 'reblock' ),
		];
		$tag = $this->opts->getValue( 'usetag' ) ?
			trim( $this->opts->getValue( 'blocktag' ) ) : '';
		$talkTag = $this->opts->getValue( 'usettag' ) ?
			trim( $this->opts->getValue( 'talktag' ) ) : '';
		$usersCount = count( $users );

		if (
			!$usersCount
			|| !$this->permissionManager->userHasRight( $this->getUser(), 'block' )
			|| $this->getUser()->getBlock()
		) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-failure' );
			return;
		}

		if ( $usersCount > $this->getConfig()->get( 'CheckUserMaxBlocks' ) ) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-limit' );
			return;
		}

		if ( !$blockParams['reason'] ) {
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
		array $users,
		array $blockParams,
		string $tag = '',
		string $talkTag = ''
	) {
		$safeUsers = [];
		foreach ( $users as $name ) {
			$u = $this->userFactory->newFromName( $name, UserRigorOptions::RIGOR_NONE );
			// Do some checks to make sure we can block this user first
			if ( !$u ) {
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
				!isset( $blockParams['email'] ) ||
				$blockParams['email'] === false ||
				$this->blockPermissionCheckerFactory
					->newBlockPermissionChecker(
						$u,
						$this->getUser()
					)
					->checkEmailPermissions()
			) {
				$res = $this->blockUserFactory->newBlockUser(
					$u,
					$this->getAuthority(),
					$isIP ? '1 week' : 'indefinite',
					$blockParams['reason'],
					[
						'isCreateAccountBlocked' => true,
						'isEmailBlocked' => $blockParams['email'] ?? false,
						'isHardBlock' => !$isIP,
						'isAutoblocking' => true,
						'isUserTalkEditBlocked' => $blockParams['talk'] ?? false,
					]
				)->placeBlock( $blockParams['reblock'] );

				if ( $res->isGood() ) {
					$userPage = $u->getUserPage();

					$safeUsers[] = "[[{$userPage->getPrefixedText()}|{$userPage->getText()}]]";

					// Tag user page and user talk page
					if ( $this->opts->getValue( 'usetag' ) ) {
						$this->tagPage( $userPage, $tag, $blockParams['reason'] );
					}
					if ( $this->opts->getValue( 'usettag' ) ) {
						$this->tagPage( $u->getTalkPage(), $talkTag, $blockParams['reason'] );
					}
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
	protected function tagPage( Title $title, string $tag, string $summary ) {
		// Check length to avoid mistakes
		if ( strlen( $tag ) > 2 ) {
			$page = $this->wikiPageFactory->newFromTitle( $title );
			$flags = 0;
			if ( $page->exists() ) {
				$flags |= EDIT_MINOR;
			}
			$page->doUserEditContent(
				new WikitextContent( $tag ),
				$this->getUser(),
				$summary,
				$flags
			);
		}
	}

	/**
	 * Gets the pager for the specific check type.
	 * Returns null if the checktype is not recognised.
	 *
	 * @param string $checkType
	 * @param UserIdentity $userIdentity
	 * @param string $logType
	 * @param bool|null $xfor
	 * @return AbstractCheckUserPager|null
	 */
	public function getPager( string $checkType, UserIdentity $userIdentity, string $logType, ?bool $xfor = null ) {
		switch ( $checkType ) {
			case self::SUBTYPE_GET_IPS:
				return new CheckUserGetIPsPager(
					$this->opts,
					$userIdentity,
					$logType,
					$this->tokenQueryManager,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->loadBalancer,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->actorMigration,
					$this->checkUserLogService,
					$this->userFactory
				);
			case self::SUBTYPE_GET_USERS:
				return new CheckUserGetUsersPager(
					$this->opts,
					$userIdentity,
					$xfor ?? false,
					$logType,
					$this->tokenQueryManager,
					$this->permissionManager,
					$this->blockPermissionCheckerFactory,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->loadBalancer,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->actorMigration,
					$this->userFactory,
					$this->checkUserLogService,
					$this->userEditTracker
				);
			case self::SUBTYPE_GET_EDITS:
				return new CheckUserGetEditsPager(
					$this->opts,
					$userIdentity,
					$xfor,
					$logType,
					$this->tokenQueryManager,
					$this->userGroupManager,
					$this->centralIdLookup,
					$this->linkBatchFactory,
					$this->loadBalancer,
					$this->getSpecialPageFactory(),
					$this->userIdentityLookup,
					$this->actorMigration,
					$this->userFactory,
					$this->revisionStore,
					$this->checkUserLogService,
					$this->commentFormatter,
					$this->userEditTracker
				);
			default:
				return null;
		}
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
		if ( !$this->userNameUtils->isValid( $search ) ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
