const ipReveal = require( './ipReveal.js' );

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

	ipReveal.enableMultiReveal( $( documentRoot ) );

	const $userLinks = ipReveal.addButton( $( '#bodyContent', documentRoot ) );
	ipReveal.revealRecentlyRevealedUsers( $userLinks );
};
