<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Pager
 */

namespace MediaWiki\CheckUser;

use Html;
use Linker;
use ReverseChronologicalPager;

class InvestigateLogPager extends ReverseChronologicalPager {
	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return 'cul_timestamp';
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return [
			'tables' => [ 'cu_log', 'user' ],
			'fields' => [
				'cul_timestamp', 'cul_user', 'cul_reason',
				'cul_target_id', 'cul_target_text', 'user_name',
			],
			'conds' => [
				'cul_type' => 'investigate',
			],
			'join_conds' => [
				'user' => [ 'JOIN', 'cul_user = user_id' ],
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function formatRow( $row ) {
		$checkUser = Linker::userLink( $row->cul_user, $row->user_name );

		// build target links
		$target = Linker::userLink( $row->cul_target_id, $row->cul_target_text );
		$target .= Linker::userToolLinks( $row->cul_target_id, $row->cul_target_text );

		$reason = Linker::commentBlock( $row->cul_reason );

		$language = $this->getLanguage();
		$user = $this->getUser();
		$message = $this->msg(
			'checkuser-investigate-log-entry',
			$checkUser,
			$target,
			$language->userTimeAndDate( wfTimestamp( TS_MW, $row->cul_timestamp ), $user ),
			$reason
		)->text();

		return Html::rawElement( 'li', [], $message );
	}

	/**
	 * @inheritDoc
	 */
	public function getStartBody() {
		return $this->getNumRows() ? '<ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEndBody() {
		return $this->getNumRows() ? '</ul>' : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getEmptyBody() {
		return Html::rawElement( 'p', [], $this->msg( 'checkuser-investigate-log-empty' )->text() );
	}
}
