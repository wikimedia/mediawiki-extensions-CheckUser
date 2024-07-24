'use strict';

const ipReveal = require( '../../../../modules/ext.checkUser/temporaryaccount/ipReveal.js' );
const { waitUntilElementDisappears } = require( './utils.js' );

let server;

QUnit.module( 'ext.checkUser.temporaryaccount.ipReveal', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
	},
	afterEach: function () {
		server.restore();
	},
	config: {
		// Prevent initOnLoad.js from running automatically and then
		// calling enableMultiReveal on the document.
		wgCanonicalSpecialPageName: 'CheckUser'
	}
} ) );

QUnit.test( 'Test getRevisionId', ( assert ) => {
	// Create an element with a child that has the data-mw-revid attribute.
	const elementWithRevId = document.createElement( 'div' );
	const childWithRevId = document.createElement( 'span' );
	childWithRevId.setAttribute( 'data-mw-revid', '123456' );
	elementWithRevId.appendChild( childWithRevId );
	// Call getRevisionId with the newly created element, to test when the
	// element is a revision line.
	assert.strictEqual(
		ipReveal.getRevisionId( $( childWithRevId ) ),
		123456,
		'getRevisionId return value for element with data-mw-revid attribute'
	);
	// Call getRevisionId with an element that does not have data-mw-revid, to test
	// when the element is not a revision line.
	assert.strictEqual(
		ipReveal.getRevisionId( $( document.createElement( 'div' ) ) ),
		undefined,
		'getRevisionId return value for element without data-mw-revid attribute'
	);
} );

QUnit.test( 'Test getLogId', ( assert ) => {
	// Create an element with a child that has the data-mw-logid attribute.
	const elementWithRevId = document.createElement( 'div' );
	const childWithRevId = document.createElement( 'span' );
	childWithRevId.setAttribute( 'data-mw-logid', '123456' );
	elementWithRevId.appendChild( childWithRevId );
	// Call getLogId with the newly created element, to test when the
	// element is a log line.
	assert.strictEqual(
		ipReveal.getLogId( $( childWithRevId ) ),
		123456,
		'getLogId return value for element with data-mw-logid attribute'
	);
	// Call getLogId with an element that does not have data-mw-logid, to test
	// when the element is not a log line.
	assert.strictEqual(
		ipReveal.getLogId( $( document.createElement( 'div' ) ) ),
		undefined,
		'getLogId return value for element without data-mw-logid attribute'
	);
} );

QUnit.test( 'Test enableMultiReveal', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Add some testing revision lines to test the multi reveal functionality.
	const revisionLines = { 1: '~1', 2: '~1', 3: '~2' };
	Object.entries( revisionLines ).forEach( ( [ revId, username ] ) => {
		const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', revId );
		$qunitFixture.append( $revisionLine );
		// Add the temporary account username link
		$revisionLine.append( $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username ) );
		// Add the Show IP button manually for the temporary account username link.
		$revisionLine.append( ipReveal.makeButton(
			username,
			{ targetId: revId, allIds: [ revId ] },
			{}
		) );
		assert.strictEqual(
			$revisionLine.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).text(),
			'(checkuser-tempaccount-reveal-ip-button-label)',
			'IP reveal button for revId ' + revId + ' before multi reveal'
		);
	} );
	// Enable multi reveal on the QUnit fixture.
	ipReveal.enableMultiReveal( $qunitFixture );

	// Fire the userRevealed event with the ~1 temporary account username
	// and the IP addresses for the revisions.
	$qunitFixture.trigger( 'userRevealed', [ '~1', { 1: '127.0.0.1', 2: '127.0.0.2', 3: '127.0.0.3' }, true, false ] );
	// Check that the IP addresses were added to the buttons for the revisions
	// with the ~1 temporary account username.
	assert.strictEqual(
		$qunitFixture.find( '[data-mw-revid="1"] .ext-checkuser-tempaccount-reveal-ip' ).text(),
		'127.0.0.1',
		'IP reveal button for revId 1 before multi reveal'
	);
	assert.strictEqual(
		$qunitFixture.find( '[data-mw-revid="2"] .ext-checkuser-tempaccount-reveal-ip' ).text(),
		'127.0.0.2',
		'IP reveal button for revId 2 before multi reveal'
	);
	assert.strictEqual(
		$qunitFixture.find(
			'[data-mw-revid="3"] .ext-checkuser-tempaccount-reveal-ip-button'
		).text(),
		'(checkuser-tempaccount-reveal-ip-button-label)',
		'IP reveal button for revId 3 before multi reveal'
	);
} );

