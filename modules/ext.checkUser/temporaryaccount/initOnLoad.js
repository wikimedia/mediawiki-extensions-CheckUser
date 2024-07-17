const ipReveal = require( './ipReveal.js' );
const ipRevealUtils = require( './ipRevealUtils.js' );

/**
 * Run code when the page loads.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	ipReveal.addButton( $( '#bodyContent', documentRoot ) );

	ipReveal.enableMultiReveal( $( documentRoot ) );

	// Check which users have been revealed recently, and reveal them on load.
	const recentUsers = [];
	$( '.mw-tempuserlink', documentRoot ).each( function () {
		const target = $( this ).text();

		// Trigger a lookup for one of each revealed user
		if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
			$( this ).next( '.ext-checkuser-tempaccount-reveal-ip-button' ).trigger( 'revealIp' );
			recentUsers.push( target );
		}
	} );
};
