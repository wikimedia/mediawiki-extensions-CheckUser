<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use ErrorPageError;
use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use PermissionsError;
use UserBlockedError;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @ingroup SpecialPage
 */
class SpecialGlobalContributions extends ContributionsSpecialPage {

	use ContributionsRangeTrait;

	private GlobalPreferencesFactory $globalPreferencesFactory;
	private GlobalContributionsPagerFactory $pagerFactory;
	private CheckUserPermissionManager $checkUserPermissionManager;

	private ?GlobalContributionsPager $pager = null;

	public function __construct(
		PermissionManager $permissionManager,
		IConnectionProvider $dbProvider,
		NamespaceInfo $namespaceInfo,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserOptionsLookup $userOptionsLookup,
		UserFactory $userFactory,
		UserIdentityLookup $userIdentityLookup,
		DatabaseBlockStore $blockStore,
		GlobalPreferencesFactory $globalPreferencesFactory,
		GlobalContributionsPagerFactory $pagerFactory,
		CheckUserPermissionManager $checkUserPermissionManager
	) {
		parent::__construct(
			$permissionManager,
			$dbProvider,
			$namespaceInfo,
			$userNameUtils,
			$userNamePrefixSearch,
			$userOptionsLookup,
			$userFactory,
			$userIdentityLookup,
			$blockStore,
			'GlobalContributions'
		);
		$this->globalPreferencesFactory = $globalPreferencesFactory;
		$this->pagerFactory = $pagerFactory;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function isIncludable() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getTargetField( $target ) {
		return [
			'type' => 'user',
			'default' => str_replace( '_', ' ', $target ),
			'label' => $this->msg( 'checkuser-global-contributions-target-label' )->text(),
			'name' => 'target',
			'id' => 'mw-target-user-or-ip',
			'size' => 40,
			'autofocus' => $target === '',
			'section' => 'contribs-top',
			'validation-callback' => function ( $target ) {
				if ( !$this->isValidIPOrQueryableRange( $target, $this->getConfig() ) ) {
					return $this->msg( 'checkuser-global-contributions-target-error-no-ip' );
				}
				return true;
			},
			'excludenamed' => true,
			'excludetemp' => true,
			'ipallowed' => true,
			'iprange' => true,
			'iprangelimits' => $this->getQueryableRangeLimit( $this->getConfig() ),
			'required' => true,
		];
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Merely declarative
	 */
	public function isRestricted() {
		return true;
	}

	/** @inheritDoc */
	public function userCanExecute( User $user ) {
		// Implemented so that Special:SpecialPages can hide Special:GlobalContributions if the user does not have the
		// necessary rights, but still show it if the user just hasn't checked the preference or is blocked.
		// The user is denied access for reasons other than rights in ::execute.
		$permissionCheck = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$this->getAuthority()
		);
		return $permissionCheck->getPermission() === null;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// We don't use the CheckUserPermissionManager here, because we would need to check all of
		// these conditions again to know whether the global preference is needed and accepted.
		if (
			!$this->permissionManager->userHasRight(
				$this->getAuthority()->getUser(),
				'checkuser-temporary-account-no-preference'
			)
		) {
			// The user must have the 'checkuser-temporary-account' right.
			if (
				!$this->permissionManager->userHasRight(
					$this->getAuthority()->getUser(),
					'checkuser-temporary-account'
				)
			) {
				throw new PermissionsError( 'checkuser-temporary-account' );
			}

			// The user must also have enabled the global preference.
			$globalPreferences = $this->globalPreferencesFactory->getGlobalPreferencesValues(
				$this->getAuthority()->getUser(),
				// Load from the database, not the cache, since we're using it for access.
				true
			);
			if (
				!$globalPreferences ||
				!isset( $globalPreferences['checkuser-temporary-account-enable'] ) ||
				!$globalPreferences['checkuser-temporary-account-enable']
			) {
				throw new ErrorPageError(
					$this->msg( 'checkuser-global-contributions-permission-error-title' ),
					$this->msg( 'checkuser-global-contributions-permission-error-description' )
				);
			}
		}

		$block = $this->getAuthority()->getBlock();
		if ( $block ) {
			throw new UserBlockedError(
				$block,
				$this->getAuthority()->getUser(),
				$this->getLanguage(),
				$this->getRequest()->getIP()
			);
		}

		parent::execute( $par );

		$target = $this->opts['target'] ?? null;
		if ( $target && !IPUtils::isIPAddress( $target ) ) {
			$this->getOutput()->setSubtitle(
				new MessageWidget( [
					'type' => 'error',
					'label' => new HtmlSnippet(
						$this->msg( 'checkuser-global-contributions-target-error-no-ip-banner', $target )->parse()
					)
				] )
			);
		} elseif ( $target && !$this->isValidIPOrQueryableRange( $target, $this->getConfig() ) ) {
			// Valid range, but outside CIDR limit.
			$limits = $this->getQueryableRangeLimit( $this->getConfig() );
			$limit = $limits[ IPUtils::isIPv4( $target ) ? 'IPv4' : 'IPv6' ];
			$this->getOutput()->addWikiMsg( 'sp-contributions-outofrange', $limit );
		} else {
			$this->getOutput()->addJsConfigVars( 'wgIPRangeTarget', $target );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyFields( &$fields ) {
		$fields['namespace']['include'] = $this->namespaceInfo->getCommonNamespaces();
	}

	/**
	 * @inheritDoc
	 */
	public function getPager( $target ) {
		if ( $this->pager === null ) {
			$options = [
				'namespace' => $this->opts['namespace'],
				'tagfilter' => $this->opts['tagfilter'],
				'start' => $this->opts['start'] ?? '',
				'end' => $this->opts['end'] ?? '',
				'deletedOnly' => $this->opts['deletedOnly'],
				'topOnly' => $this->opts['topOnly'],
				'newOnly' => $this->opts['newOnly'],
				'hideMinor' => $this->opts['hideMinor'],
				'nsInvert' => $this->opts['nsInvert'],
				'associated' => $this->opts['associated'],
				'tagInvert' => $this->opts['tagInvert'],
				'revisionsOnly' => true,
			];

			$this->pager = $this->pagerFactory->createPager(
				$this->getContext(),
				$options,
				$target
			);
		}

		return $this->pager;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'checkuser-global-contributions' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormWrapperLegendMessageKey() {
		return 'checkuser-global-contributions-search-form-wrapper';
	}

	/**
	 * @inheritDoc
	 */
	protected function getResultsPageTitleMessageKey( UserIdentity $target ) {
		return 'checkuser-global-contributions-results-title';
	}

	/** @inheritDoc */
	protected function contributionsSub( $userObj, $targetName ) {
		// Suppress the output of this function if the target isn't an IP or range (ie. is a username)
		// In those cases, parent::contributionsSub will generate an error stating that
		// the user doesn't exist which will be displayed alongside Special:GC's invalid
		// input error and showing the nonexistent username error suggests that a registered
		// username would be valid, as usernames are currently not accepted for lookup. See T377002.
		// In the case of a username input, an error message is expected and none of the text/links
		// generated by this function nor by the parent function are expected to be rendered and
		// therefore it should be safe to simply return early here.
		// TODO: Remove when usernames are supported in T375632.
		if (
			!$this->userNameUtils->isIP( $userObj->getName() ) &&
			!IPUtils::isValidRange( $userObj->getName() )
		) {
			return '';
		}

		$contributionsSub = parent::contributionsSub( $userObj, $targetName );

		// Add subtitle text describing that the data shown is limited to wgCUDMaxAge seconds ago. The count should
		// be in days, as this makes it easier to translate the message.
		$contributionsSub .= $this->msg( 'checkuser-global-contributions-subtitle' )
			->numParams( round( $this->getConfig()->get( 'CUDMaxAge' ) / 86400 ) )
			->parse();

		return $contributionsSub;
	}

	/** @inheritDoc */
	public function shouldShowBlockLogExtract( UserIdentity $target ): bool {
		return parent::shouldShowBlockLogExtract( $target ) &&
			$this->isValidIPOrQueryableRange( $target->getName(), $this->getConfig() );
	}
}
