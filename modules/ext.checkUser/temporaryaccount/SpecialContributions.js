var makeShowIpButton = require( './makeShowIpButton.js' );
var target = mw.config.get( 'wgRelevantUserName' );
mw.hook( 'wikipage.content' ).add( function ( $content ) {
	$content.find( '.mw-contributions-list [data-mw-revid]' ).each( function () {
		var revId = $( this ).data( 'mw-revid' );
		$( this ).find( '.mw-diff-bytes' ).after( function () {
			return [ ' ', $( '<span>' ).addClass( 'mw-changeslist-separator' ), makeShowIpButton( target, revId ) ];
		} );
	} );
} );
