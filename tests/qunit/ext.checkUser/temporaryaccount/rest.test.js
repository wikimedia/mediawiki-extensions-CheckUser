'use strict';

const rest = require( '../../../../modules/ext.checkUser/temporaryaccount/rest.js' );

let server;

QUnit.module( 'ext.checkUser.temporaryaccount.rest', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	}
} ) );

function performRevealRequestTest(
	assert, target, logIds, revIds, expectedUrl, responseCode, responseContent, shouldFail
) {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();
	server.respond( ( request ) => {
		if ( request.url.endsWith( expectedUrl ) ) {
			request.respond(
				responseCode,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( responseContent )
			);
		} else if ( request.url.includes( 'type=csrf' ) && request.url.includes( 'meta=tokens' ) ) {
			// Handle the request for a new CSRF token by returning a fake token.
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );
	// Call the method under test
	rest.performRevealRequest( '~1', revIds, logIds ).then( ( data ) => {
		if ( shouldFail ) {
			assert.true( false, 'Request should have failed' );
		}
		assert.deepEqual( data, responseContent, 'Response data' );
		done();
	} ).fail( () => {
		if ( !shouldFail ) {
			assert.true( false, 'Request should have succeeded' );
		} else {
			assert.true( true, 'Request failed (expected)' );
		}
		done();
	} );
}

QUnit.test( 'Test performRevealRequest for 500 response when requesting one IP', ( assert ) => {
	performRevealRequestTest(
		assert, '~1', {}, {}, 'checkuser/v0/temporaryaccount/~1?limit=1', 500, '', true
	);
} );

QUnit.test( 'Test performRevealRequest for 500 response when getting IPs for rev IDs', ( assert ) => {
	performRevealRequestTest(
		assert, '~1', {}, { allIds: [ '1', '2' ] },
		'checkuser/v0/temporaryaccount/~1/revisions/1|2', 500, '', true
	);
} );

QUnit.test( 'Test performRevealRequest for 500 response when getting IPs for log IDs', ( assert ) => {
	performRevealRequestTest(
		assert, '~1', { allIds: [ '1', '2' ] }, {},
		'checkuser/v0/temporaryaccount/~1/logs/1|2', 500, '', true
	);
} );

QUnit.test( 'Test performRevealRequest for 200 response when requesting one IP', ( assert ) => {
	performRevealRequestTest(
		assert, '~1', {}, {}, 'checkuser/v0/temporaryaccount/~1?limit=1', 200,
		{ test: 'test' }, false
	);
} );

QUnit.test( 'Test performRevealRequest on bad CSRF token for both attempts', ( assert ) => {
	performRevealRequestTest(
		assert, '~1', {}, {}, 'checkuser/v0/temporaryaccount/~1?limit=1', 500,
		{ errorKey: 'rest-badtoken' }, true
	);
} );

QUnit.test( 'Test performFullRevealRequest for only target username', ( assert ) => {
	server.respond( ( request ) => {
		// Respond to a full reveal API request.
		if ( request.url.endsWith( 'checkuser/v0/temporaryaccount/~1' ) ) {
			request.respond(
				200, { 'Content-Type': 'application/json' }, '{"ips":["127.0.0.1","1.2.3.4"]}'
			);
		} else if ( request.url.includes( 'type=csrf' ) && request.url.includes( 'meta=tokens' ) ) {
			// Handle the request for a new CSRF token by returning a fake token.
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'newtoken' } }
			} ) );
		} else {
			// All API requests except the above are not expected to be called during the test.
			// To prevent the test from silently failing, we will fail the test if an
			// unexpected API request is made.
			assert.true( false, 'Unexpected API request to' + request.url );
		}
	} );
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();
	// Call the method under test
	rest.performFullRevealRequest( '~1', {}, {} ).then( ( data ) => {
		assert.deepEqual( data, { ips: [ '127.0.0.1', '1.2.3.4' ] }, 'Response data' );
		done();
	} ).fail( () => {
		assert.true( false, 'Request should have succeeded' );
		done();
	} );
} );

QUnit.test( 'Test performFullRevealRequest on bad CSRF token for first attempt', ( assert ) => {
	let csrfTokenUpdated = false;
	server.respond( ( request ) => {
		// Respond to a full reveal API request.
		if ( request.url.endsWith( 'checkuser/v0/temporaryaccount/~1' ) ) {
			// If the CSRF token has been updated, then return a valid response. Otherwise, return a
			// response indicating that the CSRF token is invalid.
			if ( csrfTokenUpdated ) {
				request.respond(
					200, { 'Content-Type': 'application/json' }, '{"ips":["127.0.0.1","1.2.3.4"]}'
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
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();
	// Call the method under test
	rest.performFullRevealRequest( '~1', {}, {} ).then( ( data ) => {
		assert.deepEqual( data, { ips: [ '127.0.0.1', '1.2.3.4' ] }, 'Response data' );
		done();
	} ).fail( () => {
		assert.true( false, 'Request should have succeeded' );
		done();
	} );
} );
