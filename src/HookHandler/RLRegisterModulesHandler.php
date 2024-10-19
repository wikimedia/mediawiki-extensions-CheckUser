<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class RLRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {

	/**
	 * @inheritDoc
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'GuidedTour' ) ) {

			$dir = dirname( __DIR__, 2 ) . '/modules/';

			$modules = [
				'ext.guidedTour.tour.checkuserinvestigateform' => [
					'localBasePath' => $dir . 'ext.guidedTour.tour.checkuserinvestigateform',
					'remoteExtPath' => "CheckUser/modules",
					'scripts' => "checkuserinvestigateform.js",
					'dependencies' => 'ext.guidedTour',
					'messages' => [
						'checkuser-investigate-tour-targets-title',
						'checkuser-investigate-tour-targets-desc'
					]
				],
				'ext.guidedTour.tour.checkuserinvestigate' => [
					'localBasePath' => $dir . 'ext.guidedTour.tour.checkuserinvestigate',
					'remoteExtPath' => "CheckUser/module",
					'scripts' => 'checkuserinvestigate.js',
					'dependencies' => [ 'ext.guidedTour', 'ext.checkUser' ],
					'messages' => [
						'checkuser-investigate-tour-useragents-title',
						'checkuser-investigate-tour-useragents-desc',
						'checkuser-investigate-tour-addusertargets-title',
						'checkuser-investigate-tour-addusertargets-desc',
						'checkuser-investigate-tour-filterip-title',
						'checkuser-investigate-tour-filterip-desc',
						'checkuser-investigate-tour-block-title',
						'checkuser-investigate-tour-block-desc',
						'checkuser-investigate-tour-copywikitext-title',
						'checkuser-investigate-tour-copywikitext-desc',
					],
				]
			];

			$resourceLoader->register( $modules );
		}
	}

}
