'use strict';

const useInstrument = require( 'ext.checkUser.suggestedInvestigations/composables/useInstrument.js' );

// Store stubs for use in arrow functions
let getInstrumentStub, sendStub;

QUnit.module( 'ext.checkUser.suggestedInvestigations.useInstrument', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stub for the instrument
		sendStub = this.sandbox.stub();
		const instrumentStub = { send: sendStub };

		if ( mw.testKitchen ) {
			getInstrumentStub = this.sandbox.stub( mw.testKitchen, 'getInstrument' ).returns( instrumentStub );
		} else {
			getInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.testKitchen
			delete mw.testKitchen;
			this.sandbox.define( mw, 'testKitchen', { getInstrument: getInstrumentStub } );
		}
	}
} ) );

QUnit.test( 'returned function logs events with correct data', ( assert ) => {
	const logEvent = useInstrument( 'suggested-investigations-interaction-v2' );

	logEvent( 'test-action', {
		context: 'Test context'
	} );

	// Verify send is called with correct parameters
	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'test-action',
		'Passes correct action to send'
	);
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_context,
		'Test context',
		'Includes context in interaction data'
	);
} );
