'use strict';

const initOnLoad = require( '../../../../modules/ext.checkUser/temporaryaccount/initOnLoad.js' );
const { waitUntilElementCount } = require( './utils.js' );
const Utils = require( '../../../../modules/ext.checkUser/temporaryaccount/ipRevealUtils.js' );
const { getRevisionId, getLogId } = require( '../../../../modules/ext.checkUser/temporaryaccount/ipReveal.js' );

let server;

QUnit.module( 'ext.checkUser.temporaryaccount.initOnLoad', QUnit.newMwEnvironment( {
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
		// calling enableMultiReveal on the document. We will call it
		// manually at the right time in the tests.
		wgCanonicalSpecialPageName: 'CheckUser',
		wgCheckUserTemporaryAccountMaxAge: 1500
	}
} ) );

QUnit.test( 'Test initOnLoad when there are no temporary account user links', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links before initOnLoad call'
	);
	// Call initOnLoad with the QUnit fixture as the document root
	initOnLoad( $qunitFixture );
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links after initOnLoad call'
	);
} );

function setUpDocumentForTest() {
	const $bodyContent = $( '<div>' ).attr( 'id', 'bodyContent' );
	const temporaryAccountUserLinks = [];
	const temporaryAccountUserLinksThatAreAutomaticallyRevealed = [];
	const temporaryAccountUserLinksWhichShouldHaveNoButton = [];
	// Add some testing revision lines
	const revisionLines = { 1: '~1', 2: '~1', 3: '~2' };
	Object.entries( revisionLines ).forEach( ( [ revId, username ] ) => {
		const $revisionLine = $( '<div>' ).attr( 'data-mw-revid', revId );
		$bodyContent.append( $revisionLine );
		// Add the temporary account username link for the revision line
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$revisionLine.append( $tempAccountUserLink );
		if ( Utils.getRevealedStatus( username ) ) {
			temporaryAccountUserLinksThatAreAutomaticallyRevealed.push( $tempAccountUserLink );
		} else {
			temporaryAccountUserLinks.push( $tempAccountUserLink );
		}
	} );
	// Add some testing log lines which are performed by temporary accounts
	const $logLinesContainer = $( '<div>' ).attr( 'class', 'mw-logevent-loglines' );
	$bodyContent.append( $logLinesContainer );
	const logLines = { 1: '~1', 123: '~2' };
	Object.entries( logLines ).forEach( ( [ logId, username ] ) => {
		const $logLine = $( '<div>' )
			.attr( 'data-mw-logid', logId )
			.addClass( 'ext-checkuser-log-line-supports-ip-reveal' );
		$logLinesContainer.append( $logLine );
		// Add the temporary account username link for the performer of the log line
		const $tempAccountPerformerLink = $( '<a>' )
			.addClass( 'mw-tempuserlink' )
			.text( username );
		$logLine.append( $tempAccountPerformerLink );
		if ( Utils.getRevealedStatus( username ) ) {
			temporaryAccountUserLinksThatAreAutomaticallyRevealed.push(
				$tempAccountPerformerLink
			);
		} else {
			temporaryAccountUserLinks.push( $tempAccountPerformerLink );
		}
		// Add another temp account user link as the target, which should not have
		// a button added next to it.
		const $tempAccountTargetLink = $( '<a>' )
			.addClass( 'mw-tempuserlink' )
			.text( username );
		$logLine.append( $tempAccountPerformerLink );
		temporaryAccountUserLinksWhichShouldHaveNoButton.push( $tempAccountTargetLink );
	} );
	// Add some testing log lines which have temporary account usernames but are not performed
	// by a temporary account
	const logLinesNotPerformedByTempAccounts = { 1234: '~1' };
	Object.entries( logLinesNotPerformedByTempAccounts ).forEach( ( [ logId, username ] ) => {
		const $logLine = $( '<div>' )
			.attr( 'data-mw-logid', logId );
		$logLinesContainer.append( $logLine );
		// Add the temporary account username link as the target of the log line
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$logLine.append( $tempAccountUserLink );
		temporaryAccountUserLinksWhichShouldHaveNoButton.push( $tempAccountUserLink );
	} );
	// Add some temporary account username links that are not associated with a revision or log
	const linksWithoutIds = [ '~1', '~5' ];
	linksWithoutIds.forEach( ( username ) => {
		const $tempAccountUserLink = $( '<a>' ).addClass( 'mw-tempuserlink' ).text( username );
		$bodyContent.append( $tempAccountUserLink );
		if ( Utils.getRevealedStatus( username ) ) {
			temporaryAccountUserLinksThatAreAutomaticallyRevealed.push( $tempAccountUserLink );
		} else {
			temporaryAccountUserLinks.push( $tempAccountUserLink );
		}
	} );
	// Append the $bodyContent to the QUnit test fixture.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $bodyContent );
	return {
		temporaryAccountUserLinks,
		temporaryAccountUserLinksThatAreAutomaticallyRevealed,
		temporaryAccountUserLinksWhichShouldHaveNoButton
	};
}

