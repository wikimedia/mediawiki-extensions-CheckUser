'use strict';

const assert = require( 'assert' ),
	VersionPage = require( '../pageobjects/version.page' );

describe( 'CheckUser on Version page', function () {
	it( 'CheckUser is listed in the version page under the special page category', function () {
		VersionPage.open();

		assert( VersionPage.checkuserExtension.isExisting() );
	} );
} );
