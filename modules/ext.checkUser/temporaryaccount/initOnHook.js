const ipReveal = require( './ipReveal.js' );
const ipRevealUtils = require( './ipRevealUtils.js' );

/**
 * Checks which users have been revealed recently, and reveals those users on load.
 *
 * @param {string|JQuery|*} documentRoot A DOM Element, Document, jQuery or selector
 *   to use as the root for searches.
 */
function checkRecentlyRevealedUsers( documentRoot ) {
	const recentUsers = [];
	$( '.mw-tempuserlink', documentRoot ).each( function () {
		const target = $( this ).text();

		// Trigger a lookup for one of each revealed user
		if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
			$( this ).next( '.ext-checkuser-tempaccount-reveal-ip-button' ).trigger( 'revealIp' );
			recentUsers.push( target );
		}
	} );
}

/**
 * Code to run when the page loads.
 *
 * @param {string|JQuery|*} documentRoot A DOM Element, Document, jQuery or selector
 *   to use as context
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		ipReveal.addButton( $content );
		checkRecentlyRevealedUsers( documentRoot );
	} );

	ipReveal.enableMultiReveal( $( documentRoot ) );
	checkRecentlyRevealedUsers( documentRoot );
};
