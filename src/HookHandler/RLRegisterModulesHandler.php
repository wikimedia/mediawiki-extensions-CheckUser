<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {
	private ExtensionRegistry $extensionRegistry;

	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->extensionRegistry = $extensionRegistry;
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

		if ( count( $modules ) ) {
			$resourceLoader->register( $modules );
		}
	}

}
