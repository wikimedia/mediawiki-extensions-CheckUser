<?php

class SpecialCheckUserLog extends SpecialPage {
	public function __construct() {
		parent::__construct( 'CheckUserLog', 'checkuser-log' );
	}

	function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$this->setHeaders();

		$type = $request->getVal( 'cuSearchType' );
		$target = $request->getVal( 'cuSearch' );
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
		
		$searchTypes = array( 'initiator', 'target' );
		$select = "<select name=\"cuSearchType\" style='margin-top:.2em;'>\n";
		foreach ( $searchTypes as $searchType ) {
			if ( $type == $searchType ) {
				$checked = 'selected="selected"';
			} else {
				$checked = '';
			}
			$caption = wfMsgHtml( 'checkuser-search-' . $searchType );
			$select .= "<option value=\"$searchType\" $checked>$caption</option>\n";
		}
		$select .= '</select>';

		$encTarget = htmlspecialchars( $target );
		$msgSearch = wfMsgHtml( 'checkuser-search' );
		$input = "<input type=\"text\" name=\"cuSearch\" value=\"$encTarget\" size=\"40\"/>";
		$msgSearchForm = wfMsgHtml( 'checkuser-search-form', $select, $input );
		$formAction = $this->getTitle()->escapeLocalURL();
		$msgSearchSubmit = '&#160;&#160;' . wfMsgHtml( 'checkuser-search-submit' ) . '&#160;&#160;';

		$s = "<form method='get' action=\"$formAction\">\n" .
			"<fieldset><legend>$msgSearch</legend>\n" .
			"<p>$msgSearchForm</p>\n" .
			"<p>" . $this->getDateMenu( $year, $month ) . "&#160;&#160;&#160;\n" .
			"<input type=\"submit\" name=\"cuSearchSubmit\" value=\"$msgSearchSubmit\"/></p>\n" .
			"</fieldset></form>\n";
		$out->addHTML( $s );

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
	 * @return string Formatted HTML
	 * @param int $year
	 * @param int $month
	 */
	protected function getDateMenu( $year, $month ) {
		# Offset overrides year/month selection
		if ( $month && $month !== - 1 ) {
			$encMonth = intval( $month );
		} else {
			$encMonth = '';
		}
		if ( $year ) {
			$encYear = intval( $year );
		} elseif ( $encMonth ) {
			$thisMonth = intval( gmdate( 'n' ) );
			$thisYear = intval( gmdate( 'Y' ) );
			if ( intval( $encMonth ) > $thisMonth ) {
				$thisYear--;
			}
			$encYear = $thisYear;
		} else {
			$encYear = '';
		}
		return Xml::label( wfMsg( 'year' ), 'year' ) . ' ' .
			Xml::input( 'year', 4, $encYear, array( 'id' => 'year', 'maxlength' => 4 ) ) .
			' ' .
			Xml::label( wfMsg( 'month' ), 'month' ) . ' ' .
			Xml::monthSelector( $encMonth, - 1 );
	}
}
