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
use IContextSource;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use TablePager;
use Wikimedia\IPUtils;

class ComparePager extends TablePager {
	/** @var TokenManager */
	private $tokenManager;

	/** @var CompareService */
	private $compareService;

	/** @var array */
	private $requestData;

	/** @var array */
	private $fieldNames;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenManager $tokenManager,
		CompareService $compareService
	) {
		parent::__construct( $context, $linkRenderer );
		$this->tokenManager = $tokenManager;
		$this->compareService = $compareService;

		$this->requestData = $tokenManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $this->requestData['offset'] ?? '';
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
				$query['token'] = $this->tokenManager->encode( $session, array_merge( $this->requestData, [
					'offset' => $query['offset'],
				] ) );
				unset( $query['offset'] );
			} elseif ( isset( $this->requestData['offset'] ) ) {
				// Remove the offset.
				$data = $this->requestData;
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
	protected function getTableClass() {
		return parent::getTableClass() . ' ext-checkuser-investigate-table';
	}

	/**
	 * @inheritDoc
	 */
	public function getCellAttrs( $field, $value ) {
		$attributes = parent::getCellAttrs( $field, $value );
		$attributes['class'] = $attributes['class'] ?? '';

		$row = $this->mCurrentRow;
		$targets = $this->requestData['targets'];
		switch ( $field ) {
			case 'cuc_ip':
				foreach ( $targets as $target ) {
					if ( !IPUtils::isIPAddress( $target ) ) {
						continue;
					}

					if ( IPUtils::isValidRange( $target ) && IPUtils::isInRange( $value, $target ) ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-dark';
						break;
					} elseif ( IPUtils::toHex( $target ) === $row->cuc_ip_hex ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-dark';
						break;
					}
				}
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['data-' . $field] = $value;
				break;
			case 'cuc_user_text':
				if ( !IPUtils::isIpAddress( $value ) && in_array( $value, $targets ) ) {
					$attributes['class'] .= ' ext-checkuser-compare-table-cell-dark';
				}
				break;
			case 'cuc_agent':
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['data-' . $field] = $value;
				break;
		}

		return $attributes;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$language = $this->getLanguage();
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'cuc_user_text':
				if ( IPUtils::isValid( $value ) ) {
					$formatted = $this->msg( 'checkuser-investigate-compare-table-cell-unregistered' );
				} else {
					$formatted = Linker::userLink( $row->cuc_user, $value );
				}
				break;
			case 'cuc_ip':
				$formatted = Html::rawElement(
					'span',
					[ 'class' => "ext-checkuser-compare-table-cell-ip" ],
					htmlspecialchars( $value )
				);

				// get other edits
				$otherEdits = '';
				$edits = $this->compareService->getTotalEditsFromIp( $value );
				if ( !empty( $edits['total_edits'] ) ) {
					$otherEdits = Html::rawElement(
						'span',
						[],
						$this->msg(
							'checkuser-investigate-compare-table-cell-other-edits',
							$edits['total_edits']
						)->parse()
					);
				}

				$formatted .= Html::rawElement(
					'div',
					[],
					$this->msg(
						'checkuser-investigate-compare-table-cell-edits',
						$row->total_edits
					)->parse() . $otherEdits
				);

				break;
			case 'cuc_agent':
				$formatted = htmlspecialchars( $value );
				break;
			case 'activity':
				$firstEdit = $language->date( $row->first_edit );
				$lastEdit = $language->date( $row->last_edit );
				$formatted = $firstEdit . ' - ' . $lastEdit;
				break;
			default:
				$formatted = '';
		}

		return $formatted;
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'cuc_user_text', 'cuc_ip', 'cuc_agent' ] ];
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
	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = [
				'cuc_user_text' => 'checkuser-investigate-compare-table-header-username',
				'cuc_ip' => 'checkuser-investigate-compare-table-header-ip',
				'cuc_agent' => 'checkuser-investigate-compare-table-header-useragent',
				'activity' => 'checkuser-investigate-compare-table-header-activity',
			];
			foreach ( $this->fieldNames as $key => $val ) {
				$this->fieldNames[$key] = $this->msg( $val )->text();
			}
		}
		return $this->fieldNames;
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$targets = $this->requestData['targets'] ?: [];
		return $this->compareService->getQueryInfo( $targets );
	}
}
