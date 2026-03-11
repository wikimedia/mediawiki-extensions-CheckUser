'use strict';

( function () {
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'SuggestedInvestigations':
			require( './SpecialSuggestedInvestigations.js' )();
			break;
	}

	require( './instrumentation.js' )();
}() );
