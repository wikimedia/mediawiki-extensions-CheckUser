'use strict';

const assert = require( 'assert' ),
	LoginAsCheckUser = require( '../checkuserlogin' ),
	CheckUserLogPage = require( '../pageobjects/checkuserlog.page' );

describe( 'CheckUserLog', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await CheckUserLogPage.open();

			assert( await CheckUserLogPage.hasPermissionErrors.isExisting() );
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await CheckUserLogPage.open();
		} );
		describe( 'Verify checkuser can interact with the CheckUser log', () => {
			it( 'Should show target input', async () => {
				assert( await CheckUserLogPage.targetInput.isExisting() );
			} );
			it( 'Should show initiator input', async () => {
				assert( await CheckUserLogPage.initiatorInput.isExisting() );
			} );
			it( 'Should show start date selector', async () => {
				assert( await CheckUserLogPage.startDateSelector.isExisting() );
			} );
			it( 'Should show end date selector', async () => {
				assert( await CheckUserLogPage.endDateSelector.isExisting() );
			} );
			it( 'Should show search button', async () => {
				assert( await CheckUserLogPage.search.isExisting() );
			} );
			it( 'Should be able to use the filters to search', async () => {
				// @todo check if the filters had any effect?
				await CheckUserLogPage.initiatorInput.setValue( process.env.MEDIAWIKI_USER );
				await CheckUserLogPage.initiatorInput.click();
				// Initiator input will be missing if the request failed.
				assert( await CheckUserLogPage.initiatorInput.isExisting() );
			} );
		} );
	} );
} );
