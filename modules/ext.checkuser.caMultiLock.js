/**
 * Enhance Special:CheckUser's block form with a link to CentralAuth's
 * Special:MultiLock (if installed)
 */
( function () {
	var $userCheckboxes,
		centralURL = mw.config.get( 'wgCUCAMultiLockCentral' );

	if ( !centralURL ) {
		// Ignore. Either this isn't a block form, or CentralAuth isn't setup.
		return;
	}

	// Initialize the link
	$( '#checkuserblock fieldset' ).append(
		$( '<a>' ).attr( {
			id: 'cacu-multilock-link',
			href: centralURL
		} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
	);

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes = $( '#checkuserresults li [type=checkbox]' );
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
		$( '#cacu-multilock-link' ).prop(
			'href',
			centralURL + '?wpTarget=' + encodeURIComponent( names.join( '\n' ) )
		);
	} );

}() );
