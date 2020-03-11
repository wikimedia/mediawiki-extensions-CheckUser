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
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;

class ComparePager extends InvestigatePager {
	/** @var CompareService */
	private $compareService;

	/** @var array */
	private $fieldNames;

	/** @var string[] */
	private $filteredTargets;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenManager $tokenManager,
		CompareService $compareService
	) {
		parent::__construct( $context, $linkRenderer, $tokenManager );
		$this->compareService = $compareService;

		$this->filteredTargets = array_diff(
			$this->requestData['targets'] ?? [],
			$this->requestData['hide-targets'] ?? []
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getCellAttrs( $field, $value ) {
		$attributes = parent::getCellAttrs( $field, $value );
		$attributes['class'] = $attributes['class'] ?? '';

		$row = $this->mCurrentRow;
		switch ( $field ) {
			case 'cuc_ip':
				foreach ( $this->filteredTargets as $target ) {
					if ( !IPUtils::isIPAddress( $target ) ) {
						continue;
					}

					if ( IPUtils::isValidRange( $target ) && IPUtils::isInRange( $value, $target ) ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
						break;
					} elseif ( IPUtils::toHex( $target ) === $row->cuc_ip_hex ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
						break;
					}
				}
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['data-' . $field] = $value;
				break;
			case 'cuc_user_text':
				if ( !IPUtils::isIpAddress( $value ) && in_array( $value, $this->filteredTargets ) ) {
					$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
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
	 *
	 * Handle special case where all targets are filtered.
	 */
	public function doQuery() {
		// If there are no targets, there is no need to run the query and an empty result can be used.
		if ( $this->filteredTargets === [] ) {
			$this->mResult = new FakeResultWrapper( [] );
			$this->mQueryDone = true;
			return $this->mResult;
		}

		return parent::doQuery();
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->compareService->getQueryInfo( $this->filteredTargets );
	}

	/**
	 * Check if we have incomplete data for any of the targets.
	 *
	 * @return string[] Targets whose limits were exceeded (if any)
	 */
	public function getTargetsOverLimit() : array {
		return $this->compareService->getTargetsOverLimit( $this->filteredTargets );
	}
}
