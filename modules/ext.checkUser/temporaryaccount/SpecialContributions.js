const ipRevealUtils = require( './ipRevealUtils.js' );
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

	const target = mw.config.get( 'wgRelevantUserName' );
	const revIds = [];

	const $userLinks = $( '#bodyContent', documentRoot ).find( '.mw-contributions-list [data-mw-revid]' );
	$userLinks.each( function () {
		const revId = ipReveal.getRevisionId( $( this ) );
		revIds.push( revId );
	} );
	$userLinks.each( function () {
		const revId = ipReveal.getRevisionId( $( this ) );
		$( this ).find( '.mw-diff-bytes' ).after( () => {
			const ids = {
				targetId: revId,
				allIds: revIds
			};
			return [
				' ',
				$( '<span>' ).addClass( 'mw-changeslist-separator' ),
				ipReveal.makeButton( target, ids, undefined, documentRoot )
			];
		} );
	} );

	$( documentRoot ).on( 'userRevealed', ( _e, userLookup, ips ) => {
		$( '.ext-checkuser-tempaccount-reveal-ip-button' ).each( function () {
			const id = $( this ).closest( '[data-mw-revid]' ).data( 'mw-revid' );
			const ip = ( ips && ips[ id ] ) ? ips[ id ] : false;
			ipReveal.replaceButton( $( this ), ip, true );
		} );
	} );

	// If the user has been revealed lately, trigger a lookup from the first button
	if ( ipRevealUtils.getRevealedStatus( mw.config.get( 'wgRelevantUserName' ) ) ) {
		$( '.ext-checkuser-tempaccount-reveal-ip-button' ).first().trigger( 'revealIp' );
	}
};
