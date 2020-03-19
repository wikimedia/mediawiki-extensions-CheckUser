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

use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use TablePager;

abstract class InvestigatePager extends TablePager {
	/** @var TokenManager */
	private $tokenManager;

	/** @var array */
	protected $tokenData;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenManager $tokenManager
	) {
		parent::__construct( $context, $linkRenderer );

		$this->tokenManager = $tokenManager;
		$this->tokenData = $tokenManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $this->tokenData['offset'] ?? '';
	}

	/**
	 * @inheritDoc
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		$session = $this->getContext()->getRequest()->getSession();
		$queries = parent::getPagingQueries();
		foreach ( $queries as $key => &$query ) {
			if ( $query === false ) {
				continue;
			}

			if ( isset( $query['offset'] ) ) {
				// Move the offset into the token.
				$query['token'] = $this->tokenManager->encode( $session, array_merge( $this->tokenData, [
					'offset' => $query['offset'],
				] ) );
				unset( $query['offset'] );
			} elseif ( isset( $this->tokenData['offset'] ) ) {
				// Remove the offset.
				$data = $this->tokenData;
				unset( $data['offset'] );
				$query['token'] = $this->tokenManager->encode( $session, $data );
			}
		}

		return $queries;
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldSortable( $field ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getTableClass() {
		return parent::getTableClass() . ' ext-checkuser-investigate-table';
	}
}
