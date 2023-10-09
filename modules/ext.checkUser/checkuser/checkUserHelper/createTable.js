// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

/**
 * Adds table rows generated from the dictionary provided in the data parameter
 * to the summary table shown inside the collapse layout with the
 * label "See results in a table".
 *
 * The generated rows have columns where the first column is the associated user,
 * the second column is the IPs used and the third column is the User-Agent strings
 * used.
 *
 * This function fires the 'wikipage.content' hook on the summary table after the
 * rows have been added.
 *
 * @param {Object.<string, {ip: {}, ua: {}, sorted: {ip: string[], ua: string[]}}>} data
 *   The result of generateData
 * @param {boolean} showCounts Whether to show the number of times each IP and
 *    User-Agent is used for a particular user.
 */
function createTable( data, showCounts ) {
	var counter;
	var tbl = document.getElementsByClassName( 'mw-checkuser-helper-table' ).item( 0 );
	if ( !tbl ) {
		return;
	}
	var user, ipText, i, len;
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
						if ( xffTrusted === 'true' ) {
							xffPrefix.textContent = ' ' +
								mw.message( 'checkuser-helper-xff-trusted' ) + ' ';
						} else {
							xffPrefix.textContent = ' ' +
								mw.message( 'checkuser-helper-xff-untrusted' ) + ' ';
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
	mw.hook( 'wikipage.content' ).fire( $( '.mw-checkuser-helper-table' ) );
}

module.exports = createTable;
