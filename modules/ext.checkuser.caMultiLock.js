/**
 * Adds a link to Special:MultiLock on a central wiki if $wgCheckUserCAMultiLock
 * is configured on the Special:CheckUser's block form
 */
( function () {
	var centralURL = mw.config.get( 'wgCUCAMultiLockCentral' ),
		// eslint-disable-next-line no-jquery/no-global-selector
		$userCheckboxes = $( '#checkuserresults li [type=checkbox]' );

	// Initialize the link
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#checkuserblock fieldset' ).append(
		$( '<a>' ).attr( {
			id: 'cacu-multilock-link',
			href: centralURL
		} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
	);

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes.on( 'change', function () {
		var names = [];
		$userCheckboxes.serializeArray().forEach( function ( obj ) {
			if ( obj.name && obj.name === 'users[]' ) {
				// Only registered accounts (not IPs) can be locked
				if ( !mw.util.isIPAddress( obj.value ) ) {
					names.push( obj.value );
				}
			}
		} );

		// Update the href of the link with the latest change
		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#cacu-multilock-link' ).prop(
			'href',
			centralURL + '?wpTarget=' + encodeURIComponent( names.join( '\n' ) )
		);
	} );

}() );
