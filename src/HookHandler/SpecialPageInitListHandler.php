<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\GlobalContributions\SpecialGlobalContributions;
use MediaWiki\CheckUser\IPContributions\SpecialIPContributions;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
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

	private Config $config;
	private TempUserConfig $tempUserConfig;
	private ExtensionRegistry $extensionRegistry;

	public function __construct(
		Config $config,
		TempUserConfig $tempUserConfig,
		ExtensionRegistry $extensionRegistry
	) {
		$this->config = $config;
		$this->tempUserConfig = $tempUserConfig;
		$this->extensionRegistry = $extensionRegistry;
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
					'CheckUserIPContributionsPagerFactory',
					'CheckUserPermissionManager',
				],
			];
		}

		// Use of Special:GlobalContributions depends on:
		// - the user enabling IP reveal globally via GlobalPreferences
		// - CentralAuth being enabled to support cross-wiki lookups
		// It also requires temp users to be known to this wiki, or for there
		// to be a central wiki that Special:GlobalContributions redirects to.
		if (
			(
				$this->tempUserConfig->isKnown() ||
				$this->config->get( 'CheckUserGlobalContributionsCentralWikiId' )
			) &&
			$this->extensionRegistry->isLoaded( 'GlobalPreferences' ) &&
			$this->extensionRegistry->isLoaded( 'CentralAuth' )
		) {
			$list['GlobalContributions'] = [
				'class' => SpecialGlobalContributions::class,
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
					'CentralIdLookup',
					'CheckUserGlobalContributionsPagerFactory',
					'StatsFactory',
				],
			];
		}

		return true;
	}
}
