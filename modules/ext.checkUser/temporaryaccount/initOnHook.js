const ipReveal = require( './ipReveal.js' );
const ipRevealUtils = require( './ipRevealUtils.js' );

// Check which users have been revealed recently
function checkRecentlyRevealedUsers() {
	const recentUsers = [];
	$( '.mw-tempuserlink' ).each( function () {
		const target = $( this ).text();

		// Trigger a lookup for one of each revealed user
		if ( ipRevealUtils.getRevealedStatus( target ) && recentUsers.indexOf( target ) < 0 ) {
			$( this ).next( '.ext-checkuser-tempaccount-reveal-ip-button' ).trigger( 'revealIp' );
			recentUsers.push( target );
		}
	} );
}

mw.hook( 'wikipage.content' ).add( ( $content ) => {
	ipReveal.addButton( $content );
	checkRecentlyRevealedUsers();
} );

ipReveal.enableMultiReveal( $( document ) );
checkRecentlyRevealedUsers();
