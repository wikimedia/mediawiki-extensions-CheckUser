<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ActorMigration;
use CentralIdLookup;
use Exception;
use ExtensionRegistry;
use Html;
use IContextSource;
use Linker;
use ListToggle;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserUnionSelectQueryBuilderFactory;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\FormOptions;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Xml;

class CheckUserGetUsersPager extends AbstractCheckUserPager {
	/** @var bool */
	protected $canPerformBlocks;

	/** @var array[] */
	protected $userSets;

	/** @var string|false */
	private $centralAuthToollink;

	/** @var array|false */
	private $globalBlockingToollink;

	/** @var string[][] */
	private $aliases;

	/** @var BlockPermissionCheckerFactory */
	private $blockPermissionCheckerFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var CheckUserUtilityService */
	private $checkUserUtilityService;

	/**
	 * @param FormOptions $opts
	 * @param UserIdentity $target
	 * @param bool $xfor
	 * @param string $logType
	 * @param TokenQueryManager $tokenQueryManager
	 * @param PermissionManager $permissionManager
	 * @param BlockPermissionCheckerFactory $blockPermissionCheckerFactory
	 * @param UserGroupManager $userGroupManager
	 * @param CentralIdLookup $centralIdLookup
	 * @param ILoadBalancer $loadBalancer
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param ActorMigration $actorMigration
	 * @param UserFactory $userFactory
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserEditTracker $userEditTracker
	 * @param CheckUserUtilityService $checkUserUtilityService
	 * @param CheckUserUnionSelectQueryBuilderFactory $checkUserUnionSelectQueryBuilderFactory
	 * @param IContextSource|null $context
	 * @param LinkRenderer|null $linkRenderer
	 * @param ?int $limit
	 */
	public function __construct(
		FormOptions $opts,
		UserIdentity $target,
		bool $xfor,
		string $logType,
		TokenQueryManager $tokenQueryManager,
		PermissionManager $permissionManager,
		BlockPermissionCheckerFactory $blockPermissionCheckerFactory,
		UserGroupManager $userGroupManager,
		CentralIdLookup $centralIdLookup,
		ILoadBalancer $loadBalancer,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		ActorMigration $actorMigration,
		UserFactory $userFactory,
		CheckUserLogService $checkUserLogService,
		UserEditTracker $userEditTracker,
		CheckUserUtilityService $checkUserUtilityService,
		CheckUserUnionSelectQueryBuilderFactory $checkUserUnionSelectQueryBuilderFactory,
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $loadBalancer, $specialPageFactory,
			$userIdentityLookup, $actorMigration, $checkUserLogService, $userFactory,
			$checkUserUnionSelectQueryBuilderFactory, $context, $linkRenderer, $limit );
		$this->checkType = SpecialCheckUser::SUBTYPE_GET_USERS;
		$this->xfor = $xfor;
		$this->canPerformBlocks = $permissionManager->userHasRight( $this->getUser(), 'block' )
			&& !$this->getUser()->getBlock();
		$this->centralAuthToollink = ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' )
			? $this->getConfig()->get( 'CheckUserCAtoollink' ) : false;
		$this->globalBlockingToollink = ExtensionRegistry::getInstance()->isLoaded( 'GlobalBlocking' )
			? $this->getConfig()->get( 'CheckUserGBtoollink' ) : false;
		$this->aliases = $this->getLanguage()->getSpecialPageAliases();
		$this->blockPermissionCheckerFactory = $blockPermissionCheckerFactory;
		$this->permissionManager = $permissionManager;
		$this->userEditTracker = $userEditTracker;
		$this->checkUserUtilityService = $checkUserUtilityService;
	}

	/**
	 * Returns nothing as formatUserRow
	 * is instead used.
	 *
	 * @inheritDoc
	 */
	public function formatRow( $row ): string {
		return '';
	}

	/** @inheritDoc */
	public function getBody() {
		$this->getOutput()->addModuleStyles( $this->getModuleStyles() );
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		if ( $this->mResult->numRows() ) {
			# Do any special query batches before display
			$this->doBatchLookups();
		}

		# Don't use any extra rows returned by the query
		$numRows = count( $this->userSets['ids'] );

		$s = $this->getStartBody();
		if ( $numRows ) {
			$keys = array_keys( $this->userSets['ids'] );
			if ( $this->mIsBackwards ) {
				$keys = array_reverse( $keys );
			}
			foreach ( $keys as $user_text ) {
				$s .= $this->formatUserRow( $user_text );
			}
			$s .= $this->getFooter();
		} else {
			$s .= $this->getEmptyBody();
		}
		$s .= $this->getEndBody();
		return $s;
	}

	/**
	 * Gets a row for the results for 'Get users'
	 *
	 * @param string $user_text the username for the current row.
	 * @return string
	 */
	public function formatUserRow( string $user_text ): string {
		$templateParams = [];
		$templateParams['canPerformBlocks'] = $this->canPerformBlocks;
		$templateParams['userText'] = $user_text;
		// Load user object
		$user = new UserIdentityValue( $this->userSets['ids'][$user_text], $user_text );
		$userNonExistent = !IPUtils::isIPAddress( $user ) && !$user->isRegistered();
		if ( $userNonExistent ) {
			$templateParams['userLinkClass'] = 'mw-checkuser-nonexistent-user';
		}
		$templateParams['userLink'] = Linker::userLink( $user->getId(), $user, $user );
		$templateParams['userToolLinks'] = Linker::userToolLinksRedContribs(
			$user->getId(),
			$user,
			$this->userEditTracker->getUserEditCount( $user ),
			// don't render parentheses in HTML markup (CSS will provide)
			false
		);
		$ip = IPUtils::isIPAddress( $user ) ? $user : '';
		if ( $ip ) {
			$templateParams['userLinks'] = $this->msg( 'checkuser-userlinks-ip', $user )->parse();
		} elseif ( !$userNonExistent ) {
			if ( $this->msg( 'checkuser-userlinks' )->exists() ) {
				$templateParams['userLinks'] =
					$this->msg( 'checkuser-userlinks', htmlspecialchars( $user ) )->parse();
			}
		}
		// Add global user tools links
		// Add CentralAuth link for real registered users
		if ( $this->centralAuthToollink !== false
			&& !IPUtils::isIPAddress( $user_text )
			&& !$userNonExistent
		) {
			// Get CentralAuth SpecialPage name in UserLang from the first Alias name
			$spca = $this->aliases['CentralAuth'][0];
			$calinkAlias = str_replace( '_', ' ', $spca );
			$centralCAUrl = WikiMap::getForeignURL(
				$this->centralAuthToollink,
				'Special:CentralAuth'
			);
			if ( $centralCAUrl === false ) {
				throw new Exception(
					"Could not retrieve URL for CentralAuth: $this->centralAuthToollink"
				);
			}
			$linkCA = Html::element( 'a',
				[
					'href' => $centralCAUrl . "/" . $user,
					'title' => $this->msg( 'centralauth' )->text(),
				],
				$calinkAlias
			);
			$templateParams['centralAuthLink'] = $this->msg( 'parentheses' )->rawParams( $linkCA )->escaped();
		}
		// Add GlobalBlocking link to CentralWiki
		if ( $this->globalBlockingToollink !== false
			&& IPUtils::isIPAddress( $user )
		) {
			// Get GlobalBlock SpecialPage name in UserLang from the first Alias name
			$centralGBUrl = WikiMap::getForeignURL(
				$this->globalBlockingToollink['centralDB'],
				'Special:GlobalBlock'
			);
			$spgb = $this->aliases['GlobalBlock'][0];
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
						'href' => $centralGBUrl . "/" . $user,
						'title' => $this->msg( 'globalblocking-block-submit' )->text(),
					],
					$gblinkAlias
				);
			} elseif ( $centralGBUrl !== false ) {
				// Case wikimap configured without CentralAuth extension
				// Get effective Local user groups since there is a wikimap but there is no CA
				$gbUserGroups = $this->userGroupManager->getUserEffectiveGroups( $this->getUser() );
				$linkGB = Html::element( 'a',
					[
						'href' => $centralGBUrl . "/" . $user,
						'title' => $this->msg( 'globalblocking-block-submit' )->text(),
					],
					$gblinkAlias
				);
			} else {
				// Load local user group instead
				$gbUserGroups = [ '' ];
				$gbtitle = $this->getPageTitle( 'GlobalBlock' );
				$linkGB = $this->getLinkRenderer()->makeKnownLink(
					$gbtitle,
					$gblinkAlias,
					[ 'title' => $this->msg( 'globalblocking-block-submit' ) ]
				);
				$gbUserCanDo = $this->permissionManager->userHasRight( $this->getUser(), 'globalblock' );
				if ( $gbUserCanDo ) {
					$this->globalBlockingToollink['groups'] = $gbUserGroups;
				}
			}
			// Only load the script for users in the configured global(local) group(s) or
			// for local user with globalblock permission if there is no WikiMap
			if ( count( array_intersect( $this->globalBlockingToollink['groups'], $gbUserGroups ) ) ) {
				$templateParams['globalBlockLink'] .= $this->msg( 'parentheses' )->rawParams( $linkGB )->escaped();
			}
		}
		// Show edit time range
		$templateParams['timeRange'] = $this->getTimeRangeString(
			$this->userSets['first'][$user_text],
			$this->userSets['last'][$user_text]
		);
		// Total edit count
		$templateParams['editCount'] = $this->userSets['edits'][$user_text];
		// Check if this user or IP is blocked. If so, give a link to the block log...
		$templateParams['flags'] = $this->userBlockFlags( $ip, $user );
		// List out each IP/XFF combo for this username
		$templateParams['infoSets'] = [];
		for ( $i = ( count( $this->userSets['infosets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			// users_infosets[$name][$i] is array of [ $row->ip, XFF ];
			$row = [];
			list( $clientIP, $xffString ) = $this->userSets['infosets'][$user_text][$i];
			// IP link
			$row['ipLink'] = $this->getSelfLink( $clientIP, [ 'user' => $clientIP ] );
			// XFF string, link to /xff search
			if ( $xffString ) {
				// Flag our trusted proxies
				list( $client ) = $this->checkUserUtilityService->getClientIPfromXFF( $xffString );
				// XFF was trusted if client came from it
				$trusted = ( $client === $clientIP );
				$row['xffTrusted'] = $trusted;
				$row['xff'] = $this->getSelfLink( $xffString, [ 'user' => $client . '/xff' ] );
			}
			$templateParams['infoSets'][] = $row;
		}
		// List out each agent for this username
		for ( $i = ( count( $this->userSets['agentsets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			$templateParams['agentsList'][] = $this->userSets['agentsets'][$user_text][$i];
		}
		return $this->templateParser->processTemplate( 'GetUsersLine', $templateParams );
	}

	/**
	 * @param IResultWrapper $result
	 * @return array[]
	 */
	protected function preprocessResults( $result ): array {
		$this->userSets = [
			'first' => [],
			'last' => [],
			'edits' => [],
			'ids' => [],
			'infosets' => [],
			'agentsets' => []
		];

		foreach ( $result as $row ) {
			if ( !array_key_exists( $row->user_text, $this->userSets['edits'] ) ) {
				$this->userSets['last'][$row->user_text] = $row->timestamp;
				$this->userSets['edits'][$row->user_text] = 0;
				$this->userSets['ids'][$row->user_text] = $row->user ?? 0;
				$this->userSets['infosets'][$row->user_text] = [];
				$this->userSets['agentsets'][$row->user_text] = [];
			}
			$this->userSets['edits'][$row->user_text]++;
			$this->userSets['first'][$row->user_text] = $row->timestamp;
			// Treat blank or NULL xffs as empty strings
			$xff = empty( $row->xff ) ? null : $row->xff;
			$xff_ip_combo = [ $row->ip, $xff ];
			// Add this IP/XFF combo for this username if it's not already there
			if ( !in_array( $xff_ip_combo, $this->userSets['infosets'][$row->user_text] ) ) {
				$this->userSets['infosets'][$row->user_text][] = $xff_ip_combo;
			}
			// Add this agent string if it's not already there; 10 max.
			if ( count( $this->userSets['agentsets'][$row->user_text] ) < 10 ) {
				if ( !in_array( $row->agent, $this->userSets['agentsets'][$row->user_text] ) ) {
					$this->userSets['agentsets'][$row->user_text][] = $row->agent;
				}
			}
		}

		return $this->userSets;
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cuc_timestamp',
				'ip' => 'cuc_ip',
				'agent' => 'cuc_agent',
				'xff' => 'cuc_xff',
				'user' => 'actor_cuc_user.actor_user',
				'user_text' => 'actor_cuc_user.actor_name',
				# Needed for IndexPager
				'cuc_timestamp'
			],
			'tables' => [ 'cu_changes', 'actor_cuc_user' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cuc_user' => [ 'JOIN', 'actor_cuc_user.actor_id=cuc_actor' ] ],
			'options' => [ 'USE INDEX' => [
				'cu_changes' => $this->xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time'
			] ],
		];
		$ipConds = self::getIpConds( $this->mDb, $this->target->getName(), $this->xfor );
		if ( $ipConds ) {
			$queryInfo['conds'] = array_merge( $queryInfo['conds'], $ipConds );
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		$s = $this->getCheckUserHelperFieldsetHTML() . $this->getNavigationBar();
		if ( $this->mResult->numRows() ) {
			$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		}
		if ( $this->canPerformBlocks ) {
			$s .= Xml::openElement(
				'form',
				[
					'action' => $this->getPageTitle()->getLocalURL( 'action=block' ),
					'id' => 'checkuserblock',
					'name' => 'checkuserblock',
					'class' => 'mw-htmlform-ooui mw-htmlform',
					'method' => 'post',
				]
			);
		}

		$s .= '<div id="checkuserresults" class="mw-checkuser-get-users-results"><ul>';

		return $s;
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		$fieldset = new HTMLFieldsetCheckUser( [], $this->getContext(), '' );
		$s = '</ul></div>';
		if ( $this->mResult->numRows() ) {
			$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		}
		// T314217 - cannot have forms inside of forms.
		// $s .= $this->getNavigationBar();
		if ( $this->canPerformBlocks ) {
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

			$fields = [
				'usetag' => [
					'type' => 'check',
					'default' => false,
					'label-message' => 'checkuser-blocktag',
					'id' => 'usetag',
					'name' => 'usetag',
					'size' => 46,
				],
				'tag' => [
					'type' => 'text',
					'id' => 'blocktag',
					'name' => 'blocktag',
					'minlength' => 3,
				],
				'talkusetag' => [
					'type' => 'check',
					'default' => false,
					'label-message' => 'checkuser-blocktag-talk',
					'id' => 'usettag',
					'name' => 'usettag',
				],
				'talktag' => [
					'type' => 'text',
					'id' => 'talktag',
					'name' => 'talktag',
					'size' => 46,
					'minlength' => 3,
				],
			];

			$fieldset->addFields( $fields )
				->setWrapperLegendMsg( 'checkuser-massblock' )
				->setSubmitTextMsg( 'checkuser-massblock-commit' )
				->setSubmitId( 'checkuserblocksubmit' )
				->setSubmitName( 'checkuserblock' )
				->setHeaderHtml( $this->msg( 'checkuser-massblock-text' )->text() );

			if ( $config->get( 'BlockAllowsUTEdit' ) ) {
				$fieldset->addFields( [
					'blocktalk' => [
						'type' => 'check',
						'default' => false,
						'label-message' => 'checkuser-blocktalk',
						'id' => 'blocktalk',
						'name' => 'blocktalk',
					]
				] );
			}

			if (
				$this->blockPermissionCheckerFactory
					->newBlockPermissionChecker(
						null,
						$this->getUser()
					)
					->checkEmailPermissions()
			) {
				$fieldset->addFields( [
					'blockemail' => [
						'type' => 'check',
						'default' => false,
						'label-message' => 'checkuser-blockemail',
						'id' => 'blockemail',
						'name' => 'blockemail',
					]
				] );
			}

			$s .= $fieldset
				->addFields( [
					'reblock' => [
						'type' => 'check',
						'default' => false,
						'label-message' => 'checkuser-reblock',
						'id' => 'reblock',
						'name' => 'reblock',
					],
					'reason' => [
						'type' => 'selectandother',
						'options-message' => 'checkuser-block-reason-dropdown',
						'label-message' => 'checkuser-reason',
						'size' => 46,
						'maxlength' => 150,
						'id' => 'blockreason',
						'name' => 'blockreason',
						'cssclass' => 'ext-checkuser-checkuserblock-block-reason'
					],
				] )
				->prepareForm()
				->getHtml( false );
			$s .= '</form>';
		}

		return $s;
	}
}
