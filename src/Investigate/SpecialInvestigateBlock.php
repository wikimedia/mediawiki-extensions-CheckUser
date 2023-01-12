<?php

namespace MediaWiki\CheckUser\Investigate;

use ApiMain;
use DerivativeRequest;
use Exception;
use FormSpecialPage;
use Linker;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use PermissionsError;
use TitleFormatter;
use TitleValue;
use User;
use Wikimedia\IPUtils;

class SpecialInvestigateBlock extends FormSpecialPage {
	/** @var BlockUserFactory */
	private $blockUserFactory;

	/** @var BlockPermissionCheckerFactory */
	private $blockPermissionCheckerFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var UserFactory */
	private $userFactory;

	/** @var EventLogger */
	private $eventLogger;

	/** @var array */
	private $blockedUsers = [];

	/** @var bool */
	private $noticesFailed = false;

	/**
	 * @param BlockUserFactory $blockUserFactory
	 * @param BlockPermissionCheckerFactory $blockPermissionCheckerFactory
	 * @param PermissionManager $permissionManager
	 * @param TitleFormatter $titleFormatter
	 * @param UserFactory $userFactory
	 * @param EventLogger $eventLogger
	 */
	public function __construct(
		BlockUserFactory $blockUserFactory,
		BlockPermissionCheckerFactory $blockPermissionCheckerFactory,
		PermissionManager $permissionManager,
		TitleFormatter $titleFormatter,
		UserFactory $userFactory,
		EventLogger $eventLogger
	) {
		parent::__construct( 'InvestigateBlock', 'checkuser' );

		$this->blockUserFactory = $blockUserFactory;
		$this->blockPermissionCheckerFactory = $blockPermissionCheckerFactory;
		$this->permissionManager = $permissionManager;
		$this->titleFormatter = $titleFormatter;
		$this->userFactory = $userFactory;
		$this->eventLogger = $eventLogger;
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		return parent::userCanExecute( $user ) &&
			$this->permissionManager->userHasRight( $user, 'block' );
	}

