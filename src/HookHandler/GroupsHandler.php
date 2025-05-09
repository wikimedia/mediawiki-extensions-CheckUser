<?php
namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Hook\SpecialUserRightsChangeableGroupsHook;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class GroupsHandler implements SpecialUserRightsChangeableGroupsHook {
	private Config $config;
	private IConnectionProvider $dbProvider;
	private ExtensionRegistry $extensionRegistry;
	private UserGroupManager $userGroupManager;
	private UserEditTracker $userEditTracker;
	private UserRegistrationLookup $userRegistrationLookup;

	public function __construct(
		Config $config,
		IConnectionProvider $dbProvider,
		ExtensionRegistry $extensionRegistry,
		UserGroupManager $userGroupManager,
		UserEditTracker $userEditTracker,
		UserRegistrationLookup $userRegistrationLookup
	) {
		$this->config = $config;
		$this->dbProvider = $dbProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->userGroupManager = $userGroupManager;
		$this->userEditTracker = $userEditTracker;
		$this->userRegistrationLookup = $userRegistrationLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onSpecialUserRightsChangeableGroups(
		Authority $authority,
		UserIdentity $target,
		array $addableGroups,
		array &$unaddableGroups
	): void {
		$spec = $this->config->get( 'CheckUserGroupRequirements' );
		$groupsToCheck = array_intersect( array_keys( $spec ), $addableGroups );

		if ( count( $groupsToCheck ) === 0 ) {
			return;
		}

		$performerGroups = $this->userGroupManager->getUserGroups( $authority->getUser() );
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$centralUser = CentralAuthUser::getInstance( $authority->getUser() );
			if ( $centralUser->exists() && $centralUser->isAttached() ) {
				$performerGroups = array_merge( $performerGroups, $centralUser->getGlobalGroups() );
			}
		}

		// Get the target user's edit count and registration date. The target user may be local
		// or external. Note that the requirements are configured on the local wiki.
		if ( $target->getWikiId() === UserIdentity::LOCAL ) {
			$editCount = (int)$this->userEditTracker->getUserEditCount( $target );
			$registration = $this->userRegistrationLookup->getRegistration( $target );
		} else {
			$dbr = $this->dbProvider->getReplicaDatabase( $target->getWikiId() );
			$row = $dbr->newSelectQueryBuilder()
				->select( [ 'user_editcount', 'user_registration' ] )
				->from( 'user' )
				->where( [ 'user_name' => $target->getName() ] )
				->caller( __METHOD__ )
				->fetchRow();
			$editCount = $row->user_editcount;
			$registration = $row->user_registration;
		}

		foreach ( $groupsToCheck as $group ) {
			$groupSpec = $spec[$group];

			if (
				isset( $groupSpec['exemptGroups'] ) &&
				count( array_intersect( $performerGroups, $groupSpec['exemptGroups'] ) ) > 0
			) {
				// The performer is exempt from requirements checking
				continue;
			}

			// Treat the edit count as 0 if null is returned
			$editCountPasses = !isset( $groupSpec['edits'] ) || $editCount >= $groupSpec['edits'];

			// Treat the account age as meeting requirements if null is returned for the
			// registration, because that would imply a very old account.
			if ( $registration !== null ) {
				$now = (int)ConvertibleTimestamp::now( TS_UNIX );
				$accountAge = $now - (int)ConvertibleTimestamp::convert(
					TS_UNIX,
					$registration
				);
			}
			$accountAgePasses = !isset( $groupSpec['age'] ) ||
				!isset( $accountAge ) ||
				$accountAge >= $groupSpec['age'];

			if ( $editCountPasses && $accountAgePasses ) {
				// The target meets all requirements that are set
				continue;
			}

			$unaddableGroups[$group] = $groupSpec['reason'] ?? 'checkuser-group-requirements';
		}
	}
}
