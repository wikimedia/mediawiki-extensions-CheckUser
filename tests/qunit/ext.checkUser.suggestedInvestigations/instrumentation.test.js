'use strict';

const instrumentation = require( 'ext.checkUser.suggestedInvestigations/instrumentation.js' );

// Store stubs for use in arrow functions
let submitInteractionStub;

QUnit.module( 'ext.checkUser.suggestedInvestigations.instrumentation', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stub for the instrument
		submitInteractionStub = this.sandbox.stub();
		const instrumentStub = { submitInteraction: submitInteractionStub };

		if ( 'eventLog' in mw ) {
			this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );
		} else {
			const newInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.eventLog
			this.sandbox.define( mw, 'eventLog', { newInstrument: newInstrumentStub } );
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

	// Verify submitInteraction is called with correct parameters
	assert.strictEqual( submitInteractionStub.callCount, 1, 'Calls submitInteraction' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'contributions_toollink_click',
		'Passes correct action to submitInteraction'
	);
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 1 ].action_context,
		'TestUser1234',
		'Includes context in interaction data'
	);
} );
