var makeShowIpButton = require( './makeShowIpButton.js' );
mw.hook( 'wikipage.content' ).add( function ( $content ) {
	$content.find( '.mw-tempuserlink' ).after( function () {
		var target = $( this ).text();
		var revId = $( this ).data( 'mw-revid' );

		return makeShowIpButton( target, revId );
	} );
} );
