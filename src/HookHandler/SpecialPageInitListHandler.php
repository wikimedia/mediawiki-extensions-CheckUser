<?php

namespace MediaWiki\CheckUser\HookHandler;

use Config;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\CheckUser\Investigate\SpecialInvestigateBlock;
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
		if ( $this->config->get( 'CheckUserEnableSpecialInvestigate' ) ) {
			$list['Investigate'] = [
				'class' => SpecialInvestigate::class,
				'services' => [
					'LinkRenderer',
					'ContentLanguage',
					'UserOptionsManager',
					'CheckUserPreliminaryCheckPagerFactory',
					'CheckUserComparePagerFactory',
					'CheckUserTimelinePagerFactory',
					'CheckUserTokenQueryManager',
					'CheckUserDurationManager',
					'CheckUserEventLogger',
					'CheckUserGuidedTourLauncher',
					'CheckUserHookRunner',
					'PermissionManager',
					'CheckUserLogService',
					'UserIdentityLookup',
					'UserFactory',
				],
			];

			$list['InvestigateBlock'] = [
				'class' => SpecialInvestigateBlock::class,
				'services' => [
					'BlockUserFactory',
					'BlockPermissionCheckerFactory',
					'PermissionManager',
					'TitleFormatter',
					'UserFactory',
					'CheckUserEventLogger',
				]
			];
		}

		return true;
	}
}
