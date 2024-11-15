'use strict';

const assert = require( 'assert' ),
	LoginAsCheckUser = require( '../checkuserlogin' ),
	CheckUserPage = require( '../pageobjects/checkuser.page' ),
	EditPage = require( '../pageobjects/edit.page' );

const supportedBrowsers = [ 'chrome', 'chromium', 'msedge' ];

function checkIfBrowserSupportsClientHints( browserName ) {
	return supportedBrowsers.includes( browserName );
}

describe( 'Client Hints', () => {
	before( async () => {
		await LoginAsCheckUser.loginAsCheckUser();
		// Skip the tests if we are using a browser which does not support client hints, because the tests
		// need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await EditPage.edit( 'Testing', 'testing1234', 'test-edit-to-test-client-hints' );
	} );
	it( 'Verify edit sends Client Hints data', async () => {
		// Skip the tests if we are using a browser which does not support client hints, because the tests
		// need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		browser.waitUntil( () => {
			browser.execute( () => browser.document.readyState === 'complete' );
		}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
		assert( await CheckUserPage.getActionsResults.isExisting() );
		// Check that Client Hints data exists for the edit, by checking if the Client Hints element class is present
		// for the edit.
		const $relevantResultLine = $( 'li:contains(test-edit-to-test-client-hints)', CheckUserPage.getActionsResults );
		assert( $relevantResultLine.find( 'mw-checkuser-client-hints' ).length !== 0 );
	} );
	it( 'stores client hints data on successful logins', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( process.env.MEDIAWIKI_USER );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		browser.waitUntil( () => {
			browser.execute( () => browser.document.readyState === 'complete' );
		}, { timeout: 10 * 1000, timeoutMsg: 'Page failed to load in a reasonable time.' } );
		assert( await CheckUserPage.getActionsResults.isExisting() );
		// Check that Client Hints data exists for the login, by checking if the Client Hints element class is present
		// for the log entry.
		const $relevantResultLine = $( 'li:contains(Successfully logged in)', CheckUserPage.getActionsResults );
		assert( $relevantResultLine.find( 'mw-checkuser-client-hints' ).length !== 0 );
	} );
} );
