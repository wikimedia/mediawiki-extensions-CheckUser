'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	LocalSettingsSetup = require( './LocalSettingsSetup' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',
	maxInstances: 5,
	async onPrepare() {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	},
	async onComplete() {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
