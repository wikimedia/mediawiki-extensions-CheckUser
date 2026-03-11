'use strict';

/**
 * Initialize instrumentation for Suggested Investigations.
 *
 * @return {void}
 */
module.exports = () => {
	// T418740: Instrumentation for the "SI cases" link on contributions special pages
	const $siCasesLink = $( '.mw-contributions-link-suggested-investigations' );
	if ( $siCasesLink.length !== 0 ) {
		const useInstrument = require( './composables/useInstrument.js' );
		const logEvent = useInstrument();

		$siCasesLink.on(
			'click',
			() => logEvent(
				'contributions_toollink_click',
				{ context: mw.config.get( 'wgRelevantUserName' ) }
			)
		);
	}
};
