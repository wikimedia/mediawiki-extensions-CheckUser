'use strict';

const assert = require( 'assert' ),
	VersionPage = require( '../pageobjects/version.page' );

describe( 'CheckUser on Version page', function () {
	it( 'CheckUser is listed in the version page under the special page category', async function () {
		await VersionPage.open();

		assert( await VersionPage.checkuserExtension.isExisting() );
	} );
} );
