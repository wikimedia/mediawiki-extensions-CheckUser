'use strict';

const Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	Util = require( 'wdio-mediawiki/Util' ),
	MWBot = require( 'mwbot' );

class LoginAsCheckUser {
	async loginAsCheckUser() {
		await LoginPage.loginAdmin();
		const username = Util.getTestString( 'User-' );
		const password = Util.getTestString();
		const adminBot = await Api.bot();
		await Api.createAccount( adminBot, username, password );
		await this.assignCheckUserGroup( adminBot, username );
		await LoginPage.login( username, password );
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
					} ).catch( ( err ) => {
						return reject( err );
					} );
				} else {
					return reject( new Error( 'Could not get userrights token' ) );
				}
			} ).catch( ( err ) => {
				return reject( err );
			} );
		} );
	}
}

module.exports = new LoginAsCheckUser();
