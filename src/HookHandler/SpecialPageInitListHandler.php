<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\IPContributions\SpecialIPContributions;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

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

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		$autoCreateTempUser = $this->config->get( 'AutoCreateTempUser' );
		if ( $autoCreateTempUser['enabled'] ) {
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
				],
			];
		}

		return true;
	}
}
