var ipRevealUtils = require( './ipRevealUtils.js' );

function makeButton( target, revId, revIds ) {
	var button = new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' ),
		framed: false,
		quiet: true,
		flags: [
			'progressive'
		],
		classes: [ 'ext-checkuser-tempaccount-reveal-ip-button' ]
	} );
	button.once( 'click', function () {
		button.$element.trigger( 'revealIp' );
		button.$element.off( 'revealIp' );
		$( document ).trigger( 'userRevealed', [ target ] );
	} );

	button.$element.on( 'revealIp', function () {
		button.$element.off( 'revealIp' );
		var params = new URLSearchParams();
		params.set( 'limit', revIds ? revIds.length : 1 );
		$.get(
			mw.config.get( 'wgScriptPath' ) +
			'/rest.php/checkuser/v0/temporaryaccount/' +
			target +
			( revIds && revIds.length ? ( '/revisions/' + revIds.join( '|' ) ) : '' ) +
			'?' + params.toString()
		).then( function ( response ) {
			var ip = response.ips[ revId || 0 ];
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			button.$element.replaceWith(
				$( '<span>' )
					.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
					.text( ip || mw.msg( 'checkuser-tempaccount-reveal-ip-missing' ) )
			);
		} );
	} );

	return button.$element;
}

/**
 * Add a button to a "typical" page. This functionality is here because
 * it is shared between initOnLoad and initOnHook.
 */
function addButton( $content ) {
	var allRevIds = {};
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

		return makeButton( target, revId, revIds );
	} );
}

/**
 * Add enable multireveal for a "typical" page. This functionality is here
 * because it is shared between initOnLoad and initOnHook.
 */
function enableMultiReveal( $element ) {
	$element.on( 'userRevealed', function ( _e, userLookup ) {
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

module.exports = {
	makeButton: makeButton,
	addButton: addButton,
	enableMultiReveal: enableMultiReveal
};
