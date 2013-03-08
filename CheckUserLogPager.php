<?php

class CheckUserLogPager extends ReverseChronologicalPager {
	var $searchConds, $specialPage, $y, $m;

	function __construct( $specialPage, $searchConds, $y, $m ) {
		parent::__construct();

		$this->getDateCond( $y, $m );
		$this->searchConds = $searchConds ? $searchConds : array();
		$this->specialPage = $specialPage;
	}

	function formatRow( $row ) {
		if ( $row->cul_reason === '' ) {
			$comment = '';
		} else {
			$comment = Linker::commentBlock( $row->cul_reason );
		}

		$user = Linker::userLink( $row->cul_user, $row->user_name );

		if ( $row->cul_type == 'userips' || $row->cul_type == 'useredits' ) {
			$target = Linker::userLink( $row->cul_target_id, $row->cul_target_text ) .
					Linker::userToolLinks( $row->cul_target_id, $row->cul_target_text );
		} else {
			$target = $row->cul_target_text;
		}

		// Give grep a chance to find the usages:
		// checkuser-log-userips, checkuser-log-ipedits, checkuser-log-ipusers,
		// checkuser-log-ipedits-xff, checkuser-log-ipusers-xff, checkuser-log-useredits
		return '<li>' .
			$this->getLanguage()->timeanddate( wfTimestamp( TS_MW, $row->cul_timestamp ), true ) .
			$this->msg( 'comma-separator' )->text() .
			$this->msg(
				'checkuser-log-' . $row->cul_type,
				$user,
				$target
			)->text() .
			$comment .
			'</li>';
	}

	/**
	 * @return string
	 */
	function getStartBody() {
		if ( $this->getNumRows() ) {
			return '<ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	function getEndBody() {
		if ( $this->getNumRows() ) {
			return '</ul>';
		} else {
			return '';
		}
	}

	/**
	 * @return string
	 */
	function getEmptyBody() {
		return '<p>' . $this->msg( 'checkuser-empty' )->escaped() . '</p>';
	}

	function getQueryInfo() {
		$this->searchConds[] = 'user_id = cul_user';
		return array(
			'tables' => array( 'cu_log', 'user' ),
			'fields' => $this->selectFields(),
			'conds'  => $this->searchConds
		);
	}

	function getIndexField() {
		return 'cul_timestamp';
	}

	function selectFields() {
		return array(
			'cul_id', 'cul_timestamp', 'cul_user', 'cul_reason', 'cul_type',
			'cul_target_id', 'cul_target_text', 'user_name'
		);
	}
}
