module.exports = function makeShowIpButton( target, revId, revIds ) {
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
			button.$element.replaceWith(
				$( '<span>' )
					.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
					.text( ip || mw.msg( 'checkuser-tempaccount-reveal-ip-missing' ) )
			);
		} );
	} );

	return button.$element;
};
