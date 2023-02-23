module.exports = function makeShowIpButton( target, revId ) {
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
		var params = new URLSearchParams();
		params.set( 'limit', 1 );
		$.get(
			mw.config.get( 'wgScriptPath' ) +
			'/rest.php/checkuser/v0/temporaryaccount/' +
			target +
			( revId ? ( '/revisions/' + revId ) : '' ) +
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
