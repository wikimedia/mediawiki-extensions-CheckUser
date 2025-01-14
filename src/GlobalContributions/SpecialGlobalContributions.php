<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @ingroup SpecialPage
 */
class SpecialGlobalContributions extends ContributionsSpecialPage {

	use ContributionsRangeTrait;

	private GlobalContributionsPagerFactory $pagerFactory;

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
		GlobalContributionsPagerFactory $pagerFactory
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
		$this->pagerFactory = $pagerFactory;
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
			'placeholder' => $this->msg( 'checkuser-global-contributions-target-placeholder' )->text(),
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
	 */
	public function execute( $par ) {
		$this->requireLogin();

		parent::execute( $par );

		$target = $this->opts['target'] ?? null;

		if ( $target === null ) {
			$message = $this->msg( 'checkuser-global-contributions-summary' )
				->numParams( $this->getMaxAgeForMessage() )
				->parse();
			$this->getOutput()->prependHTML( "<div class='mw-specialpage-summary'>\n$message\n</div>" );
		} elseif ( !IPUtils::isIPAddress( $target ) ) {
			$this->getOutput()->setSubtitle(
				new MessageWidget( [
					'type' => 'error',
					'label' => new HtmlSnippet(
						$this->msg( 'checkuser-global-contributions-target-error-no-ip-banner', $target )->parse()
					)
				] )
			);
		} elseif ( !$this->isValidIPOrQueryableRange( $target, $this->getConfig() ) ) {
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

		// Some of these tags may be disabled on external wikis via `$wgSoftwareTags`
		// but any tag returned here is guaranteed to be consistent on any wiki it
		// is enabled on
		$fields['tagfilter']['useAllTags'] = false;
		$fields['tagfilter']['activeOnly'] = false;
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

	/**
	 * @return float The max age of contributions in days rounded to the nearest whole number
	 */
	private function getMaxAgeForMessage() {
		return round( $this->getConfig()->get( 'CUDMaxAge' ) / 86400 );
	}

	/** @inheritDoc */
	protected function addContributionsSubWarning( $userObj ) {
		// Suppress the output of this function if the target isn't an IP or range (ie. is a username)
		// In those cases, parent::addContributionsSubWarning will generate an error stating that
		// the user doesn't exist which will be displayed alongside Special:GC's invalid
		// input error and showing the nonexistent username error suggests that a registered
		// username would be valid, as usernames are currently not accepted for lookup. See T377002.
		// TODO: Remove when usernames are supported in T375632.
		if (
			!$this->userNameUtils->isIP( $userObj->getName() ) &&
			!IPUtils::isValidRange( $userObj->getName() )
		) {
			return;
		}
	}

	/** @inheritDoc */
	protected function contributionsSub( $userObj, $targetName ) {
		$contributionsSub = parent::contributionsSub( $userObj, $targetName );

		// Add subtitle text describing that the data shown is limited to wgCUDMaxAge seconds ago. The count should
		// be in days, as this makes it easier to translate the message.
		$contributionsSub .= $this->msg( 'checkuser-global-contributions-subtitle' )
			->numParams( $this->getMaxAgeForMessage() )
			->parse();

		return $contributionsSub;
	}

	/** @inheritDoc */
	public function shouldShowBlockLogExtract( UserIdentity $target ): bool {
		return parent::shouldShowBlockLogExtract( $target ) &&
			$this->isValidIPOrQueryableRange( $target->getName(), $this->getConfig() );
	}
}
