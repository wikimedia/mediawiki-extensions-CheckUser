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
	var $checkUserBlockFieldset = $( '.mw-checkuser-massblock fieldset' );
	$checkUserBlockFieldset.append(
		$( '<a>' ).attr( {
			class: 'mw-checkuser-multilock-link',
			href: centralURL
		} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
	);

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes = $( '#checkuserresults li [type=checkbox]' );
	$userCheckboxes.on( 'change', function () {
		$( '.mw-checkuser-multilock-link, .mw-checkuser-multilock-link-header, .mw-checkuser-multilock-link-list' ).remove();
		var names = [];
		var urls = [];
		$userCheckboxes.serializeArray().forEach( function ( obj ) {
			if ( obj.name && obj.name === 'users[]' ) {
				// Only registered accounts (not IPs) can be locked
				if ( !mw.util.isIPAddress( obj.value ) ) {
					names.push( obj.value );
				}
			}
		} );

		// Split the names up into batches of username length of a
		// maximum of 2,000 including the centralURL + other parts
		// of the GET parameters
		var i = 0;
		while ( i < names.length ) {
			var url = centralURL + '?wpTarget=';
			var firstUsername = true;
			while ( i < names.length ) {
				var urlComponent = names[ i ];
				if ( !firstUsername ) {
					urlComponent = '\n' + urlComponent;
				} else {
					firstUsername = false;
				}
				urlComponent = encodeURIComponent( urlComponent );
				if ( urlComponent.length + url.length >= 2000 ) {
					break;
				}
				url += urlComponent;
				i = i + 1;
			}
			urls.push( url );
		}

		// Update the href of the link with the latest change
		if ( urls.length > 1 ) {
			$checkUserBlockFieldset.append(
				$( '<span>' ).attr( {
					class: 'mw-checkuser-multilock-link-header'
				} ).text( mw.msg( 'checkuser-centralauth-multilock-list' ) )
			);
			var links = '';
			urls.forEach( function ( urlToAdd, index ) {
				var $li = $( '<li>' );
				var $a = $( '<a>' );
				$a.attr( 'href', urlToAdd )
					.text( mw.msg( 'checkuser-centralauth-multilock-list-item', index + 1 ) );
				$li.append( $a );
				links += $li[ 0 ].outerHTML;
			} );
			$checkUserBlockFieldset.append(
				$( '<ul>' ).attr( { class: 'mw-checkuser-multilock-link-list' } ).append( links )
			);
		} else {
			$checkUserBlockFieldset.append(
				$( '<a>' ).attr( {
					class: 'mw-checkuser-multilock-link',
					href: urls[ 0 ]
				} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
			);
		}
	} );

}() );
