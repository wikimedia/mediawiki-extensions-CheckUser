<?php

namespace MediaWiki\CheckUser;

use ExtensionRegistry;
use HTMLForm;
use NamespaceInfo;
use OOUI\Element;
use OOUI\HtmlSnippet;
use OOUI\IndexLayout;
use OOUI\TabOptionWidget;
use OOUI\Tag;

class SpecialInvestigate extends \FormSpecialPage {

	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var CompareService */
	private $compareService;

	/** @var TokenManager */
	private $tokenManager;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var IndexLayout|null */
	private $layout;

	/** @var array|null */
	private $requestData;

	/**
	 * @param PreliminaryCheckService $preliminaryCheckService
	 * @param CompareService $compareService
	 * @param TokenManager $tokenManager
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		PreliminaryCheckService $preliminaryCheckService,
		CompareService $compareService,
		TokenManager $tokenManager,
		NamespaceInfo $namespaceInfo
	) {
		parent::__construct( 'Investigate', 'checkuser' );
		$this->preliminaryCheckService = $preliminaryCheckService;
		$this->compareService = $compareService;
		$this->tokenManager = $tokenManager;
		$this->namespaceInfo = $namespaceInfo;
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
		$this->getOutput()->addModuleStyles( 'ext.checkUser.investigate' );

		// If the request was POST or the request has no data, show the form.
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

		$this->addTabs( $par )->addTabContent( $par );
		$this->getOutput()->addHTML( $this->getLayout() );
	}

	/**
	 * Returns the OOUI Index Layout and adds the module dependencies for OOUI.
	 *
	 * @return IndexLayout
	 */
	private function getLayout() : IndexLayout {
		if ( $this->layout === null ) {
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addModuleStyles( [
				'oojs-ui-widgets.styles',
			] );

			$this->layout = new IndexLayout( [
				'framed' => false,
				'expanded' => false,
			] );
		}

		return $this->layout;
	}

	/**
	 * Add tabs to the layout. Provide the current tab so that tab can be highlighted.
	 *
	 * @param string $par
	 * @return self
	 */
	private function addTabs( string $par ) : self {
		$config = [];
		[
			'tabSelectWidget' => $tabSelectWidget,
		] = $this->getLayout()->getConfig( $config );

		// Create token without pagination data (if necessary).
		$requestData = $this->getRequestData();
		$token = $this->getRequest()->getVal( 'token' );
		if ( isset( $requestData['offset'] ) ) {
			unset( $requestData['offset'] );
			$token = $this->tokenManager->encode(
				$this->getUser(),
				$requestData
			);
		}

		$tabs = array_map( function ( $tab ) use ( $par, $token ) {
			$label = $this->getTabName( $tab );
			return new TabOptionWidget( [
				'label' => $label,
				'labelElement' => ( new Tag( 'a' ) )->setAttributes( [
					'href' => $this->getPageTitle( $label )->getLocalURL( [
						'token' => $token,
					] ),
				] ),
				'selected' => ( $par === $this->getTabParam( $tab ) ),
			] );
		}, [
			'preliminary-check',
			'compare',
			'timeline',
		] );

		$tabSelectWidget->addItems( $tabs );

		return $this;
	}

	/**
	 * Add HTML to Layout.
	 *
	 * @param string $html
	 * @return self
	 */
	private function addHtml( string $html ) : self {
		$config = [];
		[
			'contentPanel' => $contentPanel
		] = $this->getLayout()->getConfig( $config );

		$contentPanel->addItems( [
			new Element( [
				'content' => new HtmlSnippet( $html ),
			] ),
		] );

		return $this;
	}

	/**
	 * Add Pager Output to Layout.
	 *
	 * @param \ParserOutput $parserOutput
	 * @return self
	 */
	private function addParserOutput( \ParserOutput $parserOutput ) : self {
		$this->getOutput()->addParserOutputMetadata( $parserOutput );
		$this->addHTML( $parserOutput->getText() );

		return $this;
	}

	/**
	 * Add Tab content to Layout
	 *
	 * @param string $par
	 * @return self
	 */
	private function addTabContent( string $par ) : self {
		switch ( $par ) {
			case $this->getTabParam( 'preliminary-check' ):
				$pager = new PreliminaryCheckPager(
					$this->getContext(),
					$this->getLinkRenderer(),
					$this->namespaceInfo,
					$this->tokenManager,
					ExtensionRegistry::getInstance(),
					$this->preliminaryCheckService
				);

				if ( $pager->getNumRows() ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} else {
					$this->addHTML(
						$this->msg( 'checkuser-investigate-preliminary-table-empty' )->parse()
					);
				}

				return $this;
			case $this->getTabParam( 'compare' ):
				$pager = new ComparePager(
					$this->getContext(),
					$this->getLinkRenderer(),
					$this->tokenManager,
					$this->compareService
				);

				if ( $pager->getNumRows() ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} else {
					$this->addHTML(
						$this->msg( 'checkuser-investigate-preliminary-table-empty' )->parse()
					);
				}
				return $this;
			case $this->getTabParam( 'timeline' ):
				// @TODO Add Content.
				return $this;
			default:
				return $this;
		}
	}

	/**
	 * Given a tab name, return the subpage $par.
	 *
	 * @param string $tab
	 *
	 * @return string
	 */
	private function getTabParam( string $tab ) : string {
		return str_replace( ' ', '_', $this->getTabName( $tab ) );
	}

	/**
	 * Given a tab name, return the supage tab name.
	 *
	 * @param string $tab
	 *
	 * @return string
	 */
	private function getTabName( string $tab ) : string {
		return $this->msg( 'checkuser-investigate-tab-' . $tab )->parse();
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
				'input' => [
					'autocomplete' => false,
				],
			],
			'Reason' => [
				'type' => 'text',
				'name' => 'reason',
				'label-message' => $prefix . '-reason-label',
				'required' => true,
				'autocomplete' => false,
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

		// Redirect to preliminary check.
		$url = $this->getPageTitle( $this->getTabName( 'preliminary-check' ) )->getFullUrlForRedirect( [
			'token' => $token,
		] );
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
