<?php

class SpecialInvestigate extends \FormSpecialPage {
	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct( 'Investigate', 'checkuser' );
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
				'label-message' => $prefix . '-ipsearch-label',
				'help-message' => $prefix . '-ipsearch-help',
				'disabled' => true,
			],
			'Reason' => [
				'type' => 'text',
				'label-message' => $prefix . '-reason-label',
				'placeholder' => $this->msg( $prefix . '-reason-placeholder' )->text(),
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
		return \Status::newGood();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
