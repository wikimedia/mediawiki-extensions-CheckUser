<?php

namespace MediaWiki\CheckUser;

use HTMLForm;

class SpecialInvestigate extends \FormSpecialPage {
	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var PreliminaryCheckPager */
	private $pager;

	/** @var HTMLForm */
	private $form;

	/**
	 * @param PreliminaryCheckService $preliminaryCheckService
	 */
	public function __construct( PreliminaryCheckService $preliminaryCheckService ) {
		parent::__construct( 'Investigate', 'checkuser' );
		$this->preliminaryCheckService = $preliminaryCheckService;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// Override parent in order to display form after submit
		$this->setParameter( $par );
		$this->setHeaders();

		// This will throw exceptions if there's a problem
		$this->checkExecutePermissions( $this->getUser() );

		$securityLevel = $this->getLoginSecurityLevel();
		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			return;
		}

		$this->form = $this->getForm();
		if ( $this->form->show() ) {
			$this->onSuccess();
		}
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
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		$prefix = $this->getMessagePrefix();

		return [
			'Targets' => [
				'type' => 'usersmultiselect',
				'name' => 'targets',
				'label-message' => $prefix . '-targets-label',
				'placeholder' => $this->msg( $prefix . '-targets-placeholder' )->text(),
				'required' => true,
				'max' => 2,
				'exists' => true,
				'ipallowed' => true,
				'iprange' => true,
			],
			'IpSearch' => [
				'type' => 'check',
				'name' => 'ipSearch',
				'label-message' => $prefix . '-ipsearch-label',
				'help-message' => $prefix . '-ipsearch-help',
				'disabled' => true,
			],
			'Reason' => [
				'type' => 'text',
				'name' => 'reason',
				'label-message' => $prefix . '-reason-label',
				'required' => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		// Not done by default in OOUI forms, but done here to match
		// intended design in T237034. See FormSpecialPage::getForm
		$form->setWrapperLegendMsg( $this->getMessagePrefix() . '-legend' );
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->form->displayForm( false );
		$out = $this->getOutput();

		$this->pager = new PreliminaryCheckPager(
			$this->getContext(),
			$this->getLinkRenderer(),
			$this->preliminaryCheckService
		);
		if ( $this->pager->getNumRows() ) {
			$out->addParserOutputContent( $this->pager->getFullOutput() );
		} else {
			$out->addWikiMsg( 'checkuser-investigate-preliminary-table-empty' );
		}

		return \Status::newGood();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