	/**
	 * @inheritDoc
	 */
	public function checkPermissions() {
		$user = $this->getUser();
		if ( !parent::userCanExecute( $user ) ) {
			$this->displayRestrictionError();
		}

		// User is a checkuser, but now to check for if they can block.
		if ( !$this->permissionManager->userHasRight( $user, 'block' ) ) {
			throw new PermissionsError( 'block' );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function getFormFields() {
		$this->getOutput()->addModules( [
			'ext.checkUser'
		] );
		$this->getOutput()->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.checkUser.styles',
		] );
		$this->getOutput()->enableOOUI();

		$prefix = $this->getMessagePrefix();
		$fields = [];

		$fields['Targets'] = [
			'type' => 'usersmultiselect',
			'ipallowed' => true,
			'iprange' => true,
			'autofocus' => true,
			'required' => true,
			'exists' => true,
			'input' => [
				'autocomplete' => false,
			],
			'section' => 'target',
			'default' => '',
		];

		if (
			$this->blockPermissionCheckerFactory
				->newBlockPermissionChecker( null, $this->getUser() )
				->checkEmailPermissions()
		) {
			$fields['DisableEmail'] = [
				'type' => 'check',
				'label-message' => $prefix . '-email-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		if ( $this->getConfig()->get( 'BlockAllowsUTEdit' ) ) {
			$fields['DisableUTEdit'] = [
				'type' => 'check',
				'label-message' => $prefix . '-usertalk-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		$fields['Reblock'] = [
			'type' => 'check',
			'label-message' => $prefix . '-reblock-label',
			'default' => false,
			'section' => 'actions',
		];

		$fields['Reason'] = [
			'type' => 'text',
			'maxlength' => 150,
			'required' => true,
			'autocomplete' => false,
			'section' => 'reason',
		];

		$pageNoticeClass = 'ext-checkuser-investigate-block-notice';
		$pageNoticePosition = [
			'type' => 'select',
			'cssclass' => $pageNoticeClass,
			'label-message' => $prefix . '-notice-position-label',
			'options-messages' => [
				$prefix . '-notice-prepend' => 'prependtext',
				$prefix . '-notice-replace' => 'text',
				$prefix . '-notice-append' => 'appendtext',
			],
			'section' => 'options',
		];
		$pageNoticeText = [
			'type' => 'text',
			'cssclass' => $pageNoticeClass,
			'label-message' => $prefix . '-notice-text-label',
			'default' => '',
			'section' => 'options',
		];

		$fields['UserPageNotice'] = [
			'type' => 'check',
			'label-message' => $prefix . '-notice-user-page-label',
			'default' => false,
			'section' => 'options',
		];
		$fields['UserPageNoticePosition'] = array_merge(
			$pageNoticePosition,
			[ 'default' => 'prependtext' ]
		);
		$fields['UserPageNoticeText'] = $pageNoticeText;

		$fields['TalkPageNotice'] = [
			'type' => 'check',
			'label-message' => $prefix . '-notice-talk-page-label',
			'default' => false,
			'section' => 'options',
		];
		$fields['TalkPageNoticePosition'] = array_merge(
			$pageNoticePosition,
			[ 'default' => 'appendtext' ]
		);
		$fields['TalkPageNoticeText'] = $pageNoticeText;

		return $fields;
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( $this->getMessagePrefix() )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessagePrefix() {
		return 'checkuser-' . strtolower( $this->getName() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->blockedUsers = [];
		$targets = explode( "\n", $data['Targets'] );

		foreach ( $targets as $target ) {
			$isIP = IPUtils::isIPAddress( $target );

			if ( !$isIP ) {
				$user = $this->userFactory->newFromName( $target );
				if ( !$user || !$user->getId() ) {
					continue;
				}
			}

			$expiry = $isIP ? '1 week' : 'indefinite';

			$status = $this->blockUserFactory->newBlockUser(
				$target,
				$this->getUser(),
				$expiry,
				$data['Reason'],
				[
					'isHardBlock' => !$isIP,
					'isCreateAccountBlocked' => true,
					'isAutoblocking' => true,
					'isEmailBlocked' => $data['DisableEmail'] ?? false,
					'isUserTalkEditBlocked' => $data['DisableUTEdit'] ?? false,
				]
			)->placeBlock( $data['Reblock'] );

			if ( $status->isOK() ) {
				$this->blockedUsers[] = $target;

				if ( $data['UserPageNotice'] ) {
					$this->addNoticeToPage(
						$this->getTargetPage( NS_USER, $target ),
						$data['UserPageNoticeText'],
						$data['UserPageNoticePosition'],
						$data['Reason']
					);
				}

				if ( $data['TalkPageNotice'] ) {
					$this->addNoticeToPage(
						$this->getTargetPage( NS_USER_TALK, $target ),
						$data['TalkPageNoticeText'],
						$data['TalkPageNoticePosition'],
						$data['Reason']
					);
				}
			}
		}

		$blockedUsersCount = count( $this->blockedUsers );

		$this->eventLogger->logEvent( [
			'action' => 'block',
			'targetsCount' => count( $targets ),
			'relevantTargetsCount' => $blockedUsersCount,
		] );

		if ( $blockedUsersCount === 0 ) {
			// Message used here:
			// checkuser-investigateblock-failure
			return [ $this->getMessagePrefix() . '-failure' ];
		}

		return true;
	}

	/**
	 * @param int $namespace
	 * @param string $target Must be a valid IP address or a valid user name
	 * @return string
	 */
	private function getTargetPage( int $namespace, string $target ): string {
		if ( IPUtils::isValidRange( $target ) ) {
			$target = IPUtils::sanitizeRange( $target );
		}

		return $this->titleFormatter->getPrefixedText(
			new TitleValue( $namespace, $target )
		);
	}

	/**
	 * Add a notice to a given page. The notice may be prepended or appended,
	 * or it may replace the page.
	 *
	 * @param string $title Page to which to add the notice
	 * @param string $notice The notice, as wikitext
	 * @param string $position One of 'prependtext', 'appendtext' or 'text'
	 * @param string $summary Edit summary
	 */
	private function addNoticeToPage(
		string $title,
		string $notice,
		string $position,
		string $summary
	): void {
		$apiParams = [
			'action' => 'edit',
			'title' => $title,
			$position => $notice,
			'summary' => $summary,
			'token' => $this->getContext()->getCsrfTokenSet()->getToken(),
		];

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams,
				// was posted
				true
			),
			// enable write
			true
		);

		try {
			$api->execute();
		} catch ( Exception $e ) {
			$this->noticesFailed = true;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$blockedUsers = array_map( function ( $userName ) {
			$user = $this->userFactory->newFromName(
				$userName,
				UserNameUtils::RIGOR_NONE
			);
			return Linker::userLink( $user->getId(), $userName );
		}, $this->blockedUsers );

		$language = $this->getLanguage();
		$prefix = $this->getMessagePrefix();

		$blockedMessage = $this->msg( $prefix . '-success' )
			->rawParams( $language->listToText( $blockedUsers ) )
			->params( $language->formatNum( count( $blockedUsers ) ) )
			->parseAsBlock();

		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'blockipsuccesssub' ) );
		$out->addHtml( $blockedMessage );

		if ( $this->noticesFailed ) {
			$failedNoticesMessage = $this->msg( $prefix . '-notices-failed' );
			$out->addHtml( $failedNoticesMessage );
		}
	}
}
