'use strict';

const getUsersBlockForm = require( '../../../../modules/ext.checkUser/checkuser/getUsersBlockForm.js' );

QUnit.module( 'ext.checkUser.checkuser.getUsersBlockForm', QUnit.newMwEnvironment() );

/**
 * Set up the QUnit fixture for testing the getUsersBlockForm function.
 *
 * @param {Object.<string, boolean>} targets
 */
function setUpDocumentForTest( targets ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	// Create the mock results list which will just contain the checkboxes.
	const resultsWrapper = document.createElement( 'div' );
	resultsWrapper.id = 'checkuserresults';
	resultsWrapper.className = 'mw-checkuser-get-users-results';

	let li;
	for ( const [ target, selected ] of Object.entries( targets ) ) {
		li = document.createElement( 'li' );
		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.name = 'users[]';
		checkbox.value = target;
		if ( selected ) {
			checkbox.click();
		}
		li.appendChild( checkbox );
		resultsWrapper.appendChild( li );
	}

	// Add an unrelated checkbox to test that it is ignored.
	li = document.createElement( 'li' );
	const unrelatedCheckbox = document.createElement( 'input' );
	unrelatedCheckbox.type = 'checkbox';
	unrelatedCheckbox.name = 'unrelated';
	unrelatedCheckbox.value = 'unrelated';
	unrelatedCheckbox.className = 'mw-checkuser-unrelated-checkbox';
	li.appendChild( unrelatedCheckbox );
	resultsWrapper.appendChild( li );

	// Add the checkboxes to the QUnit test fixture.
	$qunitFixture.html( resultsWrapper );

	// Create the block form fieldset which will be empty.
	const blockForm = document.createElement( 'div' );
	blockForm.className = 'mw-checkuser-massblock';
	const fieldset = document.createElement( 'fieldset' );
	blockForm.appendChild( fieldset );

	// Add the fieldset to the QUnit test fixture.
	$qunitFixture.append( blockForm );
}

QUnit.test( 'Test MultiLock link', function ( assert ) {
	// We need the test to wait a small amount of time for the click events to finish.
	const done = assert.async();

	// Set wgCUCAMultiLockCentral to a URL. It must be set for the test to work.
	mw.config.set( 'wgCUCAMultiLockCentral', 'https://example.com/wiki/Special:MultiLock' );

	// Set the HTML that is added by Special:CheckUser.
	setUpDocumentForTest( { Test: true, '1.2.3.4': true, Test2: false, '4.5.6.0/24': false } );

	// Call the function, specifying the QUnit fixture as the document root.
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	getUsersBlockForm( $qunitFixture );

	// Click the unrelated checkbox to cause an update to the URLs.
	$( '.mw-checkuser-unrelated-checkbox', $qunitFixture )[ 0 ].click();
	setTimeout( function () {
		// Assert that the MultiLock URL is as expected
		const $linkElement = $( '.mw-checkuser-multilock-link', $qunitFixture );
		assert.true(
			!!$linkElement.length,
			'Link exists in the DOM after checkbox was clicked'
		);
		assert.strictEqual(
			$linkElement.attr( 'href' ),
			'https://example.com/wiki/Special:MultiLock?wpTarget=Test',
			'URL to Special:MultiLock is correctly set'
		);
		assert.strictEqual(
			$linkElement.text(),
			'(checkuser-centralauth-multilock)',
			'Link text for MultiLock link is correctly set'
		);
		// QUnit tests are now done, so we can call done.
		done();
	} );
} );
