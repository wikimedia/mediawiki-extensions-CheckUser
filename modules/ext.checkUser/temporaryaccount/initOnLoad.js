const ipReveal = require( './ipReveal.js' );
const ipRevealUtils = require( './ipRevealUtils.js' );

ipReveal.addButton( $( '#bodyContent' ) );

ipReveal.enableMultiReveal( $( document ) );

// Check which users have been revealed recently
const recentUsers = [];
$( '.mw-tempuserlink' ).each( function () {
	const target = $( this ).text();

	// Trigger a lookup for one of each revealed user
	if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
		$( this ).siblings( '.ext-checkuser-tempaccount-reveal-ip-button' ).trigger( 'revealIp' );
		recentUsers.push( target );
	}
} );
