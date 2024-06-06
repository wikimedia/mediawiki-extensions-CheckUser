'use strict';

const assert = require( 'assert' ),
	LoginAsCheckUser = require( '../checkuserlogin' ),
	InvestigatePage = require( '../pageobjects/investigate.page' );

describe( 'Investigate', () => {
	describe( 'Without CheckUser user group', () => {
		it( 'Should display permission error to logged-out user', async () => {
			await InvestigatePage.open();

			assert( await InvestigatePage.hasPermissionErrors.isExisting() );
		} );
	} );
	describe( 'With CheckUser user group', () => {
		before( async () => {
			await LoginAsCheckUser.loginAsCheckUser();
			await InvestigatePage.open();
		} );
		it( 'Should show targets input', async () => {
			assert( await InvestigatePage.targetsInput.isExisting() );
		} );
		it( 'Should show duration selector', async () => {
			assert( await InvestigatePage.durationSelector.isExisting() );
		} );
		it( 'Should show reason field', async () => {
			assert( await InvestigatePage.reasonInput.isExisting() );
		} );
	} );
} );
