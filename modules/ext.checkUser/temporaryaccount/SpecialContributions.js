const ipRevealUtils = require( './ipRevealUtils.js' );
const ipReveal = require( './ipReveal.js' );
const target = mw.config.get( 'wgRelevantUserName' );
const revIds = [];

const $userLinks = $( '#bodyContent' ).find( '.mw-contributions-list [data-mw-revid]' );
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
		return [ ' ', $( '<span>' ).addClass( 'mw-changeslist-separator' ), ipReveal.makeButton( target, ids ) ];
	} );
} );

$( document ).on( 'userRevealed', () => {
	const $relevantUserLinks = $userLinks.map( ( _i, el ) => $( el ).find( '.ext-checkuser-tempaccount-reveal-ip-button' ) );

	// Synthetically trigger a reveal event
	$relevantUserLinks.each( function () {
		$( this ).trigger( 'revealIp' );
	} );
} );

// If the user has been revealed lately, reveal it on load
if ( ipRevealUtils.getRevealedStatus( mw.config.get( 'wgRelevantUserName' ) ) ) {
	$( document ).trigger( 'userRevealed', mw.config.get( 'wgRelevantUserName' ) );
}
