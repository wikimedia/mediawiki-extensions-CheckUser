const ipRevealUtils = require( './ipRevealUtils.js' );
const ipReveal = require( './ipReveal.js' );

/**
 * Add IP reveal functionality to contributions pages that show contributions made by a single
 * temporary user. There are no user links on these pages.
 *
 * This is similar to initOnLoad except with the following customizations:
 * - Since there are no user links, add the buttons for revealing IPs to a specified place
 *   within each revision line.
 * - Use simpler, customized logic for enabling multi-reveal and automatically revealing users,
 *   since the page does not have multiple users, and all buttons are related to revisions.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 * @param {string} pageTitle Declare what page this is being run on.
 *   This is for compatibility across Special:Contributions and Special:DeletedContributions,
 *   as they have different guaranteed existing elements.
 */
module.exports = function ( documentRoot, pageTitle ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	// Define the class name of the element that the "Show IP" button should be appended after.
	// This can't point to the element yet as it'll be the child of a container revision line.
	let revAppendAfter;
	if ( pageTitle === 'Contributions' ) {
		revAppendAfter = '.mw-diff-bytes';
	} else if ( pageTitle === 'DeletedContributions' ) {
		revAppendAfter = '.mw-deletedcontribs-tools';
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
		$( this ).find( revAppendAfter ).after( () => {
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
	if (
		ipRevealUtils.getAutoRevealStatus() ||
		ipRevealUtils.getRevealedStatus( mw.config.get( 'wgRelevantUserName' ) )
	) {
		$( '.ext-checkuser-tempaccount-reveal-ip-button' ).first().trigger( 'revealIp' );
	}
};
