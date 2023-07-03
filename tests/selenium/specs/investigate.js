'use strict';

const assert = require( 'assert' ),
	LoginAsCheckUser = require( '../checkuserlogin' ),
	InvestigatePage = require( '../pageobjects/investigate.page' );

describe( 'Investigate', function () {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async function () {
			await InvestigatePage.open();

			assert( await InvestigatePage.hasPermissionErrors.isExisting() );
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await InvestigatePage.open();
		} );
		it( 'Should show targets input', async function () {
			assert( await InvestigatePage.targetsInput.isExisting() );
		} );
		it( 'Should show duration selector', async function () {
			assert( await InvestigatePage.durationSelector.isExisting() );
		} );
		it( 'Should show reason field', async function () {
			assert( await InvestigatePage.reasonInput.isExisting() );
		} );
	} );
} );
