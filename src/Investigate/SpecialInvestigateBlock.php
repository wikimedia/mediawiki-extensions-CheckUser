<?php

namespace MediaWiki\CheckUser\Investigate;

use ApiMain;
use Exception;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\Linker\Linker;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use PermissionsError;
use Wikimedia\IPUtils;

class SpecialInvestigateBlock extends FormSpecialPage {
	private BlockUserFactory $blockUserFactory;
	private BlockPermissionCheckerFactory $blockPermissionCheckerFactory;
	private PermissionManager $permissionManager;
	private TitleFormatter $titleFormatter;
	private UserFactory $userFactory;
	private EventLogger $eventLogger;

	private array $blockedUsers = [];

	private bool $noticesFailed = false;

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
			// The following message key is generated:
			// * checkuser-investigateblock-target
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
				'label-message' => 'checkuser-investigateblock-email-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		if ( $this->getConfig()->get( 'BlockAllowsUTEdit' ) ) {
			$fields['DisableUTEdit'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-usertalk-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		$fields['Reblock'] = [
			'type' => 'check',
			'label-message' => 'checkuser-investigateblock-reblock-label',
			'default' => false,
			// The following message key is generated:
			// * checkuser-investigateblock-actions
			'section' => 'actions',
		];

		$fields['Reason'] = [
			'type' => 'text',
			'maxlength' => 150,
			'required' => true,
			'autocomplete' => false,
			// The following message key is generated:
			// * checkuser-investigateblock-reason
			'section' => 'reason',
		];

		$pageNoticeClass = 'ext-checkuser-investigate-block-notice';
		$pageNoticePosition = [
			'type' => 'select',
			'cssclass' => $pageNoticeClass,
			'label-message' => 'checkuser-investigateblock-notice-position-label',
			'options-messages' => [
				'checkuser-investigateblock-notice-prepend' => 'prependtext',
				'checkuser-investigateblock-notice-replace' => 'text',
				'checkuser-investigateblock-notice-append' => 'appendtext',
			],
			// The following message key is generated:
			// * checkuser-investigateblock-options
			'section' => 'options',
		];
		$pageNoticeText = [
			'type' => 'text',
			'cssclass' => $pageNoticeClass,
			'label-message' => 'checkuser-investigateblock-notice-text-label',
			'default' => '',
			'section' => 'options',
		];

		$fields['UserPageNotice'] = [
			'type' => 'check',
			'label-message' => 'checkuser-investigateblock-notice-user-page-label',
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
			'label-message' => 'checkuser-investigateblock-notice-talk-page-label',
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
		return $this->msg( 'checkuser-investigateblock' );
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
			return [ 'checkuser-investigateblock-failure' ];
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

		$blockedMessage = $this->msg( 'checkuser-investigateblock-success' )
			->rawParams( $language->listToText( $blockedUsers ) )
			->params( $language->formatNum( count( $blockedUsers ) ) )
			->parseAsBlock();

		$out = $this->getOutput();
		$out->setPageTitleMsg( $this->msg( 'blockipsuccesssub' ) );
		$out->addHtml( $blockedMessage );

		if ( $this->noticesFailed ) {
			$failedNoticesMessage = $this->msg( 'checkuser-investigateblock-notices-failed' );
			$out->addHtml( $failedNoticesMessage );
		}
	}

	/**
	 * InvestigateBlock writes to the DB when the form is submitted.
	 *
	 * @return true
	 */
	public function doesWrites() {
		return true;
	}
}
