var ipRevealUtils = require( './ipRevealUtils.js' );
var ipReveal = require( './ipReveal.js' );
var target = mw.config.get( 'wgRelevantUserName' );
var revIds = [];

var $userLinks = $( '#bodyContent' ).find( '.mw-contributions-list [data-mw-revid]' );
$userLinks.each( function () {
	var revId = $( this ).data( 'mw-revid' );
	revIds.push( revId );
} );
$userLinks.each( function () {
	var revId = $( this ).data( 'mw-revid' );
	$( this ).find( '.mw-diff-bytes' ).after( function () {
		return [ ' ', $( '<span>' ).addClass( 'mw-changeslist-separator' ), ipReveal.makeButton( target, revId, revIds ) ];
	} );
} );

$( document ).on( 'userRevealed', function () {
	var $relevantUserLinks = $userLinks.map( function ( _i, el ) {
		return $( el ).find( '.ext-checkuser-tempaccount-reveal-ip-button' );
	} );

	// Synthetically trigger a reveal event
	$relevantUserLinks.each( function () {
		$( this ).trigger( 'revealIp' );
	} );
} );

// If the user has been revealed lately, reveal it on load
if ( ipRevealUtils.getRevealedStatus( mw.config.get( 'wgRelevantUserName' ) ) ) {
	$( document ).trigger( 'userRevealed', mw.config.get( 'wgRelevantUserName' ) );
}
