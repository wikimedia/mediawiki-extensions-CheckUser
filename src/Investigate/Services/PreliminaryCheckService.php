<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use ExtensionRegistry;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentityValue;
use stdClass;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILBFactory;
use Wikimedia\Rdbms\IResultWrapper;

class PreliminaryCheckService {
	/** @var ILBFactory */
	private $lbFactory;

	/** @var UserGroupManagerFactory */
	private $userGroupManagerFactory;

	/** @var string */
	private $localWikiId;

	/** @var ExtensionRegistry */
	private $extensionRegistry;

	/**
	 * @param ILBFactory $lbFactory
	 * @param ExtensionRegistry $extensionRegistry
	 * @param UserGroupManagerFactory $userGroupManagerFactory
	 * @param string $localWikiId
	 */
	public function __construct( ILBFactory $lbFactory,
		ExtensionRegistry $extensionRegistry,
		UserGroupManagerFactory $userGroupManagerFactory,
		string $localWikiId
	) {
		$this->lbFactory = $lbFactory;
		$this->extensionRegistry = $extensionRegistry;
		$this->userGroupManagerFactory = $userGroupManagerFactory;
		$this->localWikiId = $localWikiId;
	}

	/**
	 * Get the information needed to build a query for the preliminary check. The
	 * query will be different depending on whether CentralAuth is available. Any
	 * information for paginating is handled in the PreliminaryCheckPager.
	 *
	 * @param User[] $users
	 * @return array
	 */
	public function getQueryInfo( array $users ): array {
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$info = $this->getGlobalQueryInfo( $users );
		} else {
			$info = $this->getLocalQueryInfo( $users );
		}
		return $info;
	}

	/**
	 * Get the information for building a query if CentralAuth is available.
	 *
	 * @param User[] $users
	 * @return array
	 */
	protected function getGlobalQueryInfo( array $users ): array {
		return [
			'tables' => 'localuser',
			'fields' => [
				'lu_name',
				'lu_wiki',
			],
			'conds' => $this->buildUserConds( $users, 'lu_name' ),
		];
	}

	/**
	 * Get the information for building a query if CentralAuth is unavailable.
	 *
	 * @param User[]|string[] $users
	 * @return array
	 */
	protected function getLocalQueryInfo( array $users ): array {
		return [
			'tables' => 'user',
			'fields' => [
				'user_name',
				'user_id',
				'user_editcount',
				'user_registration',
			],
			'conds' => $this->buildUserConds( $users, 'user_name' ),
		];
	}

	/**
	 * @param User[] $users
	 * @param string $field
	 * @return array
	 */
	protected function buildUserConds( array $users, string $field ): array {
		if ( !$users ) {
			return [ 0 ];
		}
		return [ $field => array_map( 'strval', $users ) ];
	}

	/**
	 * Get the replica database of a local wiki, given a wiki ID.
	 *
	 * @param string $wikiId
	 * @return IDatabase
	 */
	protected function getLocalDb( $wikiId ): IDatabase {
		return $this->lbFactory->getMainLB( $wikiId )->getConnectionRef( DB_REPLICA, [], $wikiId );
	}

	/**
	 * Perform additional queries to get the required data that is not returned
	 * by the pager's query. (The pager performs the query that is used for
	 * pagination.)
	 *
	 * @param IResultWrapper $rows
	 * @return array
	 */
	public function preprocessResults( IResultWrapper $rows ): array {
		$data = [];
		foreach ( $rows as $row ) {
			if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
				$localRow = $this->getLocalUserData( $row->lu_name, $row->lu_wiki );
				$data[] = $this->getAdditionalLocalData( $localRow, $row->lu_wiki );
			} else {
				$data[] = $this->getAdditionalLocalData( $row, $this->localWikiId );
			}
		}
		return $data;
	}

	/**
	 * Get basic user information for a given user's account on a given wiki.
	 *
	 * @param string $username
	 * @param string $wikiId
	 * @return stdClass|bool
	 */
	public function getLocalUserData( string $username, string $wikiId ) {
		$db = $this->getLocalDb( $wikiId );
		$queryInfo = $this->getLocalQueryInfo( [ $username ] );
		return $db->selectRow(
			$queryInfo['tables'],
			$queryInfo['fields'],
			$queryInfo['conds'],
			__METHOD__
		);
	}

	/**
	 * Get blocked status and user groups for a given user's account on a
	 * given wiki.
	 *
	 * @param stdClass|bool $row
	 * @param string $wikiId
	 * @return array
	 */
	protected function getAdditionalLocalData( $row, string $wikiId ): array {
		$db = $this->getLocalDb( $wikiId );

		return [
			'id' => $row->user_id,
			'name' => $row->user_name,
			'registration' => $row->user_registration,
			'editcount' => $row->user_editcount,
			'blocked' => $this->isUserBlocked( $row->user_id, $db ),
			'groups' => $this->userGroupManagerFactory
				->getUserGroupManager( $wikiId )
				->getUserGroups(
					new UserIdentityValue( (int)$row->user_id, $row->user_name )
				),
			'wiki' => $wikiId,
		];
	}

	/**
	 * @param int $userId
	 * @param IDatabase $db Database connection
	 * @return bool
	 */
	protected function isUserBlocked( int $userId, IDatabase $db ): bool {
		// No need to use any other field than ipb_expiry
		// so no need to use DatabaseBlock::newFromRow
		$expiry = $db->selectField(
			'ipblocks',
			'ipb_expiry',
			[ 'ipb_user' => $userId ],
			__METHOD__
		);
		if ( $expiry ) {
			$blockObject = new DatabaseBlock;
			$blockObject->setExpiry( $db->decodeExpiry( $expiry ) );
			return !$blockObject->isExpired();
		} else {
			return false;
		}
	}
}
