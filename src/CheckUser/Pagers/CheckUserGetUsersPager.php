<?php

namespace MediaWiki\CheckUser\CheckUser\Pagers;

use ExtensionRegistry;
use IContextSource;
use LogicException;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\CheckUser\Widgets\HTMLFieldsetCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\CheckUser\Services\CheckUserUtilityService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Html\ListToggle;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Xml;

class CheckUserGetUsersPager extends AbstractCheckUserPager {
	/** @var bool Whether the user performing this check has the block right. */
	protected bool $canPerformBlocks;

	/** @var array[] */
	protected $userSets;

	/** @var string|false */
	private $centralAuthToollink;

	/** @var array|false */
	private $globalBlockingToollink;

	/** @var string[][] */
	private $aliases;

	private ClientHintsLookupResults $clientHintsLookupResults;

	private BlockPermissionCheckerFactory $blockPermissionCheckerFactory;
	private PermissionManager $permissionManager;
	private UserEditTracker $userEditTracker;
	private CheckUserUtilityService $checkUserUtilityService;
	private UserAgentClientHintsLookup $clientHintsLookup;
	private UserAgentClientHintsFormatter $clientHintsFormatter;

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
	 * @param IConnectionProvider $dbProvider
	 * @param SpecialPageFactory $specialPageFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserFactory $userFactory
	 * @param CheckUserLogService $checkUserLogService
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 * @param UserEditTracker $userEditTracker
	 * @param CheckUserUtilityService $checkUserUtilityService
	 * @param UserAgentClientHintsLookup $clientHintsLookup
	 * @param UserAgentClientHintsFormatter $clientHintsFormatter
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
		IConnectionProvider $dbProvider,
		SpecialPageFactory $specialPageFactory,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		CheckUserLogService $checkUserLogService,
		CheckUserLookupUtils $checkUserLookupUtils,
		UserEditTracker $userEditTracker,
		CheckUserUtilityService $checkUserUtilityService,
		UserAgentClientHintsLookup $clientHintsLookup,
		UserAgentClientHintsFormatter $clientHintsFormatter,
		IContextSource $context = null,
		LinkRenderer $linkRenderer = null,
		?int $limit = null
	) {
		parent::__construct( $opts, $target, $logType, $tokenQueryManager,
			$userGroupManager, $centralIdLookup, $dbProvider, $specialPageFactory,
			$userIdentityLookup, $checkUserLogService, $userFactory, $checkUserLookupUtils,
			$context, $linkRenderer, $limit );
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
		$this->clientHintsLookup = $clientHintsLookup;
		$this->clientHintsFormatter = $clientHintsFormatter;
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

		$userIsIP = IPUtils::isIPAddress( $user_text );
		$formattedUserText = $userIsIP ? IPUtils::prettifyIP( $user_text ) ?? $user_text : $user_text;

		$templateParams['userText'] = $formattedUserText;
		// Load user object
		$user = new UserIdentityValue( $this->userSets['ids'][$user_text], $formattedUserText );
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
		if ( $userIsIP ) {
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
			&& !$userIsIP
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
				throw new ConfigException(
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
					throw new ConfigException(
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
		$templateParams['flags'] = $this->userBlockFlags( $userIsIP ? $user : '', $user );
		// List out each IP/XFF combo for this username
		$templateParams['infoSets'] = [];
		for ( $i = ( count( $this->userSets['infosets'][$user_text] ) - 1 ); $i >= 0; $i-- ) {
			// users_infosets[$name][$i] is array of [ $row->ip, XFF ];
			$row = [];
			[ $clientIP, $xffString ] = $this->userSets['infosets'][$user_text][$i];
			// IP link
			$row['ipLink'] = $this->getSelfLink( $clientIP, [ 'user' => $clientIP ] );
			// XFF string, link to /xff search
			if ( $xffString ) {
				// Flag our trusted proxies
				[ $client ] = $this->checkUserUtilityService->getClientIPfromXFF( $xffString );
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
		// Show Client Hints data if display is enabled.
		$templateParams['displayClientHints'] = $this->displayClientHints;
		if ( $this->displayClientHints ) {
			$templateParams['clientHintsList'] = [];
			[ $usagesOfClientHints, $clientHintsDataObjects ] = $this->clientHintsLookupResults
				->getGroupedClientHintsDataForReferenceIds( $this->userSets['clienthints'][$user_text] );
			// Sort the $usagesOfClientHints array such that the ClientHintsData object that is most used
			// by the user referenced in $user_text is shown first and the ClientHintsData object least used is
			// shown last. This is done to be consistent with the way that User-Agent strings are shown as well
			// as ensuring that if there are more than 10 items the ClientHintsData objects used on the most reference
			// IDs are shown.
			arsort( $usagesOfClientHints, SORT_NUMERIC );
			// Limit the number displayed to at most 10 starting at the
			// ClientHintsData object associated with the most rows
			// in the results. This is to be consistent with User-Agent
			// strings which are also limited to 10 strings.
			$i = 0;
			foreach ( array_keys( $usagesOfClientHints ) as $clientHintsDataIndex ) {
				// If 10 Client Hints data objects have been displayed,
				// then don't show any more (similar to User-Agent strings).
				if ( $i === 10 ) {
					break;
				}
				$clientHintsDataObject = $clientHintsDataObjects[$clientHintsDataIndex];
				if ( $clientHintsDataObject ) {
					$formattedClientHintsData = $this->clientHintsFormatter
						->formatClientHintsDataObject( $clientHintsDataObject );
					if ( $formattedClientHintsData ) {
						// If the Client Hints data object is valid and evaluates to a non-empty
						// human readable string, then add it to the list to display.
						$i++;
						$templateParams['clientHintsList'][] = $formattedClientHintsData;
					}
				}
			}
		}
		return $this->templateParser->processTemplate( 'GetUsersLine', $templateParams );
	}

	/** @inheritDoc */
	protected function preprocessResults( $result ) {
		$this->userSets = [
			'first' => [],
			'last' => [],
			'edits' => [],
			'ids' => [],
			'infosets' => [],
			'agentsets' => [],
			'clienthints' => [],
		];
		$referenceIdsForLookup = new ClientHintsReferenceIds();

		foreach ( $result as $row ) {
			// Use the IP as the user_text if the actor ID is NULL and the IP is not NULL (T353953).
			if ( $row->actor === null && $row->ip ) {
				$row->user_text = $row->ip;
			}

			if ( !array_key_exists( $row->user_text, $this->userSets['edits'] ) ) {
				$this->userSets['last'][$row->user_text] = $row->timestamp;
				$this->userSets['edits'][$row->user_text] = 0;
				$this->userSets['ids'][$row->user_text] = $row->user ?? 0;
				$this->userSets['infosets'][$row->user_text] = [];
				$this->userSets['agentsets'][$row->user_text] = [];
				$this->userSets['clienthints'][$row->user_text] = new ClientHintsReferenceIds();
			}
			if ( $this->displayClientHints ) {
				$referenceIdsForLookup->addReferenceIds(
					$row->client_hints_reference_id,
					$row->client_hints_reference_type
				);
				$this->userSets['clienthints'][$row->user_text]->addReferenceIds(
					$row->client_hints_reference_id,
					$row->client_hints_reference_type
				);
			}
			$this->userSets['edits'][$row->user_text]++;
			$this->userSets['first'][$row->user_text] = $row->timestamp;
			// Prettify IP
			$formattedIP = IPUtils::prettifyIP( $row->ip ) ?? $row->ip;
			// Treat blank or NULL xffs as empty strings
			$xff = empty( $row->xff ) ? null : $row->xff;
			$xff_ip_combo = [ $formattedIP, $xff ];
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

		// Lookup the Client Hints data objects from the DB
		// and then batch format the ClientHintsData objects
		// for display.
		if ( $this->displayClientHints ) {
			$this->clientHintsLookupResults = $this->clientHintsLookup
				->getClientHintsByReferenceIds( $referenceIdsForLookup );
		}
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

		// Apply index and IP WHERE conditions.
		$queryInfo['options']['USE INDEX'] = [
			$table => $this->checkUserLookupUtils->getIndexName( $this->xfor, $table )
		];
		$ipConds = self::getIpConds( $this->mDb, $this->target->getName(), $this->xfor, $table );
		if ( $ipConds ) {
			$queryInfo['conds'] = array_merge( $queryInfo['conds'], $ipConds );
		}

		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuChanges(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cuc_timestamp',
				'ip' => 'cuc_ip',
				'agent' => 'cuc_agent',
				'xff' => 'cuc_xff',
				'actor' => 'cuc_actor',
				'user' => 'actor_cuc_actor.actor_user',
				'user_text' => 'actor_cuc_actor.actor_name',
			],
			'tables' => [ 'cu_changes', 'actor_cuc_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cuc_actor' => [ 'JOIN', 'actor_cuc_actor.actor_id=cuc_actor' ] ],
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
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuLogEvent(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cule_timestamp',
				'ip' => 'cule_ip',
				'agent' => 'cule_agent',
				'xff' => 'cule_xff',
				'actor' => 'cule_actor',
				'user' => 'actor_cule_actor.actor_user',
				'user_text' => 'actor_cule_actor.actor_name',
			],
			'tables' => [ 'cu_log_event', 'actor_cule_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cule_actor' => [ 'JOIN', 'actor_cule_actor.actor_id=cule_actor' ] ],
			'options' => [],
		];
		// When displaying Client Hints data, add the reference type and reference ID to each row.
		if ( $this->displayClientHints ) {
			$queryInfo['fields']['client_hints_reference_id'] =
				UserAgentClientHintsManager::IDENTIFIER_TO_COLUMN_NAME_MAP[
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT
				];
			$queryInfo['fields']['client_hints_reference_type'] =
				UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT;
		}
		return $queryInfo;
	}

	/** @inheritDoc */
	protected function getQueryInfoForCuPrivateEvent(): array {
		$queryInfo = [
			'fields' => [
				'timestamp' => 'cupe_timestamp',
				'ip' => 'cupe_ip',
				'agent' => 'cupe_agent',
				'xff' => 'cupe_xff',
				'actor' => 'cupe_actor',
				'user' => 'actor_cupe_actor.actor_user',
				'user_text' => 'actor_cupe_actor.actor_name',
			],
			'tables' => [ 'cu_private_event', 'actor_cupe_actor' => 'actor' ],
			'conds' => [],
			'join_conds' => [ 'actor_cupe_actor' => [ 'LEFT JOIN', 'actor_cupe_actor.actor_id=cupe_actor' ] ],
			'options' => [],
		];
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

		$divClasses = [ 'mw-checkuser-get-users-results' ];

		if ( $this->displayClientHints ) {
			// Class used to indicate whether Client Hints are enabled
			// TODO: Remove this class and old CSS code once display
			// is on all wikis (T341110).
			$divClasses[] = 'mw-checkuser-clienthints-enabled-temporary-class';
		}

		$s .= Xml::openElement(
			'div',
			[
				'id' => 'checkuserresults',
				'class' => implode( ' ', $divClasses )
			]
		);

		$s .= '<ul>';

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
					throw new ConfigException( '$wgCheckUserCAMultiLock requires CentralAuth extension.' );
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
						throw new ConfigException(
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
				->setHeaderHtml( $this->msg( 'checkuser-massblock-text' )->escaped() );

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
