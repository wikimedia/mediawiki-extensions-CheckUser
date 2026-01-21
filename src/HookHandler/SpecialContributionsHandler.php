<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Hook\ContribsPager__getQueryInfoHook;
use MediaWiki\Hook\SpecialContributions__getForm__filtersHook;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class SpecialContributionsHandler implements
	SpecialContributionsBeforeMainOutputHook,
	SpecialContributions__getForm__filtersHook,
	ContribsPager__getQueryInfoHook
{
	use ContributionsRangeTrait;

	public function __construct(
		private readonly TempUserConfig $tempUserConfig,
		private readonly CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly DatabaseBlockStore $databaseBlockStore,
	) {
	}

	/** @inheritDoc */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( !$sp->getUser()->isRegistered() ) {
			return;
		}

		if ( $this->shouldShowCountFromAssociatedIPs( $user, $sp ) ) {
			$this->addCountFromAssociatedIPsSubtitle( $user, $sp );
		}
		if ( $this->shouldShowCountFromThisIP( $user, $sp ) ) {
			$this->addCountFromThisIPSubtitle( $user->getName(), $sp );
		}
	}

	private function shouldShowCountFromAssociatedIPs( User $user, ContributionsSpecialPage $sp ): bool {
		if ( !$user->isRegistered() || !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			return false;
		}

		// If the user is hidden, and the authority doesn't have the `hideuser` right,
		// then pretend that the user doesn't exist
		if ( $user->isHidden() && !$sp->getAuthority()->isAllowed( 'hideuser' ) ) {
			return false;
		}

		return true;
	}

	private function shouldShowCountFromThisIP( UserIdentity $user, ContributionsSpecialPage $sp ): bool {
		if (
			!$this->tempUserConfig->isKnown() ||
			$user->isRegistered() ||
			!$this->isValidIPOrQueryableRange( $user->getName(), $sp->getConfig() )
		) {
			return false;
		}

		// The permissions are checked by CheckUserTemporaryAccountsByIPLookup, from addCountFromThisIPSubtitle
		return true;
	}

	private function addCountFromAssociatedIPsSubtitle( UserIdentity $tempUser, ContributionsSpecialPage $sp ) {
		// 101 is the maximum number of accounts we care about as defined by T412212
		[ $bucketRangeStart, $bucketRangeEnd ] = $this->checkUserTemporaryAccountsByIPLookup->getBucketedCount(
			$this->checkUserTemporaryAccountsByIPLookup
				->getAggregateActiveTempAccountCount( $tempUser, 101 )
		);

		$bucketMsgKey = 'checkuser-temporary-account-bucketcount-';
		if ( $bucketRangeStart === $bucketRangeEnd ) {
			if ( $bucketRangeStart === 0 ) {
				$bucketMsgKey .= 'min';
			} else {
				$bucketMsgKey .= 'max';
			}
		} else {
			$bucketMsgKey .= 'range';
		}

		// Uses:
		// * checkuser-temporary-account-bucketcount-min
		// * checkuser-temporary-account-bucketcount-range
		// * checkuser-temporary-account-bucketcount-max
		$bucketMsg = $sp->msg( $bucketMsgKey )
			->numParams( $bucketRangeStart, $bucketRangeEnd )
			->text();

		$msg = $sp->msg( 'checkuser-userinfocard-temporary-account-bucketcount' )
			->params( $bucketMsg );
		$out = $sp->getOutput();
		$out->addSubtitle( $msg );
	}

	private function addCountFromThisIPSubtitle( string $ip, ContributionsSpecialPage $sp ) {
		$limit = 100;
		// We're not displaying the account names, so we don't log it
		$accountsStatus = $this->checkUserTemporaryAccountsByIPLookup->get( $ip, $sp->getAuthority(), false, $limit );
		if ( !$accountsStatus->isGood() ) {
			// Insufficient permissions
			return;
		}
		$accounts = $accountsStatus->getValue();

		$excludeHiddenAccounts = !$sp->getAuthority()->isAllowed( 'hideuser' );
		$blockedAccounts = $this->countBlockedAccounts( $accounts, $excludeHiddenAccounts );
		$numAccounts = count( $accounts );

		if ( $numAccounts < $limit ) {
			if ( $blockedAccounts > 0 ) {
				$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip-with-blocked' )
					->numParams( $numAccounts, $blockedAccounts );
			} else {
				$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip' )
					->numParams( $numAccounts );
			}
		} else {
			// Show version with blocks, even if it's going to be "0+ blocks" - to signify we're uncertain
			$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip-with-blocked' )->params(
				$sp->msg( 'checkuser-temporary-account-bucketcount-max', $numAccounts ),
				$sp->msg( 'checkuser-temporary-account-bucketcount-max', $blockedAccounts ),
			);
		}
		$out = $sp->getOutput();
		$out->addSubtitle( $msg );
	}

	private function countBlockedAccounts( array $userNames, bool $excludeHidden ): int {
		if ( !$userNames ) {
			return 0;
		}

		$users = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserNames( $userNames )
			->caller( __METHOD__ )
			->fetchUserIdentities();
		$userIds = [];
		foreach ( $users as $user ) {
			$userIds[] = $user->getId();
		}

		$conds = [ 'bt_user' => $userIds ];
		if ( $excludeHidden ) {
			$conds['bl_deleted'] = 0;
		}
		$blocks = $this->databaseBlockStore->newListFromConds( $conds );
		$blocksByUser = [];
		foreach ( $blocks as $block ) {
			$blocksByUser[$block->getTargetUserIdentity()->getId()][] = $block;
		}

		return count( $blocksByUser );
	}

	/** @inheritDoc */
	public function onSpecialContributions__getForm__filters( $sp, &$filters ) {
		$target = $sp->getRequest()->getText( 'target' );

		if ( !$this->canShowRelatedTemporaryAccounts( $sp->getName(), $target, $sp->getUser() ) ) {
			return;
		}

		$filters[] = [
			'type' => 'check',
			'label-message' => 'checkuser-contributions-filters-related-temporary-accounts',
			'name' => 'showRelatedTemporaryAccounts',
		];
	}

	/** @inheritDoc */
	public function onContribsPager__getQueryInfo( $pager, &$queryInfo ) {
		$title = $pager->getContext()->getTitle();
		if ( !$title ) {
			return;
		}
		$pageName = $this->specialPageFactory->resolveAlias( $title->getDBKey() )[0];
		if ( !$pageName ) {
			return;
		}

		$request = $pager->getContext()->getRequest();
		$target = $request->getText( 'target' );

		if ( !$this->canShowRelatedTemporaryAccounts(
			$pageName,
			$target,
			$pager->getUser()
		) ) {
			return;
		}

		if ( !isset( $queryInfo['conds']['actor_name'] ) ) {
			// This shouldn't happen, but adding in related targets when there isn't a primary
			// target set wouldn't make sense, so return early.
			return;
		}

		if ( $request->getBool( 'showRelatedTemporaryAccounts' ) ) {
			$targetUser = $this->userIdentityLookup->getUserIdentityByName(
				$queryInfo['conds']['actor_name']
			);

			if ( !$targetUser ) {
				return;
			}

			$status = $this->checkUserTemporaryAccountsByIPLookup
				->getActiveTempAccountNames( $pager->getUser(), $targetUser );
			if ( !$status->isGood() ) {
				return;
			}

			$queryInfo['conds']['actor_name'] = [ $target, ...$status->getValue() ];
		}
	}

	private function canShowRelatedTemporaryAccounts(
		string $pageName,
		string $target,
		Authority $authority
	): bool {
		if ( $pageName !== 'Contributions' ) {
			return false;
		}

		if ( !$this->tempUserConfig->isTempName( $target ) ) {
			return false;
		}

		$status = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$authority
		);
		if ( !$status->isGood() ) {
			return false;
		}

		return true;
	}
}
