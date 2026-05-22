'use strict';

/**
 * Additional context for an instrumentation event.
 *
 * @typedef {Object} InteractionData
 * @property {number} [sessionLength] The duration IP auto-reveal mode is enabled for, in seconds
 */

/**
 * @callback LogEvent Log an event to the IP auto-reveal event stream.
 *
 * @param {string} action
 * @param {InteractionData} [data]
 */

/**
 * Composable to create an event logging function configured to log events to the
 * IP auto-reveal event stream.
 *
 * @return {LogEvent}
 */
const useInstrument = () => {
	if ( !mw.testKitchen ) {
		// TestKitchen is not installed
		return () => {};
	}

	const instrument = mw.testKitchen.getInstrument(
		'checkuser-ip-auto-reveal-interaction'
	);

	return ( action, data = {} ) => {
		const interactionData = {};

		if ( data.sessionLength ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = JSON.stringify( {
				// eslint-disable-next-line camelcase
				session_length: data.sessionLength
			} );
		}

		instrument.send( action, interactionData );
	};
};

module.exports = useInstrument;
