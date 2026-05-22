'use strict';

const instrumentation = require( 'ext.checkUser.suggestedInvestigations/instrumentation.js' );

// Store stubs for use in arrow functions
let sendStub;

QUnit.module( 'ext.checkUser.suggestedInvestigations.instrumentation', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stub for the instrument
		sendStub = this.sandbox.stub();
		const instrumentStub = { send: sendStub };

		if ( mw.testKitchen ) {
			this.sandbox.stub( mw.testKitchen, 'getInstrument' ).returns( instrumentStub );
		} else {
			const getInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.testKitchen
			delete mw.testKitchen;
			this.sandbox.define( mw, 'testKitchen', { getInstrument: getInstrumentStub } );
		}
	}
} ) );

QUnit.test( 'Instruments clicks on "SI cases" contributions special page toollink', ( assert ) => {
	mw.config.set( 'wgRelevantUserName', 'TestUser1234' );

	// Set up the DOM to include the "SI cases" link
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-contributions-link-suggested-investigations' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $link );

	// Execute the code under test
	instrumentation();

	// Simulate a click on the link
	$link.trigger( 'click' );

	// Verify send is called with correct parameters
	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'contributions_toollink_click',
		'Passes correct action to send'
	);
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_context,
		'TestUser1234',
		'Includes context in interaction data'
	);
} );

QUnit.test( 'Instruments clicks on "SI cases" links in CheckUser Get Users results', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $container = $( '<div>' ).addClass( 'mw-checkuser-get-users-results' );
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-checkuser-si-cases-link' )
		.text( 'SI cases' );
	$container.append( $link );
	$qunitFixture.append( $container );

	instrumentation();
	$link.trigger( 'mousedown' );

	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'checkuser_si_cases_link_click',
		'Passes correct action to send'
	);
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_context,
		'special_checkuser_get_users',
		'Includes get_users context in interaction data'
	);
} );
