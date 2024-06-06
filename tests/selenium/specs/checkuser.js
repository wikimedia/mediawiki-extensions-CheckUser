'use strict';

const assert = require( 'assert' ),
	LoginAsCheckUser = require( '../checkuserlogin' ),
	CheckUserPage = require( '../pageobjects/checkuser.page' );

describe( 'CheckUser', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await CheckUserPage.open();

			assert( await CheckUserPage.hasPermissionErrors.isExisting() );
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await CheckUserPage.open();
		} );
		describe( 'Verify checkuser can make checks:', () => {
			it( 'Should show target input', async () => {
				assert( await CheckUserPage.checkTarget.isExisting() );
			} );
			it( 'Should show checkuser radios', async () => {
				assert( await CheckUserPage.checkTypeRadios.isExisting() );
				assert( await CheckUserPage.getIPsCheckTypeRadio.isExisting() );
				assert( await CheckUserPage.getActionsCheckTypeRadio.isExisting() );
				assert( await CheckUserPage.getUsersCheckTypeRadio.isExisting() );
			} );
			it( 'Should show duration selector', async () => {
				// Check the duration selector exists
				assert( await CheckUserPage.durationSelector.isExisting() );
			} );
			it( 'Should show check reason input', async () => {
				assert( await CheckUserPage.checkReasonInput.isExisting() );
			} );
			it( 'Should show submit button', async () => {
				assert( await CheckUserPage.submit.isExisting() );
			} );
			it( 'Should show CIDR form before check is run', async () => {
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
			it( 'Should be able to run \'Get IPs\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getIPsCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				browser.waitUntil( () => {
					browser.execute( () => browser.document.readyState === 'complete' );
				}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
				assert( await CheckUserPage.getIPsResults.isExisting() );
				// CheckUser helper should never be present on Get IPs
				assert( !( await CheckUserPage.checkUserHelper.isExisting() ) );
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
			it( 'Should be able to run \'Get actions\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getActionsCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				browser.waitUntil( () => {
					browser.execute( () => browser.document.readyState === 'complete' );
				}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
				assert( await CheckUserPage.getActionsResults.isExisting() );
				assert( await CheckUserPage.checkUserHelper.isExisting() );
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
			it( 'Should be able to run \'Get users\' check', async () => {
				await CheckUserPage.open();
				await CheckUserPage.getUsersCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( '127.0.0.1' );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				browser.waitUntil( () => {
					browser.execute( () => browser.document.readyState === 'complete' );
				}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
				assert( await CheckUserPage.getUsersResults.isExisting() );
				assert( await CheckUserPage.checkUserHelper.isExisting() );
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
		} );
	} );
} );
