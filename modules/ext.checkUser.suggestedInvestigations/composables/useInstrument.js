'use strict';

/**
 * Additional context for an instrumentation event.
 *
 * @typedef {Object} InteractionData
 * @property {string} [context] - Context of the action
 */

/**
 * @callback LogEvent Log an event to the Suggested Investigations event stream.
 *
 * @param {string} action
 * @param {InteractionData} [data]
 */

/**
 * Composable to create an event logging function configured to log events to the
 * Suggested Investigations event stream.
 *
 * @return {LogEvent}
 */
module.exports = () => {
	if ( !mw.eventLog ) {
		// EventLogging is not installed
		return () => Promise.resolve();
	}

	const instrument = mw.eventLog.newInstrument(
		'mediawiki.product_metrics.suggested_investigations_interaction.v2',
		'/analytics/mediawiki/suggested_investigations/interaction/1.1.3'
	);

	return ( action, data = {} ) => {
		const interactionData = {};

		if ( data.context ) {
			// eslint-disable-next-line camelcase
			interactionData.action_context = data.context;
		}

		instrument.submitInteraction( action, interactionData );
	};
};
