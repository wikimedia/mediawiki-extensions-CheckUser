<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\GlobalContributions\SpecialGlobalContributions;
use MediaWiki\CheckUser\IPContributions\SpecialIPContributions;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\TempUser\TempUserConfig;

// The name of onSpecialPage_initList raises the following phpcs error. As the
// name is defined in core, this is an unavoidable issue and therefore the check
// is disabled.
//
// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

/**
 * Hook handler for the SpecialPage_initList hook
 */
class SpecialPageInitListHandler implements SpecialPage_initListHook {

	private TempUserConfig $tempUserConfig;

	public function __construct( TempUserConfig $tempUserConfig ) {
		$this->tempUserConfig = $tempUserConfig;
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->tempUserConfig->isKnown() ) {
			$list['IPContributions'] = [
				'class' => SpecialIPContributions::class,
				'services' => [
					'PermissionManager',
					'ConnectionProvider',
					'NamespaceInfo',
					'UserNameUtils',
					'UserNamePrefixSearch',
					'UserOptionsLookup',
					'UserFactory',
					'UserIdentityLookup',
					'DatabaseBlockStore',
					'CheckUserLookupUtils',
					'CheckUserIPContributionsPagerFactory',
				],
			];
			$list['GlobalContributions'] = [
				'class' => SpecialGlobalContributions::class,
				'services' => [
					"PermissionManager",
					"ConnectionProvider",
					"NamespaceInfo",
					"UserNameUtils",
					"UserNamePrefixSearch",
					"UserOptionsLookup",
					"UserFactory",
					"UserIdentityLookup",
					"DatabaseBlockStore",
					"CheckUserLookupUtils",
					"CheckUserGlobalContributionsPagerFactory"
				],
			];
		}

		return true;
	}
}
