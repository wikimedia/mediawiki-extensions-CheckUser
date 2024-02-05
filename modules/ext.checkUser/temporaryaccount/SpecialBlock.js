var { performFullRevealRequest } = require( './rest.js' );

var $blockTargetWidget = $( '#mw-bi-target' );
var blockTargetWidget, lastUserRequest, lastIpRequest;

// This code is also loaded on the "block succeeded" page where there is no form,
// so check for block target widget; if it exists, the form is present
if ( $blockTargetWidget.length ) {
	blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
	blockTargetWidget.on( 'change', function ( blockTarget ) {
		if ( lastUserRequest ) {
			lastUserRequest.abort();
		}
		if ( lastIpRequest ) {
			lastIpRequest.abort();
		}
		onTargetChange( blockTarget );
	} );
	onTargetChange( blockTargetWidget.getValue() );
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
	var api = new mw.Api();
	lastUserRequest = api.get( {
		action: 'query',
		list: 'users',
		ususers: blockTargetWidget.getValue()
	} );
	lastUserRequest.done( function ( data ) {
		if ( data.query.users[ 0 ].userid ) {
			var revealButton = createButton(
				mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' )
			);
			var $container = $( '<div>' )
				.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
				.append( revealButton.$element );
			$( '#mw-htmlform-target' ).after( $container );

			revealButton.once( 'click', function () {
				$container.empty();

				performFullRevealRequest( blockTarget, [], [] ).then( function ( response ) {
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
				} ).fail( function () {
					$container.empty()
						.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
						.append( new OO.ui.LabelWidget( {
							label: mw.message( 'checkuser-tempaccount-reveal-ip-error' ).text()
						} ).$element );
				} );
			} );
		}
	} );
}
