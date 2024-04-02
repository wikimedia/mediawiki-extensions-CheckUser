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

namespace MediaWiki\CheckUser\Investigate\Pagers;

use DateTime;
use Html;
use IContextSource;
use Linker;
use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;
use TablePager;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;

class ComparePager extends TablePager {
	private CompareService $compareService;
	private TokenQueryManager $tokenQueryManager;
	private UserFactory $userFactory;

	/** @var array */
	private $fieldNames;

	/**
	 * Holds a cache of iphex => edit-count to avoid
	 * recurring queries to the database for the same ip
	 *
	 * @var array
	 */
	private $ipTotalEdits;

	/**
	 * Targets whose results should not be included in the investigation.
	 * Targets in this list may or may not also be in the $targets list.
	 * Either way, no activity related to these targets will appear in the
	 * results.
	 *
	 * @var string[]
	 */
	private $excludeTargets;

	/**
	 * Targets that have been added to the investigation but that are not
	 * present in $excludeTargets. These are the targets that will actually
	 * be investigated.
	 *
	 * @var string[]
	 */
	private $filteredTargets;

	/** @var string */
	private $start;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param TokenQueryManager $tokenQueryManager
	 * @param DurationManager $durationManager
	 * @param CompareService $compareService
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		CompareService $compareService,
		UserFactory $userFactory
	) {
		parent::__construct( $context, $linkRenderer );
		$this->compareService = $compareService;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->userFactory = $userFactory;

		$tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $tokenData['offset'] ?? '';

		$this->excludeTargets = $tokenData['exclude-targets'] ?? [];
		$this->filteredTargets = array_diff(
			$tokenData['targets'] ?? [],
			$this->excludeTargets
		);

		$this->start = $durationManager->getTimestampFromRequest( $context->getRequest() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getTableClass() {
		$sortableClass = $this->mIsFirst && $this->mIsLast ? 'sortable' : '';
		return implode( ' ', [
			parent::getTableClass(),
			$sortableClass,
			'ext-checkuser-investigate-table',
			'ext-checkuser-investigate-table-compare'
		] );
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
					}

					if ( IPUtils::toHex( $target ) === $row->cuc_ip_hex ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
						break;
					}
				}
				$ipHex = IPUtils::toHex( $value );
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-ip-target';
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $value;
				$attributes['data-sort-value'] = $ipHex;
				$attributes['data-edits'] = $row->total_edits;
				$attributes['data-all-edits'] = $this->ipTotalEdits[$ipHex];
				break;
			case 'cuc_user_text':
				// Hide the username if it is hidden from the current authority.
				$user = $this->userFactory->newFromName( $value );
				$userIsHidden = $user !== null && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' );
				if ( $userIsHidden ) {
					$value = $this->msg( 'rev-deleted-user' )->text();
				}
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				if ( !IPUtils::isIpAddress( $value ) ) {
					if ( !$userIsHidden ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-user-target';
					}
					if ( in_array( $value, $this->filteredTargets ) ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
					}
					$attributes['data-field'] = $field;
					$attributes['data-value'] = $value;
				}
				// Store the sort value as an attribute, to avoid using the table cell contents
				// as the sort value, since UI elements are added to the table cell.
				$attributes['data-sort-value'] = $value;
				break;
			case 'cuc_agent':
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-user-agent';
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $value;
				// Store the sort value as an attribute, to avoid using the table cell contents
				// as the sort value, since UI elements are added to the table cell.
				$attributes['data-sort-value'] = $value;
				break;
			case 'activity':
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-activity';
				$start = new DateTime( $row->first_edit );
				$end = new DateTime( $row->last_edit );
				$attributes['data-sort-value'] = $start->format( 'Ymd' ) . $end->format( 'Ymd' );
				break;
		}

		// Add each cell to the tab index.
		$attributes['tabindex'] = 0;

		return $attributes;
	}

	/**
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$language = $this->getLanguage();
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'cuc_user_text':
				// Hide the username if it is hidden from the current authority.
				$user = $this->userFactory->newFromName( $value );
				if ( $user !== null && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
					return $this->msg( 'rev-deleted-user' )->text();
				}
				if ( IPUtils::isValid( $value ) ) {
					$formatted = $this->msg( 'checkuser-investigate-compare-table-cell-unregistered' );
				} else {
					$formatted = Linker::userLink( $row->cuc_user ?? 0, $value );
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
				$ipHex = $row->cuc_ip_hex;
				if ( !isset( $this->ipTotalEdits[$ipHex] ) ) {
					$this->ipTotalEdits[$ipHex] = $this->compareService->getTotalEditsFromIp( $ipHex );
				}

				if ( $this->ipTotalEdits[$ipHex] ) {
					$otherEdits = Html::rawElement(
						'span',
						[],
						$this->msg(
							'checkuser-investigate-compare-table-cell-other-edits',
							$this->ipTotalEdits[$ipHex]
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
				$formatted = htmlspecialchars( $value ?? '' );
				break;
			case 'activity':
				$firstEdit = $language->userDate( $row->first_edit, $this->getUser() );
				$lastEdit = $language->userDate( $row->last_edit, $this->getUser() );
				$formatted = htmlspecialchars( $firstEdit . ' - ' . $lastEdit );
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
		return [ [ 'cuc_user_text', 'cuc_ip_hex', 'cuc_agent' ] ];
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
			foreach ( $this->fieldNames as &$val ) {
				$val = $this->msg( $val )->text();
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
			return;
		}

		parent::doQuery();
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->compareService->getQueryInfo(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->start
		);
	}

	/**
	 * Check if we have incomplete data for any of the targets.
	 *
	 * @return string[] Targets whose limits were exceeded (if any)
	 */
	public function getTargetsOverLimit(): array {
		return $this->compareService->getTargetsOverLimit(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->start
		);
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
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		return $this->tokenQueryManager->getPagingQueries(
			$this->getRequest(), parent::getPagingQueries()
		);
	}
}
