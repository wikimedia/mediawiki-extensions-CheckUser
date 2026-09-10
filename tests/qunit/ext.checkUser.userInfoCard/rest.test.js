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

/**
 * Answers icon requests with the variant of an icon for each requested user, and records the
 * bodies of the requests which were made.
 *
 * @param {Object} assert
 * @param {Object<string,?string>} iconsByUser Icon to answer for a user. Users which are
 *   absent from this map get 'userAvatar', and users which are mapped to null are left out
 *   of the response.
 * @param {number} [status=200] Status code to answer with
 * @return {Array<Object>} Bodies of the requests, in the order in which they arrived
 */
function respondToIconRequests( assert, iconsByUser, status = 200 ) {
	const requestBodies = [];

	server.respond( ( request ) => {
		if ( !request.url.endsWith( '/checkuser/v0/userinfo/icons' ) ) {
			// The icons endpoint needs no CSRF token, so a request for one, or any other
			// request, means that the code under test does more than it should.
			assert.true( false, 'Unexpected API request to ' + request.url );
			return;
		}

		const body = JSON.parse( request.requestBody );
		requestBodies.push( body );

		if ( status !== 200 ) {
			request.respond( status, { 'Content-Type': 'application/json' }, '{}' );
			return;
		}

		const icons = {};
		body.users.forEach( ( username ) => {
			if ( iconsByUser[ username ] === null ) {
				return;
			}
			icons[ username ] = iconsByUser[ username ] || 'userAvatar';
		} );
		request.respond(
			200,
			{ 'Content-Type': 'application/json' },
			JSON.stringify( { icons: icons } )
		);
	} );

	return requestBodies;
}

QUnit.test( 'Test getUserIconVariants sends the users and returns the icons', ( assert ) => {
	const requestBodies = respondToIconRequests( assert, { TestUser1: 'userBlocked' } );

	return rest.getUserIconVariants( [ 'TestUser1', 'TestUser2' ] ).then( ( icons ) => {
		assert.deepEqual(
			requestBodies,
			[ { users: [ 'TestUser1', 'TestUser2' ] } ],
			'Request body should contain the requested users'
		);
		assert.deepEqual(
			icons,
			{ TestUser1: 'userBlocked', TestUser2: 'userAvatar' },
			'getUserIconVariants should return the icons from the response'
		);
	} );
} );

QUnit.test( 'Test getUserIconVariant puts the requests of one tick together', ( assert ) => {
	const requestBodies = respondToIconRequests( assert, {
		TestUser1: 'userBlocked',
		TestUser2: 'suggestedInvestigations'
	} );

	return Promise.all( [
		rest.getUserIconVariant( 'TestUser1' ),
		rest.getUserIconVariant( 'TestUser2' ),
		rest.getUserIconVariant( 'TestUser1' )
	] ).then( ( icons ) => {
		assert.deepEqual(
			requestBodies,
			[ { users: [ 'TestUser1', 'TestUser2' ] } ],
			'One request should be made, and it should name every user once'
		);
		assert.deepEqual(
			icons,
			[ 'userBlocked', 'suggestedInvestigations', 'userBlocked' ],
			'Every caller should get the icon of the user which it asked about'
		);
	} );
} );
