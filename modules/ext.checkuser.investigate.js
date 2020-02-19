/* eslint-disable no-jquery/no-global-selector */
( function () {
	var dataAttributes = [ 'registration', 'wiki', 'cuc_ip', 'cuc_agent' ],
		toggleButtons = {};

	function getDataKey( $element ) {
		return JSON.stringify( $element.data() );
	}

	function toggleClass( $target, value, classSuffix ) {
		dataAttributes.forEach( function ( dataAttribute ) {
			var $matches,
				dataValue = $target.data( dataAttribute ),
				cellClass = 'ext-checkuser-investigate-table-cell-' + classSuffix,
				rowClass = 'ext-checkuser-investigate-table-row-' + classSuffix;

			if ( dataValue === undefined ) {
				return;
			}

			$matches = $( 'td[data-' + dataAttribute + '="' + dataValue + '"]' );
			$matches.toggleClass( cellClass, value );

			// Rows should be highlighted iff they contain highlighted cells
			$matches.closest( 'tr' ).each( function () {
				$( this ).toggleClass(
					rowClass,
					!!$( this ).find( '.' + cellClass ).length
				);
			} );
		} );
	}

	function toggleClassForHover( event ) {
		// Toggle on for mouseover, off for mouseout
		toggleClass( $( this ), event.type === 'mouseover', 'hover-data-match' );
	}

	function toggleClassForPin( $tableCell, value ) {
		$( '.ext-checkuser-investigate-table' ).toggleClass( 'ext-checkuser-investigate-table-pinned', value );
		$tableCell.toggleClass( 'ext-checkuser-investigate-table-cell-pinned', value );

		toggleButtons[ getDataKey( $tableCell ) ].forEach( function ( button ) {
			button.setValue( value );
		} );
		toggleClass( $tableCell, value, 'pinned-data-match' );
	}

	$( 'td.ext-checkuser-investigate-table-cell-pinnable' ).on( 'mouseover mouseout', toggleClassForHover );

	$( 'td.ext-checkuser-investigate-table-cell-pinnable' ).each( function () {
		var $tableCell = $( this ),
			key = getDataKey( $tableCell ),
			toggleButton = new OO.ui.ToggleButtonWidget( {
				icon: 'pushPin',
				classes: [ 'ext-checkuser-investigate-table-button-pin' ]
			} );

		toggleButtons[ key ] = toggleButtons[ key ] || [];
		toggleButtons[ key ].push( toggleButton );

		toggleButton.on( 'change', toggleClassForPin.bind( this, $tableCell ) );
		$tableCell.append( toggleButton.$element );
	} );
}() );
