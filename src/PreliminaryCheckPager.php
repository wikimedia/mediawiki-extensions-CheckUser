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

use ExtensionRegistry;
use Html;
use IContextSource;
use MediaWiki\Linker\LinkRenderer;
use NamespaceInfo;
use TablePager;
use WikiMap;
use Wikimedia\Rdbms\FakeResultWrapper;

/**
 * @ingroup Pager
 */
class PreliminaryCheckPager extends TablePager {
	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var TokenManager */
	private $tokenManager;

	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var string[] Array of column name to translated table header message */
	private $fieldNames;

	/** @var array */
	private $requestData;

	/** @var bool */
	private $globalCheck;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param NamespaceInfo $namespaceInfo
	 * @param TokenManager $tokenManager
	 * @param ExtensionRegistry $extensionRegistry
	 * @param PreliminaryCheckService $preliminaryCheckService
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		NamespaceInfo $namespaceInfo,
		TokenManager $tokenManager,
		ExtensionRegistry $extensionRegistry,
		PreliminaryCheckService $preliminaryCheckService
	) {
		// This must be done before getIndexField is called by the parent constructor
		$this->globalCheck = $extensionRegistry->isLoaded( 'CentralAuth' );
		if ( $this->globalCheck ) {
			$this->mDb = \CentralAuthUtils::getCentralReplicaDB();
		}

		parent::__construct( $context, $linkRenderer );

		$this->namespaceInfo = $namespaceInfo;
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
		$row = $this->mCurrentRow;

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
				$wiki = WikiMap::getWiki( $row->wiki );
				$formatted = Html::element(
					'a',
					[
						'href' => $wiki->getFullUrl(
							$this->namespaceInfo->getCanonicalName( NS_USER ) . ':' . $row->name
						),
					],
					$wiki->getDisplayName()
				);
				break;
			case 'editcount':
				$wiki = WikiMap::getWiki( $row->wiki );
				$formatted = Html::rawElement(
					'a',
					[
						'href' => $wiki->getFullUrl(
							$this->namespaceInfo->getCanonicalName( NS_SPECIAL ) . ':Contributions/' . $row->name
						),
					],
					$this->msg(
						'checkuser-investigate-preliminary-table-cell-edits',
						$value
					)->parse()
				);
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
	public function getIndexField() {
		return $this->globalCheck ? [ [ 'lu_name', 'lu_wiki' ] ] : 'user_name';
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
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		$targets = $this->requestData['targets'] ?? [];
		$users = array_filter( array_map( 'User::newFromName', $targets ), function ( $user ) {
			return (bool)$user;
		} );

		return $this->preliminaryCheckService->getQueryInfo( $users );
	}

	/**
	 * @inheritDoc
	 */
	public function preprocessResults( $result ) {
		$this->mResult = new FakeResultWrapper(
			$this->preliminaryCheckService->preprocessResults( $result )
		);
	}
}
