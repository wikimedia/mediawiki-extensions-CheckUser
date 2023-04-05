var makeShowIpButton = require( './makeShowIpButton.js' );
var target = mw.config.get( 'wgRelevantUserName' );
var revIds = [];
mw.hook( 'wikipage.content' ).add( function ( $content ) {
	var $userLinks = $content.find( '.mw-contributions-list [data-mw-revid]' );
	$userLinks.each( function () {
		var revId = $( this ).data( 'mw-revid' );
		revIds.push( revId );
	} );
	$userLinks.each( function () {
		var revId = $( this ).data( 'mw-revid' );
		$( this ).find( '.mw-diff-bytes' ).after( function () {
			return [ ' ', $( '<span>' ).addClass( 'mw-changeslist-separator' ), makeShowIpButton( target, revId, revIds ) ];
		} );
	} );
} );

$( document ).on( 'userRevealed', function () {
	var $userLinks = $( '.mw-contributions-list [data-mw-revid]' );
	$userLinks = $userLinks.map( function ( _i, el ) {
		return $( el ).find( '.ext-checkuser-tempaccount-reveal-ip-button' );
	} );

	// Synthetically trigger a reveal event
	$userLinks.each( function () {
		$( this ).trigger( 'revealIp' );
	} );
} );
