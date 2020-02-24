<?php

namespace MediaWiki\CheckUser;

use HTMLForm;
use OOUI\Element;
use OOUI\HtmlSnippet;
use OOUI\IndexLayout;
use OOUI\MessageWidget;
use OOUI\TabOptionWidget;
use OOUI\Tag;
use Wikimedia\IPUtils;

class SpecialInvestigate extends \FormSpecialPage {
	/** @var PagerFactory */
	private $preliminaryCheckPagerFactory;

	/** @var PagerFactory */
	private $comparePagerFactory;

	/** @var TokenManager */
	private $tokenManager;

	/** @var IndexLayout|null */
	private $layout;

	/** @var array|null */
	private $requestData;

	/** string|null */
	private $tokenWithoutPaginationData;

	/**
	 * @param PagerFactory $preliminaryCheckPagerFactory
	 * @param PagerFactory $comparePagerFactory
	 * @param TokenManager $tokenManager
	 */
	public function __construct(
		PagerFactory $preliminaryCheckPagerFactory,
		PagerFactory $comparePagerFactory,
		TokenManager $tokenManager
	) {
		parent::__construct( 'Investigate', 'checkuser' );
		$this->preliminaryCheckPagerFactory = $preliminaryCheckPagerFactory;
		$this->comparePagerFactory = $comparePagerFactory;
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
		$this->getOutput()->addModuleStyles( 'ext.checkUser.investigate' );
		$this->getOutput()->addModules( 'oojs-ui.styles.icons-content' );

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

		$token = $this->getTokenWithoutPaginationData();

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
	 * @return string|null
	 */
	private function getTokenWithoutPaginationData() {
		if ( $this->tokenWithoutPaginationData === null ) {
			$requestData = $this->getRequestData();
			$token = $this->getRequest()->getVal( 'token' );
			if ( isset( $requestData['offset'] ) ) {
				unset( $requestData['offset'] );
				$token = $this->tokenManager->encode(
					$this->getRequest()->getSession(),
					$requestData
				);
			}
			$this->tokenWithoutPaginationData = $token;
		}
		return $this->tokenWithoutPaginationData;
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
				$pager = $this->preliminaryCheckPagerFactory->createPager( $this->getContext() );
				$hasIpTargets = (bool)array_filter(
					$this->getRequestData()['targets'] ?? [],
					function ( $target ) {
						return IPUtils::isIPAddress( $target );
					}
				);

				if ( $pager->getNumRows() ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} elseif ( !$hasIpTargets ) {
					$this->addHTML(
						$this->msg( 'checkuser-investigate-notice-no-results' )->parse()
					);
				}

				if ( $hasIpTargets ) {
					$compareLabel = $this->msg( 'checkuser-investigate-tab-compare' )->parse();
					// getFullURL handles the query params:
					// https://www.mediawiki.org/wiki/Help:Links#External_links_to_internal_pages
					$link = $this->getPageTitle( $compareLabel )->getFullURL( [
						'token' => $this->getTokenWithoutPaginationData(),
					] );
					$message = $this->msg( 'checkuser-investigate-preliminary-notice-ip-targets', $link )->parse();
					$this->addHTML( new MessageWidget( [
						'type' => 'notice',
						'label' => new HtmlSnippet( $message )
					] ) );
				}

				return $this;
			case $this->getTabParam( 'compare' ):
				$pager = $this->comparePagerFactory->createPager( $this->getContext() );

				if ( $pager->getNumRows() ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} else {
					$this->addHTML(
						$this->msg( 'checkuser-investigate-notice-no-results' )->parse()
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
			$this->requestData = $this->tokenManager->getDataFromRequest( $this->getRequest() );
		}

		return $this->requestData;
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		// Store the targets in a signed token.
		$token = $this->tokenManager->encode(
			$this->getRequest()->getSession(),
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
