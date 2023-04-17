var $blockTargetWidget = $( '#mw-bi-target' );
var blockTargetWidget;

// This code is also loaded on the "block succeeded" page where there is no form,
// so check for block target widget; if it exists, the form is present
if ( $blockTargetWidget.length ) {
	blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
	blockTargetWidget.lookupMenu.on( 'choose', updateIPs );
	blockTargetWidget.on( 'change', function () {
		$( '.ext-checkuser-tempaccount-specialblock-ips' ).empty();
	} );
	updateIPs();
}

function updateIPs() {
	$( '.ext-checkuser-tempaccount-specialblock-ips' ).empty();

	var blockTarget = blockTargetWidget.getValue().trim();

	if ( mw.util.isTemporaryUser( blockTarget ) ) {
		$.get(
			mw.config.get( 'wgScriptPath' ) +
			'/rest.php/checkuser/v0/temporaryaccount/' + blockTarget
		).then( function ( response ) {
			var maxDisplayWithoutButton = 3;
			var maxDisplayWithButton = maxDisplayWithoutButton - 1;

			function displayWithoutButton() {
				$( '#mw-htmlform-target' ).after(
					$( '<div>' )
						.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
						.append( new OO.ui.LabelWidget( {
							label: response.ips.length ?
								mw.message(
									'checkuser-tempaccount-specialblock-ips',
									response.ips.length,
									mw.language.listToText( response.ips )
								).text() :
								mw.message(
									'checkuser-tempaccount-no-ip-results',
									mw.config.get( 'wgCUDMaxAge' ) / 86400
								).text()
						} ).$element )
				);
			}

			function displayWithButton() {
				var button = new OO.ui.ButtonWidget( {
					label: mw.message(
						'checkuser-tempaccount-specialblock-see-more-ips',
						response.ips.length - ( maxDisplayWithButton )
					).text(),
					framed: false,
					flags: [
						'progressive'
					],
					classes: [ 'ext-checkuser-tempaccount-specialblock-ips-link' ]
				} );
				button.once( 'click', function () {
					$( '.ext-checkuser-tempaccount-specialblock-ips' ).empty();
					displayWithoutButton();
				} );

				var messageData = response.ips.slice( 0, maxDisplayWithButton );
				var messageText = new OO.ui.HtmlSnippet(
					mw.message(
						'checkuser-tempaccount-specialblock-ips',
						response.ips.length,
						messageData.join( mw.msg( 'comma-separator' ) )
					).text()
				);
				$( '#mw-htmlform-target' ).after(
					$( '<div>' )
						.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
						.append( new OO.ui.LabelWidget( {
							label: messageText
						} ).$element )
						.append( button.$element )
				);
			}

			if ( response.ips.length > maxDisplayWithoutButton ) {
				displayWithButton();
			} else {
				displayWithoutButton();
			}
		} );
	}
}
