'use strict';

const useInstrument = require( '../../../modules/ext.checkUser.tempAccounts/useInstrument.js' );

describe( 'useInstrument', () => {
	it( 'should record events', () => {
		// Mock TestKitchen infrastructure
		const send = jest.fn();
		const getInstrument = jest.fn( () => ( {
			send
		} ) );
		mw.testKitchen = { getInstrument };

		const logEvent = useInstrument();

		logEvent( 'session_end' );
		logEvent( 'session_start', { sessionLength: 3600 } );

		expect( getInstrument ).toHaveBeenCalledTimes( 1 );

		expect( send ).toHaveBeenCalledTimes( 2 );
		expect( send ).toHaveBeenNthCalledWith( 1, 'session_end', {} );
		expect( send ).toHaveBeenNthCalledWith( 2, 'session_start', {
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( { session_length: 3600 } )
		} );
	} );

	it( 'should not try to record events if TestKitchen is unavailable', () => {
		// Simulate TestKitchen being unavailable
		mw.testKitchen = undefined;

		const logEvent = useInstrument();

		expect( () => {
			logEvent( 'session_end' );
		} ).not.toThrow();
	} );
} );
