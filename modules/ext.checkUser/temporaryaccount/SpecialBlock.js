const { performFullRevealRequest } = require( './rest.js' );

let blockTargetWidget, lastUserRequest, lastIpRequest;

/**
 * Run code for use when the Special:Block page loads.
 * This is in a function to allow QUnit testing to call
 * the method directly.
 */
function onLoad() {
	const $blockTargetWidget = $( '#mw-bi-target' );

	// This code is also loaded on the "block succeeded" page where there is no form,
	// so check for block target widget; if it exists, the form is present
	if ( $blockTargetWidget.length ) {
		blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
		blockTargetWidget.on( 'change', ( blockTarget ) => {
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
}

/**
 * Creates the button used to reveal the IPs of a temporary account on Special:Block.
 *
 * @return {OO.ui.ButtonWidget}
 */
function createButton() {
	return new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' ),
		framed: false,
		flags: [ 'progressive' ],
		classes: [ 'ext-checkuser-tempaccount-specialblock-ips-link' ]
	} );
}

/**
 * Handles the change event of the block target widget.
 *
 * @param {string} blockTarget
 */
function onTargetChange( blockTarget ) {
	$( '.ext-checkuser-tempaccount-specialblock-ips' ).remove();
	if ( !mw.util.isTemporaryUser( blockTarget ) ) {
		return;
	}
	const api = new mw.Api();
	lastUserRequest = api.get( {
		action: 'query',
		list: 'users',
		ususers: blockTargetWidget.getValue()
	} );
	lastUserRequest.done( ( data ) => {
		if ( data.query.users[ 0 ].userid ) {
			const revealButton = createButton();
			const $container = $( '<div>' )
				.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
				.append( revealButton.$element );
			$( '#mw-htmlform-target' ).after( $container );

			revealButton.once( 'click', () => {
				performFullRevealRequest( blockTarget, [], [] ).then( ( response ) => {
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
				} ).fail( () => {
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

module.exports = {
	onLoad: onLoad,
	createButton: createButton
};
