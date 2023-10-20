<?php

namespace MediaWiki\CheckUser\CheckUser;

use CommentStore;
use ContribsPager;
use Html;
use HTMLForm;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CheckUser\LogPager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use SpecialPage;
use Title;
use User;
use UserBlockedError;
use Wikimedia\IPUtils;

class SpecialCheckUserLog extends SpecialPage {
	/**
	 * @var string[]|null[] an array of nullable string options.
	 */
	protected $opts;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var CommentStore */
	private $commentStore;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param PermissionManager $permissionManager
	 * @param CommentStore $commentStore
	 * @param UserFactory $userFactory
	 * @param UserNameUtils $userNameUtils
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		PermissionManager $permissionManager,
		CommentStore $commentStore,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->permissionManager = $permissionManager;
		$this->commentStore = $commentStore;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @inheritDoc
	 */
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
		$out->addModules( [ 'ext.checkUser' ] );
		$out->addModuleStyles( [
			'ext.checkUser.styles',
			'mediawiki.interface.helpers.styles'
		] );
		$request = $this->getRequest();

		$this->opts = [];

		// Normalise target parameter and ignore if not valid (T217713)
		// It must be valid when making a link to Special:CheckUserLog/<user>.
		// Do not normalize an empty target, as that means "everything" (T265606)
		$this->opts['target'] = trim( $request->getVal( 'cuSearch', $par ?? '' ) );
		if ( $this->opts['target'] !== '' ) {
			$userTitle = Title::makeTitleSafe( NS_USER, $this->opts['target'] );
			$this->opts['target'] = $userTitle ? $userTitle->getText() : '';
		}

		$this->opts['initiator'] = trim( $request->getVal( 'cuInitiator', '' ) );

		// From SpecialContributions.php
		$skip = $request->getText( 'offset' ) || $request->getText( 'dir' ) === 'prev';
		# Offset overrides year/month selection
		if ( !$skip ) {
			$this->opts['year'] = $request->getIntOrNull( 'year' );
			$this->opts['month'] = $request->getIntOrNull( 'month' );

			$this->opts['start'] = $request->getVal( 'start' );
			$this->opts['end'] = $request->getVal( 'end' );
		}

		$this->opts = ContribsPager::processDateFilter( $this->opts );

		$this->addSubtitle();

		$this->displaySearchForm();

		$errorMessageKey = null;

		if ( $this->opts['target'] !== '' && self::verifyTarget( $this->opts['target'] ) === false ) {
			$errorMessageKey = 'checkuser-target-nonexistent';
		}

		if ( $errorMessageKey !== null ) {
			// Invalid target was input so show an error message and stop from here
			$out->addHTML(
				Html::errorBox(
					$out->msg( $errorMessageKey )->parse()
				)
			);
			return;
		}

		$pager = new LogPager(
			$this->getContext(),
			$this->opts,
			$this->linkBatchFactory,
			$this->commentStore,
			$this->userFactory,
			$this->userNameUtils
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
	private function addSubtitle(): void {
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

			if ( $this->opts['target'] ) {
				$links[] = $this->getLinkRenderer()->makeKnownLink(
					// The above if statement will evaluate NULL to false and thus this
					// only runs if target is a string.
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
					SpecialPage::getTitleFor( 'CheckUser', $this->opts['target'] ),
					$this->msg( 'checkuser-check-this-user' )->text()
				);

				$links[] = $this->getLinkRenderer()->makeKnownLink(
					SpecialPage::getTitleFor( 'Investigate' ),
					$this->msg( 'checkuser-investigate-this-user' )->text(),
					[],
					[ 'targets' => $this->opts['target'] ]
				);
			}

			$this->getOutput()->addSubtitle( Html::rawElement(
					'span',
					[ "class" => "mw-checkuser-links-no-parentheses" ],
					Html::openElement( 'span' ) .
					implode(
						Html::closeElement( 'span' ) . Html::openElement( 'span' ),
						$links
					) .
					Html::closeElement( 'span' )
				)
			);
		}
	}

	/**
	 * Use an HTMLForm to create and output the search form used on this page.
	 */
	protected function displaySearchForm() {
		$fields = [
			'target' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuSearch',
				'size' => 40,
				'label-message' => 'checkuser-log-search-target',
				'default' => $this->opts['target'],
				'id' => 'mw-target-user-or-ip'
			],
			'initiator' => [
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuInitiator',
				'size' => 40,
				'label-message' => 'checkuser-log-search-initiator',
				'default' => $this->opts['initiator']
			],
			'start' => [
				'type' => 'date',
				'default' => '',
				'id' => 'mw-date-start',
				'label' => $this->msg( 'date-range-from' )->text(),
				'name' => 'start'
			],
			'end' => [
				'type' => 'date',
				'default' => '',
				'id' => 'mw-date-end',
				'label' => $this->msg( 'date-range-to' )->text(),
				'name' => 'end'
			]
		];

		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$form->setMethod( 'get' )
			->setWrapperLegendMsg( 'checkuser-search' )
			->setSubmitTextMsg( 'checkuser-search-submit' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Verify if the target is a valid IP, IP range or user.
	 *
	 * If the target is a user then return the user's ID,
	 * if the target is a valid IP address then return
	 * the IP address in hexadecimal and if the target
	 * is a valid IP range return the start and end
	 * hexadecimal for that range. These are used
	 * by LogPager.
	 *
	 * Otherwise return false for an invalid target.
	 *
	 * @param string $target
	 * @return bool|int|array
	 */
	public static function verifyTarget( string $target ) {
		[ $start, $end ] = IPUtils::parseRange( $target );

		if ( $start !== false ) {
			if ( $start === $end ) {
				return [ $start ];
			}

			return [ $start, $end ];
		}

		$user = User::newFromName( $target );
		if ( $user && $user->getId() ) {
			return $user->getId();
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'changes';
	}
}