QUnit.test( 'Test enableMultiReveal with grouped recent changes', ( assert ) => {
	const done = assert.async();
	server.respond( ( request ) => {
		if ( request.url.includes( 'revisions' ) || request.url.includes( 'logs' ) ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":{"1":"127.0.0.1"}}' );
		} else {
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":["127.0.0.3"]}' );
		}
	} );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// Add a revision line and a line with no ID for the same user, mimicking grouped recent changes
	const lines = [
		{
			$element: $( '<div>' ).attr( 'data-mw-revid', 1 ),
			revIds: { targetId: 1, allIds: [ 1 ] },
			logIds: {}
		},
		{
			$element: $( '<div>' ).attr( 'data-mw-logid', 1 ),
			revIds: {},
			logIds: { targetId: 1, allIds: [ 1 ] }
		},
		{
			$element: $( '<div>' ).addClass( 'no-id' ),
			revIds: {},
			logIds: {}
		}
	];
	lines.forEach( ( line ) => {
		const username = '~1';
		$qunitFixture.append( line.$element );
		// Add the temporary account username link
		line.$element.append( $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username ) );
		// Add the Show IP button manually for the temporary account username link.
		line.$element.append( ipReveal.makeButton(
			username,
			line.revIds,
			line.logIds
		) );
	} );
	// Enable multi reveal on the QUnit fixture.
	ipReveal.enableMultiReveal( $qunitFixture );

	$( '.no-id .ext-checkuser-tempaccount-reveal-ip-button a', $qunitFixture )[ 0 ].click();

	waitUntilElementDisappears( '.no-id .ext-checkuser-tempaccount-reveal-ip-button' ).then( () => {
		// Verify that the button has gone and was replaced with the IP
		assert.strictEqual(
			$( '.no-id .ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
			0,
			'Button removed after click'
		);
		assert.strictEqual(
			$( '.no-id .ext-checkuser-tempaccount-reveal-ip', $qunitFixture ).text(),
			'127.0.0.3',
			'Text of element that replaced button'
		);

		waitUntilElementDisappears( '[data-mw-revid="1"] .ext-checkuser-tempaccount-reveal-ip-button' ).then( () => {
			// Verify that the button has gone and was replaced with the IP
			assert.strictEqual(
				$( '[data-mw-revid="1"] .ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
				0,
				'Button removed after click'
			);
			assert.strictEqual(
				$( '[data-mw-revid="1"] .ext-checkuser-tempaccount-reveal-ip', $qunitFixture ).text(),
				'127.0.0.1',
				'Text of element that replaced button'
			);

			waitUntilElementDisappears( '[data-mw-logid="1"] .ext-checkuser-tempaccount-reveal-ip-button' ).then( () => {
				// Verify that the button has gone and was replaced with the IP
				assert.strictEqual(
					$( '[data-mw-logid="1"] .ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
					0,
					'Button removed after click'
				);
				assert.strictEqual(
					$( '[data-mw-logid="1"] .ext-checkuser-tempaccount-reveal-ip', $qunitFixture ).text(),
					'127.0.0.1',
					'Text of element that replaced button'
				);

				done();
			} );
		} );
	} );
} );

QUnit.test( 'Test addButton adds temporary account IP reveal buttons', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const temporaryAccountUserLinks = [];
	// Add some testing revision lines
	const revisionLines = { 1: '~1', 2: '~1', 3: '~2' };
	Object.entries( revisionLines ).forEach( ( [ revId, username ] ) => {
		const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', revId );
		$qunitFixture.append( $revisionLine );
		// Add the temporary account username link for the revision line
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$revisionLine.append( $tempAccountUserLink );
		temporaryAccountUserLinks.push( $tempAccountUserLink );
	} );
	// Add some testing log lines
	const logLines = { 2: '~1', 5: '~2', 6: '~3' };
	Object.entries( logLines ).forEach( ( [ logId, username ] ) => {
		const $logLine = $( '<div>' ).attr( 'data-mw-logid', logId );
		$qunitFixture.append( $logLine );
		// Add the temporary account username link for the log line
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$logLine.append( $tempAccountUserLink );
		temporaryAccountUserLinks.push( $tempAccountUserLink );
	} );
	// Add some temporary account username links that are not associated with a revision or log
	const linksWithoutIds = [ '~3', '~5' ];
	linksWithoutIds.forEach( ( username ) => {
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$qunitFixture.append( $tempAccountUserLink );
		temporaryAccountUserLinks.push( $tempAccountUserLink );
	} );
	// Verify that before the call to ::addButton there are no Show IP buttons
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links before addButton call'
	);
	// Call addButton on the QUnit fixture
	ipReveal.addButton( $qunitFixture );
	// Verify that the Show IP button was added for all temporary user links
	temporaryAccountUserLinks.forEach( ( $element ) => {
		assert.strictEqual(
			// eslint-disable-next-line no-jquery/no-class-state
			$element.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), true,
			'IP reveal button is directly after temporary account user link after addButton call'
		);
	} );
} );

