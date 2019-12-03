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
use Wikimedia\Rdbms\FakeResultWrapper;

/**
 * @ingroup Pager
 */
class PreliminaryCheckPager extends TablePager {
	/** @var TokenManager */
	private $tokenManager;

	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var string[] Array of column name to translated table header message */
	private $fieldNames;

	/** @var array */
	private $requestData;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param TokenManager $tokenManager
	 * @param PreliminaryCheckService $preliminaryCheckService
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		TokenManager $tokenManager,
		PreliminaryCheckService $preliminaryCheckService
	) {
		parent::__construct( $context, $linkRenderer );
		$this->tokenManager = $tokenManager;
		$this->preliminaryCheckService = $preliminaryCheckService;
		$this->requestData = $tokenManager->getDataFromContext( $context );
		$this->mOffset = $this->requestData['offset'] ?? '';
	}

	/**
	 * @inheritDoc
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		$user = $this->getContext()->getUser();
		$queries = parent::getPagingQueries();
		foreach ( $queries as $key => &$query ) {
			if ( $query === false ) {
				continue;
			}

			if ( isset( $query['offset'] ) ) {
				// Move the offset into the token.
				$query['token'] = $this->tokenManager->encode( $user, array_merge( $this->requestData, [
					'offset' => $query['offset'],
				] ) );
				unset( $query['offset'] );
			} elseif ( isset( $this->requestData['offset'] ) ) {
				// Remove the offset.
				$data = $this->requestData;
				unset( $data['offset'] );
				$query['token'] = $this->tokenManager->encode( $user, $data );
			}
		}

		return $queries;
	}

	/**
	 * @inheritDoc
	 */
	public function doQuery() {
		$defaultOrder = ( $this->mDefaultDirection === self::DIR_ASCENDING )
			? self::QUERY_ASCENDING
			: self::QUERY_DESCENDING;
		$order = $this->mIsBackwards ? self::oppositeOrder( $defaultOrder ) : $defaultOrder;

		// Extra row so that we can tell whether "next" link should be shown
		$queryLimit = $this->mLimit + 1;

		if ( $this->mOffset !== '' ) {
			[ $nameOffset, $wikiOffset ] = explode( '>', $this->mOffset );
			$offsets = [
				'name' => $nameOffset,
				'wiki' => $wikiOffset,
			];
		} else {
			$offsets = false;
		}

		$pageInfo = [
			'includeOffset' => $this->mIncludeOffset,
			'offsets' => $offsets,
			'limit' => $queryLimit,
			'order' => $order,
		];

		$targets = $this->requestData['targets'] ?? [];

		$users = array_filter( array_map( 'User::newFromName', $targets ), function ( $user ) {
			return (bool)$user;
		} );

		if ( $this->mOffset == '' ) {
			$isFirst = true;
		} else {
			// If there's an offset, we may or may not be at the first entry.
			// The only way to tell is to run the query in the opposite
			// direction see if we get a row.
			$oldIncludeOffset = $this->mIncludeOffset;
			$this->mIncludeOffset = !$this->mIncludeOffset;
			$oppositeOrder = self::oppositeOrder( $order );

			$pageInfoTemp = [
				'includeOffset' => $this->mIncludeOffset,
				'offsets' => false,
				'limit' => 1,
				'order' => $oppositeOrder,
			];

			$checkResult = $this->preliminaryCheckService
				->getPreliminaryData( $users, $pageInfoTemp );
			$checkResult = new FakeResultWrapper( $checkResult );
			$isFirst = !$checkResult->numRows();
			$this->mIncludeOffset = $oldIncludeOffset;
		}

		$result = $this->preliminaryCheckService->getPreliminaryData( $users, $pageInfo );
		$this->mResult = new FakeResultWrapper( $result );

		$this->extractResultInfo( $isFirst, $queryLimit, $this->mResult );
		$this->mQueryDone = true;

		$this->mResult->rewind();
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldSortable( $field ) {
		return false;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$language = $this->getLanguage();

		// TODO: Format according to task
		$formatted = '';
		switch ( $name ) {
			case 'name':
				$formatted = htmlspecialchars( $value );
			break;
			case 'registration':
				$formatted = htmlspecialchars(
					$language->userTimeAndDate( $value, $this->getUser() )
				);
			break;
			case 'wiki':
				$formatted = htmlspecialchars( $value );
			break;
			case 'editcount':
				$formatted = $this->msg(
					'checkuser-investigate-preliminary-table-cell-edits',
					$value
				)->parse();
			break;
			case 'blocked':
				if ( $value ) {
					$formatted = $this->msg(
						'checkuser-investigate-preliminary-table-cell-blocked'
					)->parse();
				} else {
					$formatted = $this->msg(
						'checkuser-investigate-preliminary-table-cell-unblocked'
					)->parse();
				}
			break;
			case 'groups':
				$formatted = htmlspecialchars( implode( ', ', $value ) );
			break;
		}

		return $formatted;
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return 'lu_name_wiki';
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = [
				'name' => 'checkuser-investigate-preliminary-table-header-name',
				'registration' => 'checkuser-investigate-preliminary-table-header-registration',
				'wiki' => 'checkuser-investigate-preliminary-table-header-wiki',
				'editcount' => 'checkuser-investigate-preliminary-table-header-editcount',
				'blocked' => 'checkuser-investigate-preliminary-table-header-blocked',
				'groups' => 'checkuser-investigate-preliminary-table-header-groups',
			];
			foreach ( $this->fieldNames as $key => $val ) {
				$this->fieldNames[$key] = $this->msg( $val )->text();
			}
		}
		return $this->fieldNames;
	}

	/**
	 * Abstract method override. Returns an empty array to be compatible with parent,
	 * but should not be called.
	 *
	 * @return array
	 */
	public function getQueryInfo() {
		// doQuery is overridden, so nothing to do here
		return [];
	}
}
