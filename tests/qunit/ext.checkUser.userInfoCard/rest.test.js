'use strict';

const rest = require( 'ext.checkUser.userInfoCard/rest.js' );

let server;

QUnit.module( 'ext.checkUser.userInfoCard.rest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;

		this.sandbox.stub( mw.config, 'get' ).callsFake( ( key ) => {
			switch ( key ) {
				case 'wgUserLanguage':
					return 'en';
				case 'wgPageName':
					return 'Special:RecentChanges';
			}
		} );
	},
	afterEach: function () {
		server.restore();
	}
} ) );

QUnit.test( 'Test getUserInfo sends the current page (T435585)', ( assert ) => {
	let requestBody = null;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			requestBody = JSON.parse( request.requestBody );
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( { name: 'TestUser1' } )
			);
		} else if ( request.url.includes( 'type=csrf' ) ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'token' } }
			} ) );
		} else {
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );

	return rest.getUserInfo( 'TestUser1', 'rc' ).then( () => {
		assert.strictEqual(
			requestBody.sourcePage,
			'Special:RecentChanges',
			'Request body should contain the page which the card is opened from'
		);
		assert.strictEqual(
			requestBody.openedFrom,
			'rc',
			'Request body should contain the type of the place which the card is opened from'
		);
	} );
} );

// Other functionality is tested through UserCardView.test.js,
// so no need to repeat those tests here
QUnit.test( 'Test getUserInfo on bad CSRF token for first attempt', ( assert ) => {
	let csrfTokenUpdated = false;
	let retryBody = null;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			// If the CSRF token has been updated, then return a valid response. Otherwise, return a
			// response indicating that the CSRF token is invalid.
			if ( csrfTokenUpdated ) {
				retryBody = JSON.parse( request.requestBody );
				request.respond(
					200,
					{ 'Content-Type': 'application/json' },
					JSON.stringify( { name: 'TestUser1' } )
				);
			} else {
				request.respond(
					400,
					{ 'Content-Type': 'application/json' },
					JSON.stringify( { errorKey: 'rest-badtoken' } )
				);
			}
		} else if (
			request.url.includes( 'type=csrf' ) &&
			request.url.includes( 'meta=tokens' ) &&
			!csrfTokenUpdated
		) {
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
			csrfTokenUpdated = true;
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );

	// Call the method under test
	return rest.getUserInfo( 'TestUser1', 'rc' ).then( ( data ) => {
		assert.deepEqual(
			data,
			{ name: 'TestUser1' },
			'getUserInfo should still return good data after second API call'
		);
		assert.strictEqual(
			csrfTokenUpdated,
			true,
			'CSRF token should have been refreshed'
		);
		assert.strictEqual(
			retryBody.openedFrom,
			'rc',
			'Retried request should keep the place which the card is opened from'
		);
	} );
} );
