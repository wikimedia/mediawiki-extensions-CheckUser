'use strict';

const assert = require( 'assert' ),
	CheckUserLogPage = require( '../pageobjects/checkuserlog.page' );

describe( 'CheckUserLog', function () {
	it( 'Should display permission error to logged-out user', async function () {
		await CheckUserLogPage.open();

		assert( await CheckUserLogPage.hasPermissionErrors.isExisting() );
	} );
} );