QUnit.test( 'Test initOnLoad when there are temporary account user links with one pre-revealed', ( assert ) => {
	// Prevent the test being very slow if the wait for condition fails.
	assert.timeout( 1000 );
	// Handle requests to the temporary account IP lookup APIs responding with
	// mock data and a 200 HTTP status code.
	server.respond( ( request ) => {
		if ( request.url.includes( '/logs/' ) ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":{"1":"127.0.0.2"}}' );
		} else if ( request.url.includes( '/revisions/' ) ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":{"1":"127.0.0.1","2":"1.2.3.4"}}' );
		} else {
			request.respond( 200, { 'Content-Type': 'application/json' }, '{"ips":[]}' );
		}
	} );
	// Mark that the ~1 temporary account username has been revealed previously, and so
	// should be automatically revealed when the page loads.
	Utils.setRevealedStatus( '~1' );
	// Set up the document for the test.
	const {
		temporaryAccountUserLinks,
		temporaryAccountUserLinksThatAreAutomaticallyRevealed,
		temporaryAccountUserLinksWhichShouldHaveNoButton
	} = setUpDocumentForTest();
	// Verify that before the call to initOnLoad there are no Show IP buttons
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	assert.strictEqual(
		$qunitFixture.find( '.ext-checkuser-tempaccount-reveal-ip-button' ).length,
		0,
		'No IP reveal links before initOnLoad call'
	);
	// Call initOnLoad with the QUnit fixture as the document root
	initOnLoad( $qunitFixture );
	// Wait until all the automatically revealed IP addresses have been revealed.
	const done = assert.async();
	waitUntilElementCount(
		'.ext-checkuser-tempaccount-reveal-ip',
		temporaryAccountUserLinksThatAreAutomaticallyRevealed.length
	).then( () => {
		// Verify that the Show IP button was added for all temporary user links which
		// are expected to have the button.
		temporaryAccountUserLinks.forEach( ( $element ) => {
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$element.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), true,
				'Button is after temp user link for non-recently revealed temporary account'
			);
		} );
		temporaryAccountUserLinksWhichShouldHaveNoButton.forEach( ( $element ) => {
			assert.strictEqual(
				// eslint-disable-next-line no-jquery/no-class-state
				$element.next().hasClass( 'ext-checkuser-tempaccount-reveal-ip-button' ), false,
				'Temp user links for log lines should have no button unless they are the performer'
			);
		} );
		// Verify that the relevant IP is shown for the temporary account user links that should be
		// automatically revealed on load.
		temporaryAccountUserLinksThatAreAutomaticallyRevealed.forEach( ( $element ) => {
			let expectedText;
			if ( getRevisionId( $element ) === 1 ) {
				expectedText = '127.0.0.1';
			} else if ( getRevisionId( $element ) === 2 ) {
				expectedText = '1.2.3.4';
			} else if ( getLogId( $element ) === 1 ) {
				expectedText = '127.0.0.2';
			} else {
				expectedText = '(checkuser-tempaccount-reveal-ip-missing)';
			}
			assert.strictEqual(
				$element.next().text(),
				expectedText,
				'IP is after temporary account user link for recently revealed temporary account'
			);
		} );
		// Remove the cookie after the test to avoid breaking other tests.
		mw.storage.remove( 'mw-checkuser-temp-~1' );
		done();
	} );
} );
