/* eslint-disable no-jquery/no-global-selector */

/**
 * Add highlight pinning capability and tool links to tables.
 */
module.exports = function setupTables() {
	// Attributes used for pinnable highlighting
	var highlightData = mw.storage.session.get( 'checkuser-investigate-highlight' ),
		dataAttributes = [ 'registration', 'wiki', 'cuc_ip', 'cuc_agent' ],
		toggleButtons = {};

	// The message 'checkuser-toollinks' was parsed in PHP, since translations
	// may contain wikitext that is too complex for the JS parser:
	// https://www.mediawiki.org/wiki/Manual:Messages_API#Feature_support_in_JavaScript
	mw.messages.set( require( './message.json' ) );

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
			// The following messages can be passed here:
			// * ext-checkuser-investigate-table-cell-hover-data-match
			// * ext-checkuser-investigate-table-cell-pinned-data-match
			$matches.toggleClass( cellClass, value );

			// Rows should be highlighted iff they contain highlighted cells
			$matches.closest( 'tr' ).each( function () {
				// The following messages can be passed here:
				// * ext-checkuser-investigate-table-row-hover-data-match
				// * ext-checkuser-investigate-table-row-pinned-data-match
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

	function onToggleButtonChange( $tableCell, value ) {
		$( '.ext-checkuser-investigate-table' ).toggleClass( 'ext-checkuser-investigate-table-pinned', value );
		$tableCell.toggleClass( 'ext-checkuser-investigate-table-cell-pinned', value );

		toggleButtons[ getDataKey( $tableCell ) ].forEach( function ( button ) {
			button.setValue( value );
			// HACK: Until frameless toggle button is implemented (T249841)
			button.setFlags( { progressive: value } );
		} );
		toggleClass( $tableCell, value, 'pinned-data-match' );

		if ( value ) {
			mw.storage.session.set( 'checkuser-investigate-highlight', getDataKey( $tableCell ) );
		} else {
			mw.storage.session.remove( 'checkuser-investigate-highlight' );
		}
	}

	function filterValue( $tableCell ) {
		$( 'textarea[name=exclude-targets]' ).val( function () {
			return this.value + '\n' + $tableCell.data( 'target' );
		} );
		$( '.mw-htmlform' ).trigger( 'submit' );
	}

	function addTargets( $tableCell, field ) {
		$( 'input[name=targets]' ).val( $tableCell.data( field ) );
		$( '.mw-htmlform' ).trigger( 'submit' );
	}

	function appendButtons( $tableCell, buttonTypes ) {
		// eslint-disable-next-line no-jquery/no-class-state
		var isTarget = $tableCell.hasClass( 'ext-checkuser-compare-table-cell-target' ),
			$optionsContainer = $( '<div>' ).addClass( 'ext-checkuser-investigate-table-options-container' ),
			key = getDataKey( $tableCell ),
			options = [],
			selectWidget,
			toggleButton,
			message,
			$links;

		$tableCell.prepend( $optionsContainer );

		if ( buttonTypes.filter ) {
			options.push( new OO.ui.MenuOptionWidget( {
				icon: 'funnel',
				label: mw.msg( 'checkuser-investigate-compare-table-button-filter-label' ),
				data: { type: 'filter' }
			} ) );
		}

		if ( buttonTypes.addUsers ) {
			options.push( new OO.ui.MenuOptionWidget( {
				disabled: isTarget,
				icon: 'add',
				label: mw.msg( 'checkuser-investigate-compare-table-button-add-user-targets-label' ),
				data: { type: 'addUsers' }
			} ) );
		}

		if ( buttonTypes.addIps ) {
			options.push( new OO.ui.MenuOptionWidget( {
				disabled: isTarget,
				icon: 'add',
				label: mw.msg( 'checkuser-investigate-compare-table-button-add-ip-targets-label' ),
				data: { type: 'addIps' }
			} ) );
		}

		if ( buttonTypes.toolLinks ) {
			message = mw.msg( 'checkuser-investigate-compare-toollinks', $tableCell.data( 'cuc_ip' ) );
			$links = $( '<div>' ).html( message ).find( 'a' );
			$links.each( function ( i, $link ) {
				var label = $link.text,
					href = $link.getAttribute( 'href' );
				options.push( new OO.ui.MenuOptionWidget( {
					icon: 'linkExternal',
					label: label,
					data: {
						type: 'toolLinks',
						href: href
					}
				} ) );
			} );
		}

		if ( options.length > 0 ) {
			selectWidget = new OO.ui.ButtonMenuSelectWidget( {
				icon: 'ellipsis',
				framed: false,
				classes: [ 'ext-checkuser-investigate-table-select' ],
				menu: {
					horizontalPosition: 'end',
					items: options
				}
			} );

			selectWidget.getMenu().on( 'choose', function ( item ) {
				var data = item.getData();
				switch ( data.type ) {
					case 'filter':
						filterValue( $tableCell );
						break;
					case 'addIps':
						addTargets( $tableCell, 'cuc_user_text' );
						break;
					case 'addUsers':
						addTargets( $tableCell, 'cuc_ip' );
						break;
					case 'toolLinks':
						window.open( data.href, '_blank' );
				}
			} );

			$optionsContainer.append( selectWidget.$element );
		}

		if ( buttonTypes.toggle ) {
			toggleButton = new OO.ui.ToggleButtonWidget( {
				icon: 'pushPin',
				framed: false,
				classes: [ 'ext-checkuser-investigate-table-button-pin' ]
			} );
			toggleButtons[ key ] = toggleButtons[ key ] || [];
			toggleButtons[ key ].push( toggleButton );
			toggleButton.on( 'change', onToggleButtonChange.bind( null, $tableCell ) );
			$optionsContainer.append( toggleButton.$element );
		}
	}

	$( 'td.ext-checkuser-investigate-table-cell-pinnable' ).on( 'mouseover mouseout', toggleClassForHover );

	$( '.ext-checkuser-investigate-table-preliminary-check td.ext-checkuser-investigate-table-cell-pinnable' ).each( function () {
		appendButtons( $( this ), { toggle: true } );
	} );
	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-user-agent' ).each( function () {
		appendButtons( $( this ), { toggle: true } );
	} );
	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-ip-target' ).each( function () {
		appendButtons( $( this ), { toggle: true, filter: true, addUsers: true, toolLinks: true } );
	} );
	$( 'td.ext-checkuser-compare-table-cell-user-target' ).each( function () {
		appendButtons( $( this ), { filter: true, addIps: true } );
	} );

	// Persist highlights across paginated tabs
	if (
		highlightData !== null &&
		toggleButtons[ highlightData ] &&
		toggleButtons[ highlightData ].length > 0
	) {
		toggleButtons[ highlightData ][ 0 ].setValue( true );
	}
};
