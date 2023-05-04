var $blockTargetWidget = $( '#mw-bi-target' );
var blockTargetWidget;

// This code is also loaded on the "block succeeded" page where there is no form,
// so check for block target widget; if it exists, the form is present
if ( $blockTargetWidget.length ) {
	blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
	blockTargetWidget.on( 'change', onTargetChange );
}

function createButton( text ) {
	return new OO.ui.ButtonWidget( {
		label: text,
		framed: false,
		flags: [ 'progressive' ],
		classes: [ 'ext-checkuser-tempaccount-specialblock-ips-link' ]
	} );
}

function onTargetChange( blockTarget ) {
	$( '.ext-checkuser-tempaccount-specialblock-ips' ).remove();
	if ( !mw.util.isTemporaryUser( blockTarget ) ) {
		return;
	}

	var revealButton = createButton(
		mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' )
	);
	var $container = $( '<div>' )
		.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
		.append( revealButton.$element );
	$( '#mw-htmlform-target' ).after( $container );

	revealButton.once( 'click', function () {
		$container.empty();

		$.get(
			mw.config.get( 'wgScriptPath' ) +
			'/rest.php/checkuser/v0/temporaryaccount/' + blockTarget
		).then( function ( response ) {
			$container.empty()
				.append( new OO.ui.LabelWidget( {
					label: response.ips.length ?
						mw.message(
							'checkuser-tempaccount-specialblock-ips',
							response.ips.length,
							mw.language.listToText( response.ips )
						).text() :
						mw.message(
							'checkuser-tempaccount-no-ip-results',
							Math.round( mw.config.get( 'wgCUDMaxAge' ) / 86400 )
						).text()
				} ).$element );
		} );
	} );
}
