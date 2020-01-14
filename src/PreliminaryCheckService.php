<?php
namespace MediaWiki\CheckUser;

use CentralAuthUser;
use ExtensionRegistry;
use User;
use UserGroupMembership;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILBFactory;

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
	 * @param User[] $users
	 * @return array
	 */
	public function getPreliminaryData( array $users ): array {
		$data = [];
		foreach ( $users as $user ) {
			$attachedWikis = $this->getAttachedWikis( $user );

			foreach ( $attachedWikis as $wikiId ) {
				$data[$user->getName()][$wikiId] = $this->getUserData( $user->getName(), $wikiId );
			}
		}

		return $data;
	}

	/**
	 * @param User $user
	 * @return array
	 */
	private function getAttachedWikis( User $user ): array {
		$attachedWikis = [ $this->localWikiId ];

		$globalUser = $this->getGlobalUser( $user );

		// $globalUser could be null if CentalAuth is not enabled
		if ( $globalUser && $globalUser->exists() ) {
			if ( $globalUser->exists() ) {
				$attachedWikis = $globalUser->listAttached();
			}
		}

		return $attachedWikis;
	}

	/**
	 * @param User $user
	 * @return CentralAuthUser|null
	 */
	protected function getGlobalUser( User $user ): ?CentralAuthUser {
		$globalUser = null;
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			// work with central auth
			$globalUser = CentralAuthUser::getInstance( $user );
		}

		return $globalUser;
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
