'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' );

describe( 'Create CheckUser account', () => {
	it( 'Create CheckUser account', async () => {
		await LoginAsCheckUser.createCheckUserAccount();
	} );
} );
