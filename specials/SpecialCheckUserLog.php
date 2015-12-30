<?php

class SpecialCheckUserLog extends SpecialPage {
	public function __construct() {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
	}

	function execute( $par ) {
		$this->checkPermissions();

		$out = $this->getOutput();
		$request = $this->getRequest();
		$this->setHeaders();

		if ( $this->getUser()->isAllowed( 'checkuser' ) ) {
			$subtitleLink = Linker::linkKnown(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$this->msg( 'checkuser-showmain' )->escaped()
			);
			$out->addSubtitle( $subtitleLink );
		}

		$type = $request->getVal( 'cuSearchType' );
		$target = $request->getVal( 'cuSearch' );
		$target = trim( $target );
		$year = $request->getIntOrNull( 'year' );
		$month = $request->getIntOrNull( 'month' );
		$error = false;
		$dbr = wfGetDB( DB_SLAVE );
		$searchConds = false;
		if ( $type === null ) {
			$type = 'target';
		} elseif ( $type == 'initiator' ) {
			$user = User::newFromName( $target );
			if ( !$user || !$user->getID() ) {
				$error = 'checkuser-user-nonexistent';
			} else {
				$searchConds = array( 'cul_user' => $user->getID() );
			}
		} else /* target */ {
			$type = 'target';
			// Is it an IP?
			list( $start, $end ) = IP::parseRange( $target );
			if ( $start !== false ) {
				if ( $start == $end ) {
					$searchConds = array( 'cul_target_hex = ' . $dbr->addQuotes( $start ) . ' OR ' .
						'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
						'cul_range_start <= ' . $dbr->addQuotes( $end ) . ')'
					);
				} else {
					$searchConds = array(
						'(cul_target_hex >= ' . $dbr->addQuotes( $start ) . ' AND ' .
						'cul_target_hex <= ' . $dbr->addQuotes( $end ) . ') OR ' .
						'(cul_range_end >= ' . $dbr->addQuotes( $start ) . ' AND ' .
						'cul_range_start <= ' . $dbr->addQuotes( $end ) . ')'
					);
				}
			} else {
				// Is it a user?
				$user = User::newFromName( $target );
				if ( $user && $user->getID() ) {
					$searchConds = array(
						'cul_type' => array( 'userips', 'useredits' ),
						'cul_target_id' => $user->getID(),
					);
				} elseif ( $target ) {
					$error = 'checkuser-user-nonexistent';
				}
			}
		}

		$this->displaySearchForm();

		if ( $error !== false ) {
			$out->wrapWikiMsg( '<div class="errorbox">$1</div>', $error );
			return;
		}

		$pager = new CheckUserLogPager( $this, $searchConds, $year, $month );
		$out->addHTML(
			$pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar()
		);
	}

	/**
	 * Use an HTMLForm to create and output the search form used on this page.
	 */
	protected function displaySearchForm() {
		$this->getOutput()->addModules( 'mediawiki.userSuggest' );
		$request = $this->getRequest();
		$fields = array(
			'target' => array(
				'type' => 'user',
				// validation in execute() currently
				'exists' => false,
				'ipallowed' => true,
				'name' => 'cuSearch',
				'size' => 40,
				'cssclass' => 'mw-autocomplete-user', // for mediawiki.userSuggest autocompletions
				'label-message' => 'checkuser-log-search-target',
			),
			'type' => array(
				'type' => 'radio',
				'name' => 'cuSearchType',
				'label-message' => 'checkuser-log-search-type',
				'options-messages' => array(
					'checkuser-search-target' => 'target',
					'checkuser-search-initiator' => 'initiator',
				),
				'flatlist' => true,
				'default' => 'target',
			),
			// @todo hack until HTMLFormField has a proper date selector
			'monthyear' => array(
				'type' => 'info',
				'default' => Xml::dateMenu( $request->getInt( 'year' ), $request->getInt( 'month' ) ),
				'raw' => true,
			),
		);

		$form = HTMLForm::factory( 'table', $fields, $this->getContext() );
		$form->setMethod( 'get' )
			->setWrapperLegendMsg( 'checkuser-search' )
			->setSubmitTextMsg( 'checkuser-search-submit' )
			->prepareForm()
			->displayForm( false );
	}

	protected function getGroupName() {
		return 'changes';
	}
}
