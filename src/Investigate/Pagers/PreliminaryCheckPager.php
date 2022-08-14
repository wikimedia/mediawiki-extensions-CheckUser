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

use ExtensionRegistry;
use Html;
use IContextSource;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\TokenQueryManager;
use MediaWiki\Extension\CentralAuth\CentralAuthDatabaseManager;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Linker\LinkRenderer;
use NamespaceInfo;
use SpecialPage;
use TablePager;
use WikiMap;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;

/**
 * @ingroup Pager
 */
class PreliminaryCheckPager extends TablePager {
	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/** @var array */
	protected $tokenData;

	/** @var TokenQueryManager */
	private $tokenQueryManager;

	/** @var PreliminaryCheckService */
	private $preliminaryCheckService;

	/** @var string[] Array of column name to translated table header message */
	private $fieldNames;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param NamespaceInfo $namespaceInfo
	 * @param TokenQueryManager $tokenQueryManager
	 * @param ExtensionRegistry $extensionRegistry
	 * @param PreliminaryCheckService $preliminaryCheckService
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		NamespaceInfo $namespaceInfo,
		TokenQueryManager $tokenQueryManager,
		ExtensionRegistry $extensionRegistry,
		PreliminaryCheckService $preliminaryCheckService
	) {
		// This must be done before getIndexField is called by the TablePager constructor
		$this->extensionRegistry = $extensionRegistry;
		if ( $this->isGlobalCheck() ) {
			// @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty
			$this->mDb = $this->getCentralReplicaDB();
		}

		parent::__construct( $context, $linkRenderer );
		$this->namespaceInfo = $namespaceInfo;
		$this->preliminaryCheckService = $preliminaryCheckService;
		$this->tokenQueryManager = $tokenQueryManager;

		$this->tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $this->tokenData['offset'] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	protected function getTableClass() {
		return parent::getTableClass() .
			' ext-checkuser-investigate-table' .
			' ext-checkuser-investigate-table-preliminary-check';
	}

	/**
	 * @inheritDoc
	 */
	public function getCellAttrs( $field, $value ) {
		$attributes = parent::getCellAttrs( $field, $value );
		$attributes['class'] = $attributes['class'] ?? '';

		switch ( $field ) {
			case 'wiki':
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $value;
				break;
			case 'registration':
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$date = $this->getLanguage()->userDate(
					$value,
					$this->getUser(),
					[ 'format' => 'ISO 8601' ]
				);
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $date;
				break;
		}

		// Add each cell to the tab index.
		$attributes['tabindex'] = 0;

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
				if ( $wiki ) {
					$formatted = Html::element(
						'a',
						[
							'href' => $wiki->getFullUrl(
								$this->namespaceInfo->getCanonicalName( NS_USER ) . ':' . $row->name
							),
						],
						$wiki->getDisplayName()
					);
				} else {
					$formatted = $this->msg( 'checkuser-investigate-preliminary-table-cell-wiki-nowiki' )->text();
				}
				break;
			case 'editcount':
				$wiki = WikiMap::getWiki( $row->wiki );
				if ( $wiki ) {
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
				} else {
					$formatted = $this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( 'Contributions', $row->name ),
						$this->msg(
							'checkuser-investigate-preliminary-table-cell-edits',
							$value
						)->text()
					);
				}
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
		return $this->isGlobalCheck() ? [ [ 'lu_name', 'lu_wiki' ] ] : 'user_name';
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$fullFieldNames = [
				'name' => 'checkuser-investigate-preliminary-table-header-name',
				'registration' => 'checkuser-investigate-preliminary-table-header-registration',
				'wiki' => 'checkuser-investigate-preliminary-table-header-wiki',
				'editcount' => 'checkuser-investigate-preliminary-table-header-editcount',
				'blocked' => 'checkuser-investigate-preliminary-table-header-blocked',
				'groups' => 'checkuser-investigate-preliminary-table-header-groups',
			];

			if ( !$this->isGlobalCheck() ) {
				unset( $fullFieldNames['wiki'] );
			}
			$this->fieldNames = $fullFieldNames;

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
		$targets = $this->tokenData['targets'] ?? [];
		$users = array_filter( array_map( 'User::newFromName', $targets ), static function ( $user ) {
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

	/**
	 * @return bool
	 */
	public function isGlobalCheck(): bool {
		return $this->extensionRegistry->isLoaded( 'CentralAuth' )
			&& class_exists( CentralAuthDatabaseManager::class );
	}

	/**
	 * @return IDatabase|null
	 */
	protected function getCentralReplicaDB(): ?IDatabase {
		if ( class_exists( CentralAuthDatabaseManager::class ) ) {
			return CentralAuthServices::getDatabaseManager()->getCentralDB( DB_REPLICA );
		}
		return null;
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
