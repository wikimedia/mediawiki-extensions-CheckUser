var ipReveal = require( './ipReveal.js' );
var ipRevealUtils = require( './ipRevealUtils.js' );

ipReveal.addButton( $( '#bodyContent' ) );

ipReveal.enableMultiReveal( $( document ) );

// Check which users have been revealed recently
var recentUsers = [];
$( '.mw-tempuserlink' ).each( function () {
	var target = $( this ).text();
	if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
		recentUsers.push( target );
	}
} );
recentUsers.forEach( ( user ) => {
	$( document ).trigger( 'userRevealed', user );
} );
