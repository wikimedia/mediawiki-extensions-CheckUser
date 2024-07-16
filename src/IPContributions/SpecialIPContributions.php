<?php

namespace MediaWiki\CheckUser\IPContributions;

use ErrorPageError;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OOUI\IndexLayout;
use OOUI\TabOptionWidget;
use OOUI\Tag;
use PermissionsError;
use UserBlockedError;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @ingroup SpecialPage
 */
class SpecialIPContributions extends ContributionsSpecialPage {
	private IPContributionsPagerFactory $pagerFactory;
	private ?IPContributionsPager $pager = null;
	private ?IndexLayout $layout = null;

	/**
	 * @param PermissionManager $permissionManager
	 * @param IConnectionProvider $dbProvider
	 * @param NamespaceInfo $namespaceInfo
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UserFactory $userFactory
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param DatabaseBlockStore $blockStore
	 * @param IPContributionsPagerFactory $pagerFactory
	 */
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
		IPContributionsPagerFactory $pagerFactory
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
			'IPContributions'
		);
		$this->pagerFactory = $pagerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function requiresUnblock() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getTargetField( $target ) {
		return [
			'type' => 'user',
			'default' => str_replace( '_', ' ', $target ),
			'label' => $this->msg( 'checkuser-ip-contributions-target-label' )->text(),
			'name' => 'target',
			'id' => 'mw-target-user-or-ip',
			'size' => 40,
			'autofocus' => $target === '',
			'section' => 'contribs-top',
			'validation-callback' => function ( $target ) {
				if ( !IPUtils::isIPAddress( $target ) ) {
					return $this->msg( 'checkuser-ip-contributions-target-error-no-ip' );
				}
				return true;
			},
			'ipallowed' => true,
			'iprange' => true,
			'required' => true,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// These checks are the same as in AbstractTemporaryAccountHandler.
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

			// The user must also have enabled the preference.
			if (
				!$this->userOptionsLookup->getOption(
					$this->getAuthority()->getUser(),
					'checkuser-temporary-account-enable'
				)
			) {
				throw new ErrorPageError(
					$this->msg( 'checkuser-ip-contributions-permission-error-title' ),
					$this->msg( 'checkuser-ip-contributions-permission-error-description' )
				);
			}
		}

		$isArchive = $this->getRequest()->getBool( 'isArchive' );
		$canSeeDeletedHistory = $this->permissionManager->userHasRight(
			$this->getAuthority()->getUser(),
			'deletedhistory'
		);

		if ( $isArchive && !$canSeeDeletedHistory ) {
			throw new PermissionsError( 'deletedhistory' );
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

		// Add to the $opts array now, so that parent::getForm() can add this as a
		// hidden field. This ensures the search form displayed on each tab submits
		// in the correct mode.
		$this->opts['isArchive'] = $isArchive;

		// Tabs are only needed is the user is able to view archived revisions.
		if ( $canSeeDeletedHistory ) {
			$this->addTabs( (string)$par );
		}

		$this->getOutput()->addHTML( $this->getLayout() );
		parent::execute( $par );
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
				'isArchive' => $this->opts['isArchive'],
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
		return $this->msg( 'checkuser-ip-contributions' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormWrapperLegendMessageKey() {
		return 'checkuser-ip-contributions-search-form-wrapper';
	}

	/**
	 * @inheritDoc
	 */
	protected function getResultsPageTitleMessageKey() {
		return 'checkuser-ip-contributions-results-title';
	}

	/**
	 * Returns the OOUI Index Layout and adds the module dependencies for OOUI.
	 *
	 * @return IndexLayout
	 */
	private function getLayout(): IndexLayout {
		if ( $this->layout === null ) {
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addModuleStyles( [
				'oojs-ui-widgets.styles',
			] );

			$this->layout = new IndexLayout( [
				'framed' => false,
				'expanded' => false,
				'classes' => [ 'ext-checkuser-ip-contributions-tabs-indexLayout' ],
			] );
		}

		return $this->layout;
	}

	/**
	 * Add tabs to the layout. Provide the current tab so that tab can be highlighted.
	 *
	 * @param string $par
	 */
	private function addTabs( string $par ) {
		$config = $this->getLayout()->getConfig( $config );

		/* @var TabSelectWidget $tabSelectWidget */
		$tabSelectWidget = $config['tabSelectWidget'];
		$target = $par ?: $this->getRequest()->getVal( 'target' );

		$tabs = array_map( function ( $tab ) use ( $target ) {
			return new TabOptionWidget( [
				'label' => $this->msg( $tab['label'] )->text(),
				'labelElement' => ( new Tag( 'a' ) )->setAttributes( [
					'href' => $this->getPageTitle()->getLocalURL( [
						'isArchive' => $tab['isArchive'],
						'target' => $target
					] ),
				] ),
				'selected' => ( $tab['isArchive'] === $this->opts['isArchive'] ),
			] );
		}, $this->getTabsArray() );

		$tabSelectWidget->addItems( $tabs );
	}

	/**
	 * Get an array that specifies the label and behaviour of each tab
	 *
	 * @return array[] where each entry has the keys:
	 *   - label: A message key for the tab label
	 *   - isArchive: Whether the tab is for fetching archived revisions
	 */
	private function getTabsArray() {
		return [
			[
				'label' => 'checkuser-ip-contributions-tab-label-contributions',
				'isArchive' => false,
			],
			[
				'label' => 'checkuser-ip-contributions-tab-label-archive-contributions',
				'isArchive' => true,
			],
		];
	}

}
