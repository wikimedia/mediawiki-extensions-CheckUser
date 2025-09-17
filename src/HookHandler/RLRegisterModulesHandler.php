<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly HookRunner $hookRunner,
		private readonly Config $config,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$dir = dirname( __DIR__, 2 ) . '/modules/';
		$modules = [];

		if ( $this->extensionRegistry->isLoaded( 'IPInfo' ) ) {
			$modules[ 'ext.checkUser.ipInfo.hooks' ] = [
				'localBasePath' => $dir . 'ext.checkUser.ipInfo.hooks',
				'remoteExtPath' => "CheckUser/modules",
				'scripts' => [
					'infobox.js',
					'init.js'
				],
				'messages' => [
					'checkuser-ipinfo-global-contributions-label',
					'checkuser-ipinfo-global-contributions-tooltip',
					'checkuser-ipinfo-global-contributions-value',
					'checkuser-ipinfo-global-contributions-url-text',
				]
			];
		}

		$messages = [
			'checkuser-suggestedinvestigations-change-status-dialog-cancel-btn',
			'checkuser-suggestedinvestigations-change-status-dialog-submit-btn',
			'checkuser-suggestedinvestigations-change-status-dialog-close-label',
			'checkuser-suggestedinvestigations-change-status-dialog-text',
			'checkuser-suggestedinvestigations-change-status-dialog-title',
			'checkuser-suggestedinvestigations-change-status-dialog-status-list-header',
			'checkuser-suggestedinvestigations-change-status-dialog-status-reason-header',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-description-resolved',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-description-invalid',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-resolved',
			'checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-invalid',
			'checkuser-suggestedinvestigations-status-open',
			'checkuser-suggestedinvestigations-status-resolved',
			'checkuser-suggestedinvestigations-status-invalid',
			'checkuser-suggestedinvestigations-status-description-invalid',
			'checkuser-suggestedinvestigations-status-reason-default-invalid',
			'checkuser-suggestedinvestigations-user-showmore',
			'checkuser-suggestedinvestigations-user-showless',
			'checkuser-suggestedinvestigations-risk-signals-popover-title',
			'checkuser-suggestedinvestigations-risk-signals-popover-body-intro',
			'checkuser-suggestedinvestigations-risk-signals-popover-close-label',
			'checkuser-suggestedinvestigations-risk-signals-popover-open-label',
		];

		if ( $this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			$signals = [];
			$this->hookRunner->onCheckUserSuggestedInvestigationsGetSignals( $signals );
			foreach ( $signals as $signal ) {
				$messages[] = 'checkuser-suggestedinvestigations-risk-signals-popover-body-' . $signal;
				$messages[] = 'checkuser-suggestedinvestigations-signal-' . $signal;
			}
		}

		// We have to define ext.checkUser.suggestedInvestigations unconditionally as it's used by QUnit tests
		// where we cannot enable the feature before the tests run
		$modules['ext.checkUser.suggestedInvestigations'] = [
			'localBasePath' => $dir . 'ext.checkUser.suggestedInvestigations',
			'remoteExtPath' => 'CheckUser/modules/ext.checkUser.suggestedInvestigations',
			'packageFiles' => [
				'index.js',
				'Constants.js',
				'rest.js',
				'utils.js',
				'components/ChangeInvestigationStatusDialog.vue',
				'components/CharacterLimitedTextInput.vue',
				'components/SignalsPopover.vue',
			],
			'messages' => $messages,
			'dependencies' => [
				'@wikimedia/codex',
				'jquery.lengthLimit',
				'mediawiki.api',
				'mediawiki.base',
				'mediawiki.jqueryMsg',
				'mediawiki.language',
				'mediawiki.String',
				'vue',
			],
		];

		if ( count( $modules ) ) {
			$resourceLoader->register( $modules );
		}
	}

}
