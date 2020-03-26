/* eslint-disable no-jquery/no-global-selector */
( function () {
	// Attributes used for pinnable highlighting
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

	function filterValue( $tableCell ) {
		$( 'textarea[name=exclude-targets]' ).val( function () {
			return this.value + '\n' + $tableCell.data( 'target' );
		} );
		$( '.mw-htmlform' ).trigger( 'submit' );
	}

	function appendButtonGroup( $tableCell, buttonTypes ) {
		var key = getDataKey( $tableCell ),
			buttons = [],
			buttonGroup,
			toggleButton,
			filterButton;

		if ( buttonTypes.toggle ) {
			toggleButton = new OO.ui.ToggleButtonWidget( {
				icon: 'pushPin',
				classes: [ 'ext-checkuser-investigate-table-button-pin' ]
			} );
			toggleButtons[ key ] = toggleButtons[ key ] || [];
			toggleButtons[ key ].push( toggleButton );
			toggleButton.on( 'change', toggleClassForPin.bind( null, $tableCell ) );
			buttons.push( toggleButton );
		}

		if ( buttonTypes.filter ) {
			filterButton = new OO.ui.ButtonWidget( {
				icon: 'close',
				classes: [ 'ext-checkuser-investigate-table-button-filter' ]
			} );
			filterButton.on( 'click', filterValue.bind( null, $tableCell ) );
			buttons.push( filterButton );
		}

		if ( buttons.length > 0 ) {
			buttonGroup = new OO.ui.ButtonGroupWidget( {
				items: buttons,
				classes: [ 'ext-checkuser-investigate-table-button-group' ]
			} );
			$tableCell.append( buttonGroup.$element );
		}
	}

	$( 'td.ext-checkuser-investigate-table-cell-pinnable' ).on( 'mouseover mouseout', toggleClassForHover );

	// Add buttons for pinnable highlighting and/or filtering
	$( '.ext-checkuser-investigate-table-preliminary-check td.ext-checkuser-investigate-table-cell-pinnable' ).each( function () {
		appendButtonGroup( $( this ), { toggle: true } );
	} );
	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-ip-target' ).each( function () {
		appendButtonGroup( $( this ), { toggle: true, filter: true } );
	} );
	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-user-agent' ).each( function () {
		appendButtonGroup( $( this ), { toggle: true } );
	} );
	$( 'td.ext-checkuser-compare-table-cell-user-target' ).each( function () {
		appendButtonGroup( $( this ), { filter: true } );
	} );

	function addButtonForExtraTargets( $tableCell, type, field ) {
		// eslint-disable-next-line no-jquery/no-class-state
		var isTarget = $tableCell.hasClass( 'ext-checkuser-compare-table-cell-target' ),
			button = new OO.ui.ButtonWidget( {
				disabled: isTarget,
				// The following messages can be built here:
				// * checkuser-investigate-compare-table-button-add-user-targets-label
				// * checkuser-investigate-compare-table-button-add-ip-targets-label
				label: mw.msg( 'checkuser-investigate-compare-table-button-add-' + type + '-targets-label' ),
				title: isTarget ? mw.msg( 'checkuser-investigate-compare-table-button-add-' + type + '-targets-title' ) : undefined,
				classes: [ 'ext-checkuser-compare-table-button-add-targets' ],
				flags: [ 'primary', 'progressive' ]
			} );

		button.on( 'click', function () {
			$( 'input[name=targets]' ).val( $tableCell.data( field ) );
			$( '.mw-htmlform' ).trigger( 'submit' );
		} );

		$tableCell.append( button.$element );
	}

	// Add buttons for extra user targets
	$( 'td.ext-checkuser-compare-table-cell-user-target' ).each( function () {
		addButtonForExtraTargets( $( this ), 'user', 'cuc_user_text' );
	} );

	// Add buttons for extra IP targets
	$( 'td.ext-checkuser-compare-table-cell-ip-target' ).each( function () {
		addButtonForExtraTargets( $( this ), 'ip', 'cuc_ip' );
	} );
}() );
