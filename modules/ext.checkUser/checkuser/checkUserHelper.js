// License: GPLv3 & GPLv2 (dual licensed)
// Modified to match changes to the results and to make it more based around OOUI.
// Original source: https://github.com/Ladsgroup/CheckUserHelper
( function () {
	var showCounts = true;
	var i, len, ipText;
	function createTable( data ) {
		var counter;
		var tbl = document.getElementsByClassName( 'mw-checkuser-helper-table' ).item( 0 );
		if ( !tbl ) {
			return;
		}
		var user;
		for ( user in data ) {
			var tr = tbl.insertRow();
			var td = tr.insertCell();
			var userElement = document.createElement( 'a' );
			userElement.setAttribute(
				'href',
				'/wiki/Special:Contributions/' + mw.util.escapeIdForLink( user )
			);
			userElement.textContent = user;
			td.appendChild( userElement );
			var ips = document.createElement( 'ul' );
			for ( i = 0, len = data[ user ].sorted.ip.length; i < len; i++ ) {
				ipText = data[ user ].sorted.ip[ i ];
				var xffs = Object.keys( data[ user ].ip[ ipText ] );
				var j, xffLen;
				for ( j = 0, xffLen = xffs.length; j < xffLen; j++ ) {
					var xffText = xffs[ j ];
					var xffTypes = Object.keys( data[ user ].ip[ ipText ][ xffText ] );
					var k, xffTypesLen;
					for ( k = 0, xffTypesLen = xffTypes.length; k < xffTypesLen; k++ ) {
						var xffTrusted = xffTypes[ k ];
						var ip = document.createElement( 'li' );
						var ipElement = document.createElement( 'a' );
						ipElement.setAttribute(
							'href',
							'/wiki/Special:Contributions/' + mw.util.escapeIdForLink( ipText )
						);
						ipElement.textContent = ipText;
						ip.appendChild( ipElement );
						if ( xffText !== '' ) {
							var xffPrefix = document.createElement( 'span' );
							if ( xffTrusted === true ) {
								xffPrefix.textContent = ' XFF (trusted): ';
							} else {
								xffPrefix.textContent = ' XFF (untrusted): ';
							}
							var xff = document.createElement( 'span' );
							xff.textContent = xffText;
							ip.appendChild( xffPrefix );
							ip.appendChild( xff );
						}
						if ( showCounts ) {
							counter = document.createElement( 'span' );
							counter.className = 'mw-checkuser-helper-count';
							counter.textContent =
								data[ user ].ip[ ipText ][ xffText ][ xffTrusted ];
							ip.appendChild( counter );
						}
						ips.appendChild( ip );
					}
				}
			}
			td = tr.insertCell();
			td.appendChild( ips );

			var uas = document.createElement( 'ul' );
			for ( i = 0, len = data[ user ].sorted.ua.length; i < len; i++ ) {
				var uaText = data[ user ].sorted.ua[ i ];
				var ua = document.createElement( 'li' );
				var uaCode = document.createElement( 'code' );
				uaCode.textContent = uaText;
				ua.prepend( uaCode );
				if ( showCounts ) {
					counter = document.createElement( 'span' );
					counter.className = 'mw-checkuser-helper-count';
					counter.textContent = data[ user ].ua[ uaText ];
					ua.append( counter );
				}
				uas.appendChild( ua );
			}
			td = tr.insertCell();
			td.appendChild( uas );
		}
	}

	function createTableText( data ) {
		var text = '{| class=wikitable sortable\n! ' + mw.message( 'checkuser-helper-user' ) +
			' !! ' + mw.message( 'checkuser-helper-ips' ) +
			' !! ' + mw.message( 'checkuser-helper-uas' ) + '\n|-\n';

		var user;
		for ( user in data ) {
			text += '|' + user + '||';
			for ( i = 0, len = data[ user ].sorted.ip.length; i < len; i++ ) {
				ipText = data[ user ].sorted.ip[ i ];
				var xffs = Object.keys( data[ user ].ip[ ipText ] );
				for ( var j = 0, xffLen = xffs.length; j < xffLen; j++ ) {
					var xffText = xffs[ j ];
					var xffTypes = Object.keys( data[ user ].ip[ ipText ][ xffText ] );
					for ( var k = 0, xffTypesLen = xffTypes.length; k < xffTypesLen; k++ ) {
						var xffTrusted = xffTypes[ k ];
						text += '\n* ' + ipText;
						if ( xffText !== '' ) {
							var xffPrefix = ' XFF ';
							if ( xffTrusted === true ) {
								xffPrefix += '(trusted)';
							} else {
								xffPrefix += '(untrusted)';
							}
							text += xffPrefix + ': ' + xffText;
						}
						if ( showCounts ) {
							text += ' [' + data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] + ']';
						}
					}
				}
			}
			text += '\n|';

			for ( i = 0, len = data[ user ].sorted.ua.length; i < len; i++ ) {
				var uaText = data[ user ].sorted.ua[ i ];
				text += '\n* <code>' + uaText + '</code> [' + data[ user ].ua[ uaText ] + ']';
			}

			text += '\n|-\n';
		}
		text += '|}';
		return text;
	}

	function compareIPs( a, b ) {
		return calculateIPNumber( a ) - calculateIPNumber( b );
	}

	function calculateIPNumber( ip ) {
		return ip.indexOf( '.' ) > -1 ?
			Number(
				ip.split( '.' ).map(
					function ( num ) {
						return ( '000' + num ).slice( -3 );
					}
				).join( '' )
			) : Number(
				'0x' + ip.split( ':' ).map(
					function ( num ) {
						return ( '0000' + num ).slice( -4 );
					}
				).join( '' )
			);
	}

	function generateData( $checkUserResults ) {
		var data = {};
		var currentPosition = 0;
		var endPosition = 0;
		function processRows() {
			endPosition = currentPosition + 50;
			if ( endPosition > $checkUserResults.length ) {
				endPosition = $checkUserResults.length;
			}
			$checkUserResults.slice( currentPosition, endPosition ).each( function () {
				var user = $( '.mw-checkuser-user-link', this ).text().trim();
				if ( !user ) {
					return;
				}
				if ( !data[ user ] ) {
					data[ user ] = { ip: {}, ua: {} };
				}
				$( '.mw-checkuser-agent', this ).each( function () {
					var uaText = $( this ).text().trim();
					if ( uaText !== '' ) {
						data[ user ].ua[ uaText ] = data[ user ].ua[ uaText ] || 0;
						data[ user ].ua[ uaText ] += 1;
					}
				} );
				$( '.mw-checkuser-ip', this ).each( function () {
					ipText = $( this ).text().trim();
					var $xff;
					if ( $( this ).is( 'li' ) ) {
						$xff = $( '.mw-checkuser-xff', this );
					} else {
						$xff = $( this ).closest( 'li' ).find( '.mw-checkuser-xff' );
					}
					// eslint-disable-next-line no-jquery/no-class-state
					var xffTrusted = $xff.hasClass( 'mw-checkuser-xff-trusted' );
					var xffText = $xff.text().trim();
					if ( ipText !== '' ) {
						if ( !data[ user ].ip[ ipText ] ) {
							data[ user ].ip[ ipText ] = {};
						}
						if ( !data[ user ].ip[ ipText ][ xffText ] ) {
							data[ user ].ip[ ipText ][ xffText ] = {};
						}
						data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] =
							data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] || 0;
						data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] += 1;
					}
				} );
			} );
			currentPosition = endPosition;
			if ( currentPosition < $checkUserResults.length ) {
				// Wait a bit to prevent UI freeze.
				setTimeout( processRows, 10 );
			} else {
				if ( Object.keys( data ).length === 0 ) {
					return;
				}
				// sort IPs and UAs
				// eslint-disable-next-line
				$.each( data, function ( idx ) {
					var ip = Object.keys( data[ idx ].ip );
					ip.sort( compareIPs );
					var ua = Object.keys( data[ idx ].ua );
					data[ idx ].sorted = {
						ip: ip,
						ua: ua.sort()
					};
				} );
				createTable( data );
				var copyText = createTableText( data );
				var dir = ( document.getElementsByTagName( 'html' )[ 0 ].dir === 'ltr' ) ? 'left' : 'right';
				var shortened = new mw.widgets.CopyTextLayout( {
					align: 'top',
					copyText: copyText,
					successMessage: mw.message( 'checkuser-helper-copy-success' ),
					multiline: true,
					failMessage: mw.message( 'checkuser-helper-copy-failed' )
				} );
				shortened.textInput.$element.css( dir, '-9999px' );
				shortened.textInput.$element.css( 'position', 'absolute' );
				shortened.buttonWidget.$element.css( 'position', 'absolute' );
				shortened.buttonWidget.$element.css( dir, '0px' );
				shortened.buttonWidget.$element.after( '<br>' );
				$( '.mw-checkuser-helper-table' ).after( shortened.$element );
			}
		}

		processRows();
	}

	function theGadget() {
		var $checkUserHelperFieldset = $( '.mw-checkuser-helper-fieldset' );
		var $panelLayout = $( '.mw-collapsible-content', $checkUserHelperFieldset );
		showCounts = $( '.mw-checkuser-get-edits-results' ).length !== 0;
		if ( !$panelLayout ) {
			return;
		}
		var $checkUserResults = $( '#checkuserresults li' );
		// eslint-disable-next-line no-jquery/no-class-state
		var tooManyResults = $( '.oo-ui-fieldsetLayout', $checkUserHelperFieldset ).hasClass( 'mw-collapsed' );
		var tbl = document.createElement( 'table' );
		tbl.className = 'wikitable mw-checkuser-helper-table';
		var tr = tbl.insertRow();
		tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-user' ) )[ 0 ] );
		tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-ips' ) )[ 0 ] );
		tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-uas' ) )[ 0 ] );
		$panelLayout.html( tbl );
		mw.loader.using( 'mediawiki.widgets', function () {
			if ( !tooManyResults ) {
				generateData( $checkUserResults );
			} else {
				$checkUserHelperFieldset.one( 'afterExpand.mw-collapsible', function () {
					generateData( $checkUserResults );
				} );
			}
		} );
	}

	if ( !$( '#SummaryTable' ).length ) {
		// Ensure this doesn't run if the summary table already exists
		// Needed temporarily while CheckUserHelper.js may be implemented
		//  through userscripts on wiki.
		theGadget();
	}
}() );
