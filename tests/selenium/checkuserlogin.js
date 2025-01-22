'use strict';

const Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' );

class LoginAsCheckUser {
	/**
	 * Returns the password and username for the account that has the checkuser group.
	 *
	 * @return {{password: string, username: string}}
	 */
	getCheckUserAccountDetails() {
		// Use the default username unless the config defines one.
		let username = 'CheckUserAccount';
		if ( browser.options.checkUserAccountUsername ) {
			username = browser.options.checkUserAccountUsername;
		}
		// Use the default password unless the config defines one.
		let password = 'CheckUserAccountPassword';
		if ( browser.options.checkUserAccountUsername ) {
			password = browser.options.checkUserAccountPassword;
		}
		return { username: username, password: password };
	}

	/**
	 * Logs in to the account created for CheckUser by
	 * this.createCheckUserAccount.
	 *
	 * @return {Promise<void>}
	 */
	async loginAsCheckUser() {
		const checkUserAccountDetails = this.getCheckUserAccountDetails();
		await LoginPage.login( checkUserAccountDetails.username, checkUserAccountDetails.password );
	}

	/**
	 * Creates the account and assigns it the checkuser group.
	 *
	 * @return {Promise<void>}
	 */
	async createCheckUserAccount() {
		const adminBot = await Api.bot();
		const checkUserAccountDetails = this.getCheckUserAccountDetails();
		await Api.createAccount(
			adminBot,
			checkUserAccountDetails.username,
			checkUserAccountDetails.password
		);
		await Api.addUserToGroup( adminBot, checkUserAccountDetails.username, 'checkuser' );
	}
}

module.exports = new LoginAsCheckUser();
