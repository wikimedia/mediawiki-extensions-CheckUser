'use strict';

const LoginAsCheckUser = require( '../checkuserlogin' ),
	CheckUserPage = require( '../pageobjects/checkuser.page' ),
	EditPage = require( '../pageobjects/edit.page' ),
	CreateAccountPage = require( 'wdio-mediawiki/CreateAccountPage' ),
	LogoutPage = require( '../pageobjects/logout.page' ),
	Util = require( 'wdio-mediawiki/Util' );

const supportedBrowsers = [ 'chrome', 'chromium', 'msedge' ];

function checkIfBrowserSupportsClientHints( browserName ) {
	return supportedBrowsers.includes( browserName );
}

const createdAccountUsername = Util.getTestString( 'ClientHintsAccountCreationTest' );

describe( 'Client Hints', () => {
	before( async () => {
		// Skip the tests if we are using a browser which does not support client hints,
		// because the tests need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		// Create an account for later use in the tests.
		await CreateAccountPage.createAccount( createdAccountUsername, Util.getTestString() );
		// Logout of this newly created account
		await LogoutPage.logout();
		// Then edit the page using a normal account
		await LoginAsCheckUser.loginAsCheckUser();
		await EditPage.edit(
			Util.getTestString( 'CheckUserClientHintsCollectedOnEdit-' ),
			'testing1234',
			'test-edit-to-test-client-hints'
		);
	} );
	it( 'Verify edit sends Client Hints data', async () => {
		// Skip the tests if we are using a browser which does not support client hints,
		// because the tests need data to be sent to the API to e2e test.
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		const checkUserUsername = LoginAsCheckUser.getCheckUserAccountDetails().username;
		await CheckUserPage.checkTarget.setValue( checkUserUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the edit, by checking if the Client Hints
		// element class is present for the edit and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=test-edit-to-test-client-hints' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on successful logins', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		const checkUserUsername = LoginAsCheckUser.getCheckUserAccountDetails().username;
		await CheckUserPage.checkTarget.setValue( checkUserUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the login, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=Successfully logged in' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on account creation', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( createdAccountUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the login, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( `li*=${ createdAccountUsername } was created` );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );

	it( 'stores client hints data on logout', async () => {
		if ( !checkIfBrowserSupportsClientHints( driver.requestedCapabilities.browserName ) ) {
			return;
		}
		await CheckUserPage.open();
		await CheckUserPage.getActionsCheckTypeRadio.click();
		await CheckUserPage.checkTarget.setValue( createdAccountUsername );
		await CheckUserPage.checkReasonInput.setValue( 'Selenium browser testing' );
		await CheckUserPage.submit.click();
		await expect( await CheckUserPage.getActionsResults ).toExist();
		// Check that Client Hints data exists for the logout, by checking if the Client Hints
		// element class is present for the log entry and the span contains content.
		const relevantResultLine = await CheckUserPage.getActionsResults.$( 'li*=Successfully logged out' );
		await expect( relevantResultLine ).toExist();
		const clientHints = await relevantResultLine.$( '.mw-checkuser-client-hints' ).getText();
		await expect( clientHints ).not.toBeFalsy();
	} );
} );
