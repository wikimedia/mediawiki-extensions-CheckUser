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
		return $this->msg( 'checkuser-investigate' )->text();
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
		return [];
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
