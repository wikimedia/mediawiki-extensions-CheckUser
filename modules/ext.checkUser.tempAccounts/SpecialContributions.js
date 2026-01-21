const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to contributions pages that show contributions made by a single
 * temporary user. See ipReveal#enableIpRevealForContributionsPage for details.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 * @param {string} pageTitle Declare what page this is being run on.
 *   This is for compatibility across Special:Contributions and Special:DeletedContributions,
 *   as they have different guaranteed existing elements.
 */
module.exports = function ( documentRoot, pageTitle ) {
	const $ipRevealButtons = ipReveal.addIpRevealButtons( $( '#bodyContent', documentRoot ) );
	if ( $ipRevealButtons.length === 0 ) {
		// The contributions page has only one target and therefore no user links
		ipReveal.enableIpRevealForContributionsPage( documentRoot, pageTitle );
	} else {
		// The contributions page has user links, due to having multiple targets. Treat
		// this like any other page.
		require( './initOnLoad.js' )();
	}
};
