/**
 * Implements the Special:CheckUser's 'Get users' block form which links to
 * Special:InvestigateBlock to do the actual blocking.
 *
 * This also adds links to Special:MultiLock if CentralAuth is installed and the user has
 * the rights to use that special page.
 *
 * @param {string} documentRoot The root element to append hidden forms to. Defaults to 'body'.
 */
module.exports = function ( documentRoot ) {
	var $userCheckboxes = $( '#checkuserresults li [type=checkbox]' ),
		$checkUserBlockFieldset = $( '.mw-checkuser-massblock fieldset' ),
		$blockAccountsButton = $( '.mw-checkuser-massblock-accounts-button', $checkUserBlockFieldset ),
		$blockIPsButton = $( '.mw-checkuser-massblock-ips-button', $checkUserBlockFieldset ),
		selectedAccounts = [],
		selectedIPs = [],
		centralURL = mw.config.get( 'wgCUCAMultiLockCentral' );

	if ( centralURL ) {
		// Initialize the link to Special:MultiLock.
		$checkUserBlockFieldset.append(
			$( '<a>' ).attr( {
				class: 'mw-checkuser-multilock-link',
				href: centralURL
			} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
		);
	}

	/**
	 * Handle a change in the state of the checkboxes. This regenerates the link(s) to
	 * Special:MultiLock as well as updates the list of selected accounts and IPs.
	 */
	function handleCheckboxesChange() {
		// Clear the list of selected IPs and accounts, and then fill these lists from the state of the checkboxes.
		selectedAccounts = [];
		selectedIPs = [];
		$userCheckboxes.serializeArray().forEach( function ( obj ) {
			if ( obj.name && obj.name === 'users[]' ) {
				// Only registered accounts (not IPs) can be locked
				if ( !mw.util.isIPAddress( obj.value, true ) ) {
					selectedAccounts.push( obj.value );
				} else {
					selectedIPs.push( obj.value );
				}
			}
		} );

		if ( !centralURL ) {
			return;
		}

		var urls = [];
		$( '.mw-checkuser-multilock-link, .mw-checkuser-multilock-link-header, .mw-checkuser-multilock-link-list' ).remove();
		// Split the names up into batches of username length of a
		// maximum of 2,000 including the centralURL + other parts
		// of the GET parameters
		var i = 0;
		while ( i < selectedAccounts.length ) {
			var url = centralURL + '?wpTarget=';
			var firstUsername = true;
			while ( i < selectedAccounts.length ) {
				var urlComponent = selectedAccounts[ i ];
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
				var $a = $( '<a>' ).attr( 'class', 'mw-checkuser-multilock-link' );
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
	}

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes.on( 'change', function () {
		handleCheckboxesChange();
	} );

	// Initialize the selected accounts and IPs, as the checkboxes may have been pre-selected.
	handleCheckboxesChange();

	/**
	 * Open the Special:InvestigateBlock page in a new tab with the given targets.
	 *
	 * @param {string[]} targets
	 */
	function openSpecialInvestigateBlockPage( targets ) {
		var $form = $( '<form>' ).attr( {
			action: new mw.Title( 'Special:InvestigateBlock' ).getUrl(),
			method: 'post',
			target: '_blank'
		} ).addClass( [ 'oo-ui-element-hidden', 'ext-checkuser-hidden-block-form' ] );

		$form.append( $( '<input>' ).attr( {
			type: 'hidden',
			name: 'wpTargets',
			value: targets.join( '\n' )
		} ) );

		if ( !documentRoot ) {
			documentRoot = 'body';
		}
		$form.appendTo( documentRoot ).trigger( 'submit' );
	}

	// If the 'Block accounts' or 'Block IPs' button is pressed, then open the block form in
	// a new tab for the user.
	$blockAccountsButton[ 0 ].addEventListener( 'click', function () {
		openSpecialInvestigateBlockPage( selectedAccounts );
	} );
	$blockIPsButton[ 0 ].addEventListener( 'click', function () {
		openSpecialInvestigateBlockPage( selectedIPs );
	} );
};
