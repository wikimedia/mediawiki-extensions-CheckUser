'use strict';

const assert = require( 'assert' ),
	CheckUserLogPage = require( '../pageobjects/checkuserlog.page' );

describe( 'CheckUserLog', function () {
	it( 'Should display permission error to logged-out user', function () {
		CheckUserLogPage.open();

		assert( CheckUserLogPage.hasPermissionErrors.isExisting() );
	} );
} );
