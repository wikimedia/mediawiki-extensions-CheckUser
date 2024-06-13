const ipReveal = require( './ipReveal.js' );
const ipRevealUtils = require( './ipRevealUtils.js' );

ipReveal.addButton( $( '#bodyContent' ) );

ipReveal.enableMultiReveal( $( document ) );

// Check which users have been revealed recently
const recentUsers = [];
$( '.mw-tempuserlink' ).each( function () {
	const target = $( this ).text();
	if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
		recentUsers.push( target );
	}
} );
recentUsers.forEach( ( user ) => {
	$( document ).trigger( 'userRevealed', user );
} );
