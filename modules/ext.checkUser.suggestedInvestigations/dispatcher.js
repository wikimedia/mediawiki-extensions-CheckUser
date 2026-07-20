'use strict';

( function () {
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'SuggestedInvestigations':
			require( './SpecialSuggestedInvestigations.js' )( window );
			break;
		case 'Block':
			require( './SpecialBlock.js' ).onLoad();
			break;
	}

	require( './instrumentation.js' )();
}() );
