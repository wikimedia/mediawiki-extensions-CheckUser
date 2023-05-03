var ipReveal = require( './ipReveal.js' );

mw.hook( 'wikipage.content' ).add( function ( $content ) {
	ipReveal.addButton( $content );
} );

ipReveal.enableMultiReveal( $( document ) );
