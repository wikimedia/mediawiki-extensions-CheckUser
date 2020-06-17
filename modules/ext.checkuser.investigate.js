/* eslint-disable no-jquery/no-global-selector */
( function () {
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
			message = mw.msg( 'checkuser-toollinks', $tableCell.data( 'cuc_ip' ) );
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

	/**
	 * Feature for copying wikitext version of the Compare results table (T251361).
	 * This feature is available for wikis that have Parsoid/RESTBase.
	 */
	function addCopyFeature() {
		var copyTextLayout, messageWidget, wikitextButton;

		function onWikitextButtonClick() {
			var url, html;

			function getSanitizedHtml( $table ) {
				$table = $table.clone();

				$table.find( '.oo-ui-widget, .ext-checkuser-investigate-table-options-container' ).remove();
				$table.find( '.mw-userlink' )
					.attr( 'rel', 'mw:WikiLink' )
					.attr( 'href', function () {
						return new mw.Uri( $( this ).attr( 'href' ) ).toString();
					} );

				$table.find( '[class]' ).addBack( '[class]' ).removeAttr( 'class' );
				$table.addClass( 'mw-datatable' );

				$table.find( 'tr, td' ).each( function ( i, element ) {
					Object.keys( element.dataset ).forEach( function ( key ) {
						element.removeAttribute( 'data-' + key );
					} );
				} );

				return $table[ 0 ].outerHTML;
			}

			wikitextButton.setDisabled( true );
			copyTextLayout.textInput.pushPending();
			copyTextLayout.toggle( true );

			url = mw.config.get( 'wgVisualEditorConfig' ).fullRestbaseUrl + 'v1/transform/html/to/wikitext/';
			html = getSanitizedHtml( $( '.ext-checkuser-investigate-table-compare' ) );

			$.ajax( url, { data: { html: html }, type: 'POST' } ).then( function ( data ) {
				copyTextLayout.textInput.popPending();
				copyTextLayout.textInput.setValue( data );
			} );
		}

		messageWidget = new OO.ui.MessageWidget( {
			type: 'notice',
			label: mw.msg( 'checkuser-investigate-compare-copy-message-label' ),
			classes: [ 'ext-checkuser-investigate-copy-message' ]
		} );
		messageWidget.setIcon( 'articles' );

		wikitextButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'checkuser-investigate-compare-copy-button-label' ),
			flags: [ 'primary', 'progressive' ]
		} );
		wikitextButton.on( 'click', onWikitextButtonClick );

		copyTextLayout = new mw.widgets.CopyTextLayout( {
			multiline: true,
			align: 'top',
			textInput: {
				autosize: true,
				// The following classes are used here:
				// * mw-editfont-monospace
				// * mw-editfont-sans-serif
				// * mw-editfont-serif
				classes: [ 'mw-editfont-' + mw.user.options.get( 'editfont' ) ]
			}
		} );
		copyTextLayout.toggle( false );

		$( '.oo-ui-indexLayout-stackLayout' ).append(
			messageWidget.$element.append(
				wikitextButton.$element,
				copyTextLayout.$element
			)
		);
	}

	if (
		$( '.ext-checkuser-investigate-table-compare' ).length > 0 &&
		mw.config.get( 'wgVisualEditorConfig' ) &&
		mw.config.get( 'wgVisualEditorConfig' ).fullRestbaseUrl
	) {
		addCopyFeature();
	}

}() );
