<?php

namespace MediaWiki\CheckUser\CheckUserPagers;

use ActorMigration;
use CentralIdLookup;
use Exception;
use ExtensionRegistry;
use FormOptions;
use Html;
use IContextSource;
use Linker;
use ListToggle;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\CheckUser\Hooks as CUHooks;
use MediaWiki\CheckUser\Specials\HTMLFieldsetCheckUser;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;
use Xml;

class CheckUserGetUsersPager extends AbstractCheckUserPager {

	/**
	 * Boolean is $target is a IP / range.
	 *  - False if XFF is not appended
	 *  - True if XFF is appended
	 *
	 * @var bool
	 */
	protected $xfor = null;

	/** @var bool */
	protected $canPerformBlocks;

	/** @var array[] */
	protected $userSets;

	/** @var false|mixed */
	private $centralAuthToollink;

	/** @var false|mixed */
	private $globalBlockingToollink;

	/** @var array|array[] */
	private $aliases;

	/** @var BlockPermissionCheckerFactory */
	private $blockPermissionCheckerFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserFactory */
	private $userFactory;

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
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $loadBalancer, $specialPageFactory,
			$userIdentityLookup, $actorMigration, $context, $linkRenderer, $limit );
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
		$this->userFactory = $userFactory;
	}

	/**
	 * Returns nothing as formatUserRow
	 * is instead used.
	 *
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
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
		$s = '<li>';
		if ( $this->canPerformBlocks ) {
			$s .= Xml::check( 'users[]', false, [ 'value' => $user_text ] ) . '&#160;';
		}
		// Load user object
		$user = $this->userFactory->newFromUserIdentity(
			new UserIdentityValue( $this->userSets['ids'][$user_text], $user_text )
		);
		// Add user page and tool links
		if ( !IPUtils::isIPAddress( $user ) ) {
			$idforlinknfn = -1;
		} else {
			$idforlinknfn = $this->userSets['ids'][$user_text];
		}
		$classnouser = false;
		if ( !IPUtils::isIPAddress( $user ) && !$user->isRegistered() ) {
			// User does not exist
			$idforlink = -1;
			$classnouser = true;
		} else {
			$idforlink = $this->userSets['ids'][$user_text];
		}
		if ( $classnouser ) {
			$s .= '<span class=\'mw-checkuser-nonexistent-user\'>';
		} else {
			$s .= '<span>';
		}
		$s .= Linker::userLink( $idforlinknfn, $user_text, $user_text ) . '</span> ';
		$ip = IPUtils::isIPAddress( $user_text ) ? $user_text : '';
		$s .= Linker::userToolLinksRedContribs(
				$idforlink,
				$user_text,
				$user->getEditCount(),
				// don't render parentheses in HTML markup (CSS will provide)
				false
			) . ' ';
		if ( $ip ) {
			$s .= $this->msg( 'checkuser-userlinks-ip', $user_text )->parse();
		} elseif ( !$classnouser ) {
			if ( $this->msg( 'checkuser-userlinks' )->exists() ) {
				$s .= ' ' . $this->msg( 'checkuser-userlinks', htmlspecialchars( $user_text ) )->parse();
			}
		}
		// Add CheckUser link
		$s .= ' ' . Html::rawElement(
				'span',
				[ 'class' => 'mw-changeslist-links' ],
				$this->getSelfLink(
					$this->msg( 'checkuser-check' )->text(),
					[
						'user' => $user_text,
						'reason' => $this->opts->getValue( 'reason' )
					]
				)
			);
		// Add global user tools links
		// Add CentralAuth link for real registered users
		if ( $this->centralAuthToollink !== false
			&& !IPUtils::isIPAddress( $user_text )
			&& !$classnouser
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
					"Could not retrieve URL for CentralAuth: {$this->centralAuthToollink}"
				);
			}
			$linkCA = Html::element( 'a',
				[
					'href' => $centralCAUrl . "/" . $user_text,
					'title' => $this->msg( 'centralauth' )->text(),
				],
				$calinkAlias
			);
			$s .= ' ' . $this->msg( 'parentheses' )->rawParams( $linkCA )->escaped();
		}
		// Add Globalblocking link to CentralWiki
		if ( $this->globalBlockingToollink !== false
			&& IPUtils::isIPAddress( $user_text )
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
						'href' => $centralGBUrl . "/" . $user_text,
						'title' => $this->msg( 'globalblocking-block-submit' )->text(),
					],
					$gblinkAlias
				);
			} elseif ( $centralGBUrl !== false ) {
				// Case wikimap configured without CentralAuth extension
				$user = $this->getUser();
				// Get effective Local user groups since there is a wikimap but there is no CA
				$gbUserGroups = $this->userGroupManager->getUserEffectiveGroups( $user );
				$linkGB = Html::element( 'a',
					[
						'href' => $centralGBUrl . "/" . $user_text,
						'title' => $this->msg( 'globalblocking-block-submit' )->text(),
					],
					$gblinkAlias
				);
			} else {
				// Load local user group instead
				$gbUserGroups = [ '' ];
				$user = $this->getUser();
				$gbtitle = self::getPageTitle( 'GlobalBlock' );
				$linkGB = $this->getLinkRenderer()->makeKnownLink(
					$gbtitle,
					$gblinkAlias,
					[ 'title' => $this->msg( 'globalblocking-block-submit' ) ]
				);
				$gbUserCanDo = $this->permissionManager->userHasRight( $user, 'globalblock' );
				if ( $gbUserCanDo ) {
					$this->globalBlockingToollink['groups'] = $gbUserGroups;
				}
			}
			// Only load the script for users in the configured global(local) group(s) or
			// for local user with globalblock permission if there is no WikiMap
			if ( count( array_intersect( $this->globalBlockingToollink['groups'], $gbUserGroups ) ) ) {
				$s .= ' ' . $this->msg( 'parentheses' )->rawParams( $linkGB )->escaped();
			}
		}
		// Show edit time range
		$s .= ' ' . $this->getTimeRangeString(
				$this->userSets['first'][$user_text],
				$this->userSets['last'][$user_text]
			) . ' ';
		// Total edit count
		$s .= Html::rawElement(
				'strong',
				[ 'class' => 'mw-changeslist-links' ],
				htmlspecialchars( $this->userSets['edits'][$user_text] )
			) . '<br />';
		// Check if this user or IP is blocked. If so, give a link to the block log...
		$flags = $this->userBlockFlags( $ip, $this->userSets['ids'][$user_text], $user );
		$s .= implode( ' ', $flags );
		$s .= '<ol>';
		// List out each IP/XFF combo for this username
		for ( $i = ( count( $this->userSets['infosets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			// users_infosets[$name][$i] is array of [ $row->cuc_ip, XFF ];
			list( $clientIP, $xffString ) = $this->userSets['infosets'][$user_text][$i];
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
		for ( $i = ( count( $this->userSets['agentsets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			$agent = $this->userSets['agentsets'][$user_text][$i];
			$s .= '<li><i dir="ltr">' . htmlspecialchars( $agent ) . "</i></li>\n";
		}
		$s .= '</ol>';
		$s .= '</li>';
		return $s;
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
			if ( !array_key_exists( $row->cuc_user_text, $this->userSets['edits'] ) ) {
				$this->userSets['last'][$row->cuc_user_text] = $row->cuc_timestamp;
				$this->userSets['edits'][$row->cuc_user_text] = 0;
				$this->userSets['ids'][$row->cuc_user_text] = $row->cuc_user;
				$this->userSets['infosets'][$row->cuc_user_text] = [];
				$this->userSets['agentsets'][$row->cuc_user_text] = [];
			}
			$this->userSets['edits'][$row->cuc_user_text] += 1;
			$this->userSets['first'][$row->cuc_user_text] = $row->cuc_timestamp;
			// Treat blank or NULL xffs as empty strings
			$xff = empty( $row->cuc_xff ) ? null : $row->cuc_xff;
			$xff_ip_combo = [ $row->cuc_ip, $xff ];
			// Add this IP/XFF combo for this username if it's not already there
			if ( !in_array( $xff_ip_combo, $this->userSets['infosets'][$row->cuc_user_text] ) ) {
				$this->userSets['infosets'][$row->cuc_user_text][] = $xff_ip_combo;
			}
			// Add this agent string if it's not already there; 10 max.
			if ( count( $this->userSets['agentsets'][$row->cuc_user_text] ) < 10 ) {
				if ( !in_array( $row->cuc_agent, $this->userSets['agentsets'][$row->cuc_user_text] ) ) {
					$this->userSets['agentsets'][$row->cuc_user_text][] = $row->cuc_agent;
				}
			}
		}

		return $this->userSets;
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$queryInfo = [
			'fields' => [
				'cuc_user_text', 'cuc_timestamp', 'cuc_user', 'cuc_ip', 'cuc_agent', 'cuc_xff',
			],
			'tables' => [ 'cu_changes' ],
			'conds' => [],
			'options' => [ 'USE INDEX' => $this->xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time' ],
		];
		$ipConds = self::getIpConds( $this->mDb, $this->target->getName(), $this->xfor );
		if ( $ipConds ) {
			$queryInfo['conds'] = array_merge( $queryInfo['conds'], $ipConds );
		} else {
			$this->skipQuery = true;
			return $queryInfo;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	public function getIndexField() {
		return 'cuc_timestamp';
	}

	/** @inheritDoc */
	protected function getStartBody(): string {
		$s = $this->getNavigationBar();
		$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
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

		$s .= '<div id="checkuserresults"><ul>';

		return $s;
	}

	/** @inheritDoc */
	protected function getEndBody(): string {
		$fieldset = new HTMLFieldsetCheckUser( [], $this->getContext(), '' );
		$s = '</ul></div>';
		$s .= ( new ListToggle( $this->getOutput() ) )->getHTML();
		$s .= $this->getNavigationBar();
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
						'type' => 'text',
						'label-message' => 'checkuser-reason',
						'size' => 46,
						'maxlength' => 150,
						'id' => 'blockreason',
						'name' => 'blockreason',
					],
				] )
				->prepareForm()
				->getHtml( false );
			$s .= '</form>';
		}

		return $s;
	}

	/** @inheritDoc */
	protected function getEmptyBody(): string {
		return $this->noMatchesMessage( $this->target->getName(), !$this->xfor ) . "\n";
	}
}
