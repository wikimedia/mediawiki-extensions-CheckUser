'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class LogoutPage extends Page {
	get submit() {
		return $( 'button[type="submit"]' );
	}

	open() {
		super.openTitle( 'Special:UserLogout' );
	}

	async logout() {
		await this.open();
		await this.submit.click();
	}
}

module.exports = new LogoutPage();
