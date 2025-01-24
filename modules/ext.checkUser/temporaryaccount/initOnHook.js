const ipReveal = require( './ipReveal.js' );

/**
 * Code to run when the page loads.
 *
 * @param {string|jQuery|*} documentRoot A DOM Element, Document, jQuery or selector
 *   to use as context
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		ipReveal.addButton( $content );
		ipReveal.revealRecentlyRevealedUsers( $( '.mw-tempuserlink', documentRoot ) );
	} );

	ipReveal.enableMultiReveal( $( documentRoot ) );
};
