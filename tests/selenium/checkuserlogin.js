'use strict';

const Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	MWBot = require( 'mwbot' );

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
		await this.assignCheckUserGroup( adminBot, checkUserAccountDetails.username );
	}

	/**
	 * Assigns the checkuser group to the given username.
	 *
	 * @param {MWBot} adminBot
	 * @param {string} username
	 */
	async assignCheckUserGroup( adminBot, username ) {
		return new Promise( ( resolve, reject ) => {
			adminBot.request( {
				action: 'query',
				meta: 'tokens',
				type: 'userrights'
			} ).then( ( response ) => {
				if (
					response.query &&
					response.query.tokens &&
					response.query.tokens.userrightstoken
				) {
					adminBot.request( {
						action: 'userrights',
						user: username,
						token: response.query.tokens.userrightstoken,
						add: 'checkuser',
						reason: 'Selenium testing'
					} ).then( ( userRightsResponse ) => {
						if (
							userRightsResponse.userrights &&
							userRightsResponse.userrights.user &&
							userRightsResponse.userrights.added &&
							userRightsResponse.userrights.added.length === 1 &&
							userRightsResponse.userrights.added[ 0 ] === 'checkuser'
						) {
							return resolve();
						} else {
							return reject( new Error( 'Unable to assign checkuser group' ) );
						}
					} ).catch( ( err ) => reject( err ) );
				} else {
					return reject( new Error( 'Could not get userrights token' ) );
				}
			} ).catch( ( err ) => reject( err ) );
		} );
	}
}

module.exports = new LoginAsCheckUser();
