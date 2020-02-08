<?php

namespace MediaWiki\CheckUser;

use HTMLForm;

class SpecialInvestigate extends \FormSpecialPage {

	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var TokenManager */
	private $tokenManager;

	/** @var array|null */
	private $requestData;

	/**
	 * @param PreliminaryCheckService $preliminaryCheckService
	 * @param TokenManager $tokenManager
	 */
	public function __construct(
		PreliminaryCheckService $preliminaryCheckService,
		TokenManager $tokenManager
	) {
		parent::__construct( 'Investigate', 'checkuser' );
		$this->preliminaryCheckService = $preliminaryCheckService;
		$this->tokenManager = $tokenManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function preText() {
		// Add necessary styles
		$this->getOutput()->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );

		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// If the request was POST or the request has no targets, show the form.
		if ( $this->getRequest()->wasPosted() || $this->getRequestData() === [] ) {
			return parent::execute( $par );
		}

		// Perform the access checks ourselves.
		// @see parent::execute().
		$this->setParameter( $par );
		$this->setHeaders();

		// This will throw exceptions if there's a problem
		$this->checkExecutePermissions( $this->getUser() );

		$securityLevel = $this->getLoginSecurityLevel();
		if ( $securityLevel !== false && !$this->checkLoginSecurityLevel( $securityLevel ) ) {
			return;
		}

		$out = $this->getOutput();

		$pager = new PreliminaryCheckPager(
			$this->getContext(),
			$this->getLinkRenderer(),
			$this->tokenManager,
			$this->preliminaryCheckService
		);

		if ( $pager->getNumRows() ) {
			$out->addParserOutputContent( $pager->getFullOutput() );
		} else {
			$out->addWikiMsg( 'checkuser-investigate-preliminary-table-empty' );
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
		$data = $this->getRequestData();

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
				'default' => implode( "\n", $data['targets'] ?? [] ),
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
	 * Get data from the request token.
	 *
	 * @return array
	 */
	private function getRequestData() : array {
		if ( $this->requestData === null ) {
			$this->requestData = $this->tokenManager->getDataFromContext( $this->getContext() );
		}

		return $this->requestData;
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		// Store the targets in a signed token.
		$token = $this->tokenManager->encode(
			$this->getUser(),
			[
				'targets' => explode( "\n", $data['Targets'] ?? '' ),
			]
		);

		// Redirect back to self.
		$url = wfAppendQuery(
			$this->getRequest()->getRequestURL(),
			wfArrayToCgi( [ 'token' => $token ] )
		);
		$this->getOutput()->redirect( $url );

		return \Status::newGood();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}
}
