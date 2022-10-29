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
		describe( 'Verify checkuser can make check', () => {
			it( 'Should show target input', async function () {
				assert( await CheckUserPage.checkTarget.isExisting() );
			} );
			it( 'Should show checkuser radios input', async function () {
				// @todo test that the radios show the right options.
				assert( await CheckUserPage.checkUserRadios.isExisting() );
			} );
			it( 'Should show duration selector', async function () {
				// @todo test that the durations are correct
				assert( await CheckUserPage.durationSelector.isExisting() );
			} );
			it( 'Should show check reason input', async function () {
				assert( await CheckUserPage.checkReasonInput.isExisting() );
			} );
			it( 'Should show submit button', async function () {
				assert( await CheckUserPage.checkUserSubmit.isExisting() );
			} );
			it( 'Should be able to run check', async function () {
				await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
				await CheckUserPage.checkUserSubmit.click();
				assert( await CheckUserPage.checkUserResults.isExisting() );
			} );
		} );
		after( async () => {
			await UserRightsPageForCheckUserTests.removeCheckUserFromUser(
				process.env.MEDIAWIKI_USER
			);
		} );
	} );
} );
