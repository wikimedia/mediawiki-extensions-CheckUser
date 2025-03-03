const { getAutoRevealStatus, setAutoRevealStatus } = require( './ipRevealUtils.js' );

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

	$( '.checkuser-ip-auto-reveal', documentRoot ).on(
		'click',
		() => {
			// Toggle the status
			if ( !getAutoRevealStatus() ) {
				setAutoRevealStatus( mw.config.get( 'wgCheckUserTemporaryAccountAutoRevealTime' ) );
			} else {
				setAutoRevealStatus( '' );
			}
			// Refresh, so that IPs may be revealed (or hidden)
			window.location.reload();
		} );
};
