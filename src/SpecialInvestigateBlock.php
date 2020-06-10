<?php

namespace MediaWiki\CheckUser;

use FormSpecialPage;
use Linker;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use SpecialBlock;
use User;
use Wikimedia\IPUtils;

class SpecialInvestigateBlock extends FormSpecialPage {
	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserFactory */
	private $userFactory;

	/** @var array */
	private $blockedUsers = [];

	public function __construct(
		PermissionManager $permissionManager,
		UserFactory $userFactory
	) {
		parent::__construct( 'InvestigateBlock', 'investigate' );

		$this->permissionManager = $permissionManager;
		$this->userFactory = $userFactory;
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
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function getFormFields() {
		$this->getOutput()->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );
		$this->getOutput()->enableOOUI();

		$prefix = $this->getMessagePrefix();
		$fields = [];

		$fields['Targets'] = [
			'type' => 'usersmultiselect',
			'label-message' => $prefix . '-targets-label',
			'ipallowed' => true,
			'iprange' => true,
			'autofocus' => true,
			'required' => true,
			'exists' => true,
			'input' => [
				'autocomplete' => false,
			],
		];

		if ( SpecialBlock::canBlockEmail( $this->getUser() ) ) {
			$fields['DisableEmail'] = [
				'type' => 'check',
				'label-message' => $prefix . '-email-label',
				'default' => false,
			];
		}

		if ( $this->getConfig()->get( 'BlockAllowsUTEdit' ) ) {
			$fields['DisableUTEdit'] = [
				'type' => 'check',
				'label-message' => $prefix . '-usertalk-label',
				'default' => false,
			];
		}

		$fields['Reblock'] = [
			'type' => 'check',
			'label-message' => $prefix . '-reblock-label',
			'default' => false,
		];

		$fields['Reason'] = [
			'type' => 'text',
			'label-message' => $prefix . '-reason-label',
			'maxlength' => 150,
			'required' => true,
			'autocomplete' => false,
		];

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
		$canBlockEmail = SpecialBlock::canBlockEmail( $this->getUser() );

		foreach ( $targets as $target ) {
			$isIP = IPUtils::isIPAddress( $target );

			if ( !$isIP ) {
				$user = $this->userFactory->newFromName( $target );
				if ( !$user || !$user->getId() ) {
					continue;
				}
			}

			$expiry = $isIP ? '1 week' : 'indefinite';
			$blockEmail = $canBlockEmail ? $data['DisableEmail'] : false;

			$result = SpecialBlock::processForm( [
				'Target' => $target,
				'Reason' => [ $data['Reason'] ],
				'Expiry' => $expiry,
				'HardBlock' => !$isIP,
				'CreateAccount' => true,
				'AutoBlock' => true,
				'DisableEmail' => $blockEmail,
				'DisableUTEdit' => $data['DisableUTEdit'],
				'Reblock' => $data['Reblock'],
				'Confirm' => true,
				'Watch' => false,
			], $this->getContext() );

			if ( $result === true ) {
				$this->blockedUsers[] = $target;
			}
		}

		if ( count( $this->blockedUsers ) === 0 ) {
			return $this->getMessagePrefix() . '-failure';
		}

		return true;
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
		$message = $this->msg( $this->getMessagePrefix() . '-success' )
			->rawParams( $language->listToText( $blockedUsers ) )
			->params( $language->formatNum( count( $blockedUsers ) ) )
			->parseAsBlock();

		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'blockipsuccesssub' ) );
		$out->addHtml( $message );
	}
}
