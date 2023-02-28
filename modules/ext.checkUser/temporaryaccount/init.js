var makeShowIpButton = require( './makeShowIpButton.js' );
var allRevIds = {};
mw.hook( 'wikipage.content' ).add( function ( $content ) {
	var $userLinks = $content.find( '.mw-tempuserlink' );

	$userLinks.each( function () {
		var revId = $( this ).data( 'mw-revid' );

		if ( revId ) {
			var target = $( this ).text();
			if ( !allRevIds[ target ] ) {
				allRevIds[ target ] = [];
			}
			allRevIds[ target ].push( revId );
		}
	} );

	$userLinks.after( function () {
		var revId = $( this ).data( 'mw-revid' );
		var target = $( this ).text();
		var revIds;

		if ( revId ) {
			revIds = allRevIds[ target ];
		}

		return makeShowIpButton( target, revId, revIds );
	} );
} );

// Checkusers reveal every IP used by the looked up username
mw.user.getRights( function ( rights ) {
	if ( rights.indexOf( 'checkuser' ) > -1 ) {
		$( document ).on( 'ipRevealed', function ( _e, userLookup ) {
			// Find all temp user links that share the username
			var $userLinks = $( '.mw-tempuserlink' ).filter( function () {
				return $( this ).text() === userLookup;
			} );

			// Convert the user links into pointers to the IP reveal button
			$userLinks = $userLinks.map( function ( _i, el ) {
				return $( el ).next( '.ext-checkuser-tempaccount-reveal-ip-button' );
			} );

			// Synthetically trigger a reveal event
			$userLinks.each( function () {
				$( this ).trigger( 'revealIp' );
			} );
		} );
	}
} );
