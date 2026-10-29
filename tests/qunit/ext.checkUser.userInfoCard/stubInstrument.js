'use strict';

const useInstrument = require( 'ext.checkUser.userInfoCard/composables/useInstrument.js' );

/**
 * Enable the UserInfoCard instrumentation and replace the instrument with a stub.
 *
 * Call this before you mount the component, because the component gets the instrument
 * when it is set up.
 *
 * @param {Object} sandbox Sinon sandbox of the test
 * @return {Object} Stub of the send method of the instrument
 */
module.exports = function stubInstrument( sandbox ) {
	mw.config.set( 'wgCheckUserEnableUserInfoCardInstrumentation', true );
	sandbox.stub( mw.user, 'generateRandomSessionId' ).returns( 'test-session-id' );

	useInstrument.reset();
	const sendStub = sandbox.stub();
	const instrumentStub = { send: sendStub };
	if ( mw.testKitchen ) {
		sandbox.stub( mw.testKitchen, 'getInstrument' ).returns( instrumentStub );
	} else {
		delete mw.testKitchen;
		sandbox.define( mw, 'testKitchen', { getInstrument: sandbox.stub().returns( instrumentStub ) } );
	}
	return sendStub;
};
