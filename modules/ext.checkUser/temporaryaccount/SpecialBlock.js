var $blockTargetWidget = $( '#mw-bi-target' );
var blockTargetWidget;

// This code is also loaded on the "block succeeded" page where there is no form,
// so check for block target widget; if it exists, the form is present
if ( $blockTargetWidget.length ) {
	blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
	blockTargetWidget.on( 'change', updateIPs );
	updateIPs();
}

function updateIPs() {
	var blockTarget = blockTargetWidget.getValue().toString().trim();
	var isTemporaryUser = mw.util.isTemporaryUser( blockTarget );

	if ( blockTarget.length === 0 ) {
		$( '.ext-checkuser-tempaccount-specialblock-ips' ).empty();
	}
	if ( isTemporaryUser ) {
		$.get(
			mw.config.get( 'wgScriptPath' ) +
			'/rest.php/checkuser/v0/temporaryaccount/' + blockTarget
		).then( function ( response ) {
			$( '#mw-htmlform-target' ).after(
				$( '<div>' )
					.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
					.append(
						new OO.ui.LabelWidget( {
							label: mw.message(
								'checkuser-tempaccount-specialblock-ips',
								response.ips.length,
								mw.language.listToText( response.ips )
							).text()
						} ).$element
					)
			);
		} );
	}
}
