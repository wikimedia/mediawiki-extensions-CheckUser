<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CheckUser\Services\UserInfoCardButtonRenderer;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;

readonly class UserInfoCardVariablesHandler implements MakeGlobalVariablesScriptHook {
	public function __construct( private UserInfoCardButtonRenderer $buttonRenderer ) {
	}

	/**
	 * Set the UserInfoCard config vars if checkuser-userinfocard-enable is true
	 * @inheritDoc
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		if ( !$this->buttonRenderer->isUicEnabledForUser( $out->getUser() ) ) {
			return;
		}

		$config = $out->getConfig();

		if ( $config->has( 'GEUserImpactMaxEdits' ) ) {
			$vars['wgCheckUserGEUserImpactMaxEdits'] =
				$config->get( 'GEUserImpactMaxEdits' );
		}

		if ( $config->has( 'GEUserImpactMaxThanks' ) ) {
			$vars['wgCheckUserGEUserImpactMaxThanks'] =
				$config->get( 'GEUserImpactMaxThanks' );
		}

		$vars['wgCheckUserEnableUserInfoCardInstrumentation'] =
			$config->get( 'CheckUserEnableUserInfoCardInstrumentation' );

		$vars['wgCheckUserUserInfoCardShowXToolsLink'] =
			$config->get( 'CheckUserUserInfoCardShowXToolsLink' );
	}
}
