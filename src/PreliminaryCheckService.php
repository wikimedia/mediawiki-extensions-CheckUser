<?php
namespace MediaWiki\CheckUser;

use ExtensionRegistry;
use IndexPager;
use User;
use UserGroupMembership;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\IResultWrapper;

class PreliminaryCheckService {
	/** @var ILBFactory */
	private $lbFactory;

	/** @var string */
	private $localWikiId;

	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/**
	 * @param ILBFactory $lbFactory
	 * @param ExtensionRegistry $extensionRegistry
	 * @param string $localWikiId
	 */
	public function __construct( ILBFactory $lbFactory,
		ExtensionRegistry $extensionRegistry,
		string $localWikiId
	) {
		$this->lbFactory = $lbFactory;
		$this->extensionRegistry = $extensionRegistry;
		$this->localWikiId = $localWikiId;
	}

	/**
	 * Gets preliminary data for users to start an investigation
	 *
	 * @param User[]|string[] $users
	 * @param mixed[] $pageInfo Information for pagination (unused if CentralAuth is
	 *  not available).
	 *  - includeOffset: (bool) Include the row specified as the offset
	 *  - offsets: (string[]|bool) Starting row, defined by user name and wiki, or
	 *    false if there is no offset.
	 *  - limit: (int) Maximum number of rows
	 *  - order: (bool) IndexPager::QUERY_ASCENDING or IndexPager::QUERY_DESCENDING
	 * @return array
	 */
	public function getPreliminaryData( array $users, array $pageInfo ) : array {
		if ( !$users ) {
			return [];
		}

		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) && $pageInfo ) {
			$data = $this->getGlobalUserData( $users, $pageInfo );
		} else {
			$data = $this->getLocalUserData( $users );
		}
		return $data;
	}

	/**
	 * Get the CentralAuth replica database. For mocking in tests.
	 *
	 * @return IDatabase
	 */
	protected function getCentralAuthDB() : IDatabase {
		return \CentralAuthUtils::getCentralReplicaDB();
	}

	/**
	 * Get the preliminary data, if CentralAuth is available.
	 *
	 * @param User[]|string[] $users
	 * @param mixed[] $pageInfo Information for pagination. See getPreliminaryData.
	 * @return array
	 */
	protected function getGlobalUserData( array $users, array $pageInfo ) : array {
		$db = $this->getCentralAuthDB();

		$userInfo = [];
		$userInfo['tables'] = 'localuser';
		$userInfo['fields'] = [
			'lu_name',
			'lu_wiki',
			'lu_name_wiki' => $db->buildConcat( [ 'lu_name', '\'>\'', 'lu_wiki' ] ),
		];

		$userInfo['conds']['lu_name'] = array_map( 'strval', $users );

		$offsets = $pageInfo['offsets'];
		if ( $offsets && isset( $offsets['name'] ) && isset( $offsets['wiki'] ) ) {
			$pageInfo['offsets']['name'] = $db->addQuotes( $offsets['name'] );
			$pageInfo['offsets']['wiki'] = $db->addQuotes( $offsets['wiki'] );
		}

		$queryInfo = $this->buildGlobalUserQueryInfo( $userInfo, $pageInfo );
		$rows = $db->select(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			$queryInfo['fname'],
			$queryInfo['options']
		);

		return $this->getLocalDataForGlobalUser( $rows );
	}

	/**
	 * Build variables to use by the database wrapper. See also
	 * IndexPager::buildQueryInfo.
	 *
	 * @param mixed[] $userInfo
	 * @param mixed[] $pageInfo Information for pagination. See getPreliminaryData.
	 * @return array
	 */
	protected function buildGlobalUserQueryInfo( array $userInfo, array $pageInfo ) : array {
		$conds = $userInfo['conds'];
		$options = [];
		$sortColumns = [ 'lu_name', 'lu_wiki' ];

		if ( $pageInfo['order'] === IndexPager::QUERY_ASCENDING ) {
			$options['ORDER BY'] = $sortColumns;
			$nameOperators = [ '>=', '>' ];
			$wikiOperator = $pageInfo['includeOffset'] ? '>=' : '>';
		} else {
			$orderBy = [];
			foreach ( $sortColumns as $col ) {
				$orderBy[] = $col . ' DESC';
			}
			$options['ORDER BY'] = $orderBy;
			$nameOperators = [ '<=', '<' ];
			$wikiOperator = $pageInfo['includeOffset'] ? '<=' : '<';
		}

		if ( $pageInfo['offsets'] ) {
			$conds[] = 'lu_name' . $nameOperators[0] . $pageInfo['offsets']['name'];
			$conds[] = 'lu_name' . $nameOperators[1] . $pageInfo['offsets']['name'] .
				' OR lu_wiki' .	$wikiOperator . $pageInfo['offsets']['wiki'];
		}

		$options['LIMIT'] = intval( $pageInfo['limit'] );

		return [
			'tables' => $userInfo['tables'],
			'fields' => $userInfo['fields'],
			'conds' => $conds,
			'fname' => __METHOD__,
			'options' => $options,
		];
	}

	/**
	 * Get data from the about each user's account from each wiki.
	 *
	 * @param IResultWrapper $rows Accounts found by CentralAuth
	 * @return array
	 */
	protected function getLocalDataForGlobalUser( IResultWrapper $rows ) : array {
		$data = [];
		foreach ( $rows as $row ) {
			$rowData = $this->getUserData( $row->lu_name, $row->lu_wiki );
			$rowData['lu_name_wiki'] = $row->lu_name_wiki;
			$data[] = $rowData;
		}
		return $data;
	}

	/**
	 * Get the preliminary data, if CentralAuth is not available.
	 *
	 * @param User[]|string[] $users
	 * @return array
	 */
	protected function getLocalUserData( array $users ) : array {
		// No pagination if CentralAuth is not loaded. If investigations
		// are likely to involve very many users, we could paginate.
		$data = [];
		foreach ( $users as $user ) {
			$data[] = $this->getUserData( (string)$user, $this->localWikiId );
		}
		return $data;
	}

	/**
	 * @param string $username
	 * @param string $wikiId
	 * @return array
	 */
	private function getUserData( string $username, string $wikiId ): array {
		$db = $this->lbFactory->getMainLB( $wikiId )->getConnectionRef( DB_REPLICA, [], $wikiId );
		$fields = [
			'user_id', 'user_name', 'user_editcount', 'user_registration',
		];
		$conds = [ 'user_name' => $username ];
		$row = $db->selectRow( 'user', $fields, $conds, __METHOD__ );

		return [
			'id' => $row->user_id,
			'name' => $row->user_name,
			'registration' => $row->user_registration,
			'editcount' => $row->user_editcount,
			'blocked' => $this->isUserBlocked( $row->user_id, $db ),
			'groups' => $this->getUserGroups( $row->user_id, $db ),
			'wiki' => $wikiId,
		];
	}

	/**
	 * @param int $userId
	 * @param IDatabase $db Database connection
	 * @return string[]
	 */
	protected function getUserGroups( int $userId, IDatabase $db ): array {
		$groupMembership = UserGroupMembership::getMembershipsForUser( $userId, $db );
		return array_keys( $groupMembership );
	}

	/**
	 * @param int $userId
	 * @param IDatabase $db Database connection
	 * @return bool
	 */
	protected function isUserBlocked( int $userId, IDatabase $db ): bool {
		$blocks = $db->selectField(
			'ipblocks', '1', [ 'ipb_user' => $userId ], __METHOD__, [ 'LIMIT' => 1 ]
		);
		return $blocks > 0;
	}
}
