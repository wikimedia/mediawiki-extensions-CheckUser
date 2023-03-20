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
			var showLink = response.ips.length > 3;
			var messageData, messageText;
			if ( showLink ) {
				messageData = [ response.ips[ 0 ], response.ips[ 1 ] ];
				messageText = new OO.ui.HtmlSnippet(
					mw.message(
						'checkuser-tempaccount-specialblock-ips',
						response.ips.length,
						messageData.join( mw.msg( 'comma-separator' ) )
					).text()
				);

				var button = new OO.ui.ButtonWidget( {
					label: mw.message(
						'checkuser-tempaccount-specialblock-see-more-ips',
						response.ips.length - 2
					).text(),
					framed: false,
					flags: [
						'progressive'
					],
					classes: [ 'ext-checkuser-tempaccount-specialblock-ips-link' ]
				} );

				$( '#mw-htmlform-target' ).after(
					$( '<div>' )
						.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
						.append( new OO.ui.LabelWidget( {
							label: messageText
						} ).$element )
						.append( button.$element )
				);

				button.once( 'click', function () {
					$( '.ext-checkuser-tempaccount-specialblock-ips' ).empty();
					messageText = mw.message(
						'checkuser-tempaccount-specialblock-ips',
						response.ips.length,
						mw.language.listToText( response.ips )
					).text();
					$( '#mw-htmlform-target' ).after(
						$( '<div>' )
							.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
							.append( new OO.ui.LabelWidget( {
								label: messageText
							} ).$element )
					);
				} );

			} else {
				messageText = mw.message(
					'checkuser-tempaccount-specialblock-ips',
					response.ips.length,
					mw.language.listToText( response.ips )
				).text();
				$( '#mw-htmlform-target' ).after(
					$( '<div>' )
						.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
						.append( new OO.ui.LabelWidget( {
							label: messageText
						} ).$element )
				);
			}
		} );
	}
}
