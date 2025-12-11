<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class SpecialContributionsBeforeMainOutputHandler implements SpecialContributionsBeforeMainOutputHook {

	public function __construct(
		private readonly TempUserConfig $tempUserConfig,
		private readonly CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup
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
}
