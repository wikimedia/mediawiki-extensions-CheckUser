<?php

namespace MediaWiki\CheckUser\Specials;

use HTMLForm;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\LogPager;
use MediaWiki\Permissions\PermissionManager;
use SpecialPage;
use Title;
use User;
use UserBlockedError;
use Wikimedia\IPUtils;
use Xml;

class SpecialCheckUserLog extends SpecialPage {
	/**
	 * @var string
	 */
	protected $target;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var PermissionManager */
	private $permissionManager;

	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		PermissionManager $permissionManager
	) {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->permissionManager = $permissionManager;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->addHelpLink( 'Extension:CheckUser' );
		$this->checkPermissions();

		// Blocked users are not allowed to run checkuser queries (bug T157883)
		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError( $block );
		}

		$out = $this->getOutput();
		$request = $this->getRequest();

		// Normalise target parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		// Do not normalize an empty target, as that means "everything" (T265606)
		$this->target = trim( $request->getVal( 'cuSearch', $par ) );
		if ( $this->target !== '' ) {
			$userTitle = Title::makeTitleSafe( NS_USER, $this->target );
			$this->target = $userTitle ? $userTitle->getText() : '';
		}

		$this->addSubtitle();

		$type = $request->getVal( 'cuSearchType', 'target' );

		$this->displaySearchForm();

		// Default to all log entries - we'll add conditions below if a target was provided
		$searchConds = [];

		if ( $this->target !== '' ) {
			$searchConds = ( $type === 'initiator' )
				? $this->getPerformerSearchConds()
				: $this->getTargetSearchConds();
		}

		if ( $searchConds === null ) {
			// Invalid target was input so show an error message and stop from here
			$out->wrapWikiMsg( "<div class='errorbox'>\n$1\n</div>", 'checkuser-user-nonexistent' );
			return;
		}

		$pager = new LogPager(
			$this->getContext(),
			[
				'queryConds' => $searchConds,
				'year' => $request->getInt( 'year' ),
				'month' => $request->getInt( 'month' ),
			],
			$this->linkBatchFactory
		);

		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * Add subtitle links to the page
	 */
	private function addSubtitle() : void {
		if ( $this->permissionManager->userHasRight( $this->getUser(), 'checkuser' ) ) {
			$links = [
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUser' ),
					$this->msg( 'checkuser-showmain' )->text()
				),
				$this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Investigate' ),
					$this->msg( 'checkuser-show-investigate' )->text()
				),
			];

			if ( $this->target !== '' ) {
				$links[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUser', $this->target ),
					$this->msg( 'checkuser-check-this-user' )->text()
				);

				$links[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Investigate' ),
					$this->msg( 'checkuser-investigate-this-user' )->text(),
					[],
					[ 'targets' => $this->target ]
				);
			}

			$this->getOutput()->addSubtitle( implode( ' | ', $links ) );
		}
	}

	/**
	 * Use an HTMLForm to create and output the search form used on this page.
	 */
	protected function displaySearchForm() {
		$request = $this->getRequest();
		$fields = [
			'target' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuSearch',
				'size' => 40,
				'label-message' => 'checkuser-log-search-target',
				'default' => $this->target,
			],
			'type' => [
				'type' => 'radio',
				'name' => 'cuSearchType',
				'label-message' => 'checkuser-log-search-type',
				'options-messages' => [
					'checkuser-search-target' => 'target',
					'checkuser-search-initiator' => 'initiator',
				],
				'flatlist' => true,
				'default' => 'target',
			],
			// @todo hack until HTMLFormField has a proper date selector
			'monthyear' => [
				'type' => 'info',
				'default' => Xml::dateMenu( $request->getInt( 'year' ), $request->getInt( 'month' ) ),
				'raw' => true,
			],
		];

		$form = HTMLForm::factory( 'table', $fields, $this->getContext() );
		$form->setMethod( 'get' )
			->setWrapperLegendMsg( 'checkuser-search' )
			->setSubmitTextMsg( 'checkuser-search-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Get DB search conditions depending on the CU performer/initiator
	 * Use this only for searches by 'initiator' type
	 *
	 * @return array|null array if valid target, null if invalid
	 */
	protected function getPerformerSearchConds() {
		$initiator = User::newFromName( $this->target );
		if ( $initiator && $initiator->getId() ) {
			return [ 'cul_user' => $initiator->getId() ];
		}
		return null;
	}

	/**
	 * Get DB search conditions according to the CU target given.
	 *
	 * @return array|null array if valid target, null if invalid target given
	 */
	protected function getTargetSearchConds() {
		list( $start, $end ) = IPUtils::parseRange( $this->target );
		$conds = null;

		if ( $start !== false ) {
			$dbr = wfGetDB( DB_REPLICA );
			if ( $start === $end ) {
				// Single IP address
				$conds = [
					'cul_target_hex = ' . $dbr->addQuotes( $start ) . ' OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $start ) . ')'
				];
			} else {
				// IP range
				$conds = [
					'(cul_target_hex >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_target_hex <= ' . $dbr->addQuotes( $end ) . ') OR ' .
					'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
					'cul_range_start <= ' . $dbr->addQuotes( $end ) . ')'
				];
			}
		} else {
			$user = User::newFromName( $this->target );
			if ( $user && $user->getId() ) {
				// Registered user
				$conds = [
					'cul_type' => [ 'userips', 'useredits', 'investigate' ],
					'cul_target_id' => $user->getId(),
				];
			}
		}
		return $conds;
	}

	protected function getGroupName() {
		return 'changes';
	}
}
