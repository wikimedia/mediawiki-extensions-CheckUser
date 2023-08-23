'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' );

describe( 'Create CheckUser account', function () {
	it( 'Create CheckUser account', async function () {
		await LoginAsCheckUser.createCheckUserAccount();
	} );
} );
