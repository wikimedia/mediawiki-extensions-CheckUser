'use strict';

const useInstrument = require( 'ext.checkUser.userInfoCard/composables/useInstrument.js' );

// Store stubs for use in arrow functions
let configStub, getInstrumentStub, sendStub, instrumentStub;

QUnit.module( 'ext.checkUser.userInfoCard.useInstrument', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stubs for mw functions
		configStub = this.sandbox.stub( mw.config, 'get' );
		this.sandbox.stub( mw.user, 'generateRandomSessionId' ).returns( 'test-session-id' );
		this.sandbox.stub( mw.user, 'getId' ).returns( 123 );

		// Create stub for the instrument
		sendStub = this.sandbox.stub();
		instrumentStub = { send: sendStub };

		if ( mw.testKitchen ) {
			getInstrumentStub = this.sandbox.stub( mw.testKitchen, 'getInstrument' ).returns( instrumentStub );
		} else {
			getInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.testKitchen
			delete mw.testKitchen;
			this.sandbox.define( mw, 'testKitchen', { getInstrument: getInstrumentStub } );
		}

		// Store references in this context for backward compatibility
		this.configStub = configStub;
		this.getInstrumentStub = getInstrumentStub;
		this.sendStub = sendStub;
	}
} ) );

// TODO: T386440 - Fix the other skipped tests and remove this comment
// This test fails when running in conjunction with the other test components in the
// folder (currently skipped).
// When running this test file alone, this test is passing.
QUnit.test( 'returns empty function when instrumentation is disabled', ( assert ) => {
	// Set instrumentation to disabled
	configStub.withArgs( 'wgCheckUserEnableUserInfoCardInstrumentation' ).returns( false );

	const logEvent = useInstrument();

	assert.strictEqual( typeof logEvent, 'function', 'Returns a function' );

	// Call the function and verify it does nothing
	logEvent( 'test-action' );
	assert.strictEqual( getInstrumentStub.callCount, 0, 'Does not create instrument when disabled' );
	assert.strictEqual( sendStub.callCount, 0, 'Does not log events when disabled' );
} );

QUnit.test( 'returned function logs events with correct data', ( assert ) => {
	// Set instrumentation to enabled
	configStub.withArgs( 'wgCheckUserEnableUserInfoCardInstrumentation' ).returns( true );

	const logEvent = useInstrument();

	// Call the function with an action
	logEvent( 'test-action', {
		subType: 'test-subtype',
		source: 'test-source'
	} );

	// Verify send is called with correct parameters
	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'test-action',
		'Passes correct action to send'
	);

	// Verify the interaction data
	const interactionData = sendStub.firstCall.args[ 1 ];
	assert.strictEqual(
		interactionData.funnel_entry_token,
		'test-session-id',
		'Includes funnel entry token in interaction data'
	);
	assert.strictEqual(
		interactionData.action_subtype,
		'test-subtype',
		'Includes subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'test-source',
		'Includes source in interaction data'
	);
} );
