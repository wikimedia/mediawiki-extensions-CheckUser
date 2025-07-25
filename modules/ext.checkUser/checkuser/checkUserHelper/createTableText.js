// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

/**
 * Creates wikitext for a table that contains the same data as the table
 * generated by createTable.js. This can be copied through a copy button.
 *
 * The wikitext table has columns for the user, IP addresses used by the
 * user for this row and the User-Agents used by the user for this row.
 *
 * @param {Object.<string, {ip: {}, ua: {}, sorted: {ip: string[], ua: string[]}}>} data
 *   The result of generateData
 * @param {boolean} showCounts Whether to show the number of times each IP and
 *    User-Agent is used for a particular user.
 * @return {string} The wikitext for the table
 */
function createTableText( data, showCounts ) {
	let text = '{| class="wikitable sortable"\n! ' + mw.message( 'checkuser-helper-user' ) +
		' !! ' + mw.message( 'checkuser-helper-ips' ) +
		' !! ' + mw.message( 'checkuser-helper-uas' ) +
		' !! ' + mw.message( 'checkuser-helper-client-hints' );

	text += '\n|-\n';

	let user;
	for ( user in data ) {
		text += '|' + user + '||';
		for ( let i = 0, len = data[ user ].sorted.ip.length; i < len; i++ ) {
			const ipText = data[ user ].sorted.ip[ i ];
			const xffs = Object.keys( data[ user ].ip[ ipText ] );
			for ( let j = 0, xffLen = xffs.length; j < xffLen; j++ ) {
				const xffText = xffs[ j ];
				const xffTypes = Object.keys( data[ user ].ip[ ipText ][ xffText ] );
				for ( let k = 0, xffTypesLen = xffTypes.length; k < xffTypesLen; k++ ) {
					const xffTrusted = xffTypes[ k ];
					text += '\n* ' + ipText;
					if ( xffText !== '' ) {
						let xffPrefix;
						if ( xffTrusted === 'true' ) {
							xffPrefix = ' ' + mw.message( 'checkuser-helper-xff-trusted' );
						} else {
							xffPrefix = ' ' + mw.message( 'checkuser-helper-xff-untrusted' );
						}
						text += xffPrefix + ' ' + xffText;
					}
					if ( showCounts ) {
						text += ' [' + data[ user ].ip[ ipText ][ xffText ][ xffTrusted ] + ']';
					}
				}
			}
		}
		text += '\n|';

		for ( let i = 0, len = data[ user ].sorted.ua.length; i < len; i++ ) {
			const uaText = data[ user ].sorted.ua[ i ];
			text += '\n*' + uaText;
			if ( showCounts ) {
				text += " '''[" + data[ user ].ua[ uaText ] + "]'''";
			}
		}

		text += '\n|';
		for ( let i = 0, len = data[ user ].sorted.uach.length; i < len; i++ ) {
			const clientHintsText = data[ user ].sorted.uach[ i ];
			text += '\n*' + clientHintsText;
			if ( showCounts ) {
				text += " '''[" + data[ user ].uach[ clientHintsText ] + "]'''";
			}
		}

		text += '\n|-\n';
	}
	text += '|}';
	return text;
}

module.exports = createTableText;