QUnit.test( 'Test makeButton creates expected button', ( assert ) => {
	// Call the method under test
	const $button = ipReveal.makeButton( '~1', { targetId: 1, allIds: [ 1 ] }, {} );
	// Verify that the button has the expected button text and classes
	assert.strictEqual(
		$button.text(),
		'(checkuser-tempaccount-reveal-ip-button-label)',
		'Button text'
	);
	const expectedClasses = [
		'ext-checkuser-tempaccount-reveal-ip-button',
		'oo-ui-flaggedElement-progressive',
		'oo-ui-buttonElement'
	];
	expectedClasses.forEach( ( className ) => {
		assert.strictEqual(
			// eslint-disable-next-line no-jquery/no-class-state
			$button.hasClass( className ),
			true,
			'Button has ' + className + ' class'
		);
	} );
} );

function performMakeButtonRequestTest( assert, responseCode, responseContent, expectedText ) {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();
	server.respond( ( request ) => {
		request.respond( responseCode, { 'Content-Type': 'application/json' }, responseContent );
	} );
	// Call the method under test to get a button
	const $button = ipReveal.makeButton( '~1', { targetId: 1, allIds: [ 1 ] }, {} );
	// Add the button to the QUnit fixture
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $button );
	// Click the button
	$( '.ext-checkuser-tempaccount-reveal-ip-button a', $qunitFixture )[ 0 ].click();
	waitUntilElementDisappears( '.ext-checkuser-tempaccount-reveal-ip-button' ).then( () => {
		// Verify that the button has gone and was replaced with a span containing an error message
		assert.strictEqual(
			$( '.ext-checkuser-tempaccount-reveal-ip-button', $qunitFixture ).length,
			0,
			'Button removed after click'
		);
		assert.strictEqual(
			$( '.ext-checkuser-tempaccount-reveal-ip', $qunitFixture ).text(),
			expectedText,
			'Text of element that replaced button'
		);
		done();
	} );
}

QUnit.test( 'Test makeButton on button click for failed request', ( assert ) => {
	performMakeButtonRequestTest( assert, 500, '', '(checkuser-tempaccount-reveal-ip-error)' );
} );

QUnit.test( 'Test makeButton on button click for successful request but no data', ( assert ) => {
	performMakeButtonRequestTest(
		assert, 200, '{"ips":[]}', '(checkuser-tempaccount-reveal-ip-missing)'
	);
} );

QUnit.test( 'Test makeButton on button click for successful request with data', ( assert ) => {
	performMakeButtonRequestTest( assert, 200, '{"ips":{"1":"127.0.0.1"}}', '127.0.0.1' );
} );
