'use strict';

const assert = require( 'assert' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	UserRightsPageForCheckUserTests = require( '../pageobjects/userrightscheckuser.page' ),
	CheckUserPage = require( '../pageobjects/checkuser.page' );

describe( 'CheckUser', function () {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async function () {
			await CheckUserPage.open();

			assert( await CheckUserPage.hasPermissionErrors.isExisting() );
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginPage.loginAdmin();
			await UserRightsPageForCheckUserTests.grantCheckUserToUser(
				process.env.MEDIAWIKI_USER
			);
			await CheckUserPage.open();
		} );
		describe( 'Verify checkuser can make checks:', () => {
			it( 'Should show target input', async function () {
				assert( await CheckUserPage.checkTarget.isExisting() );
			} );
			it( 'Should show checkuser radios', async function () {
				assert( await CheckUserPage.checkTypeRadios.isExisting() );
				assert( await CheckUserPage.getIPsCheckTypeRadio.isExisting() );
				assert( await CheckUserPage.getEditsCheckTypeRadio.isExisting() );
				assert( await CheckUserPage.getUsersCheckTypeRadio.isExisting() );
			} );
			it( 'Should show duration selector', async function () {
				// Check the duration selector exists
				assert( await CheckUserPage.durationSelector.isExisting() );
			} );
			it( 'Should show check reason input', async function () {
				assert( await CheckUserPage.checkReasonInput.isExisting() );
			} );
			it( 'Should show submit button', async function () {
				assert( await CheckUserPage.submit.isExisting() );
			} );
			it( 'Should show CIDR form before check is run', async function () {
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
			it( 'Should be able to run \'Get IPs\' check', async function () {
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
			it( 'Should be able to run \'Get edits\' check', async function () {
				await CheckUserPage.open();
				await CheckUserPage.getEditsCheckTypeRadio.click();
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.submit.click();
				browser.waitUntil( () => {
					browser.execute( () => browser.document.readyState === 'complete' );
				}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
				assert( await CheckUserPage.getEditsResults.isExisting() );
				assert( await CheckUserPage.checkUserHelper.isExisting() );
				assert( await CheckUserPage.cidrForm.isExisting() );
			} );
			it( 'Should be able to run \'Get users\' check', async function () {
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
		after( async () => {
			await UserRightsPageForCheckUserTests.removeCheckUserFromUser(
				process.env.MEDIAWIKI_USER
			);
		} );
	} );
} );
