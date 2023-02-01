'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class CheckUserLogPage extends Page {
	get hasPermissionErrors() { return $( '.permissions-errors' ); }

	async open() {
		await super.openTitle( 'Special:CheckUserLog' );
	}
}

module.exports = new CheckUserLogPage();
