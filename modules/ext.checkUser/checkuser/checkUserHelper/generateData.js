// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper
'use strict';

const Utils = require( './utils.js' );

/**
 * Generates a dictionary of data that summarises the
 * results from Special:CheckUser.
 *
 * @return {Promise<Object.<string, {ip: {}, ua: {}, sorted: {ip: string[], ua: string[]}}>>}
 */
function generateData() {
	var $checkUserResults = $( '#checkuserresults li' );
	function processRows( data, currentPosition ) {
		var ipText;
		var endPosition = currentPosition + 50;
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
				var xffTrusted;
				if ( $( this ).is( 'li' ) ) {
					$xff = $( '.mw-checkuser-xff', this );
				} else {
					$xff = $( this ).closest( 'li' ).find( '.mw-checkuser-xff' );
				}
				// eslint-disable-next-line no-jquery/no-class-state
				if ( $xff.hasClass( 'mw-checkuser-xff-trusted' ) ) {
					xffTrusted = 'true';
				} else {
					xffTrusted = 'false';
				}
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
			return new Promise(
				function ( resolve ) {
					// Wait a bit to prevent UI freeze.
					setTimeout( function () {
						processRows( data, currentPosition )
							.then( function ( dataFromChild ) {
								resolve( dataFromChild );
							} );
					}, 10 );
				}
			);
		} else {
			if ( Object.keys( data ).length === 0 ) {
				return Promise.resolve( data );
			}
			// sort IPs and UAs
			// eslint-disable-next-line
			$.each( data, function ( idx ) {
				var ip = Object.keys( data[ idx ].ip );
				ip.sort( Utils.compareIPs );
				var ua = Object.keys( data[ idx ].ua );
				ua.sort(); // NOSONAR
				data[ idx ].sorted = {
					ip: ip,
					ua: ua
				};
			} );
			return Promise.resolve( data );
		}
	}

	return processRows( {}, 0 );
}

module.exports = generateData;
