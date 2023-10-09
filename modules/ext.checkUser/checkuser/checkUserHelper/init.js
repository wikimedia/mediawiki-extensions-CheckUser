// Licence: GPLv3 & GPLv2 (dual licensed)
// Original source: https://github.com/Ladsgroup/CheckUserHelper

var addCopyElement = require( './addCopyElement.js' ),
	createTable = require( './createTable.js' ),
	createTableText = require( './createTableText.js' ),
	generateData = require( './generateData.js' );

/**
 * Initialises the table in the summary collapse layout
 * by adding the headings, and:
 * * Generates the rows in the tables if the box around the table if not collapsed, or
 * * Registers that when the user opens the collapsed box the table rows are generated.
 */
function init() {
	var $checkUserHelperFieldset = $( '.mw-checkuser-helper-fieldset' );
	var $panelLayout = $( '.mw-collapsible-content', $checkUserHelperFieldset );
	if ( !$panelLayout ) {
		return;
	}
	var tbl = document.createElement( 'table' );
	tbl.className = 'wikitable mw-checkuser-helper-table';
	var tr = tbl.insertRow();
	tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-user' ) )[ 0 ] );
	tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-ips' ) )[ 0 ] );
	tr.appendChild( $( '<th>' ).text( mw.message( 'checkuser-helper-uas' ) )[ 0 ] );
	$panelLayout.html( tbl );
	// eslint-disable-next-line no-jquery/no-class-state
	var tooManyResults = $( '.oo-ui-fieldsetLayout', $checkUserHelperFieldset ).hasClass( 'mw-collapsed' );
	mw.loader.using( 'mediawiki.widgets', function () {
		if ( !tooManyResults ) {
			generateAndDisplayData();
		} else {
			$checkUserHelperFieldset.one( 'afterExpand.mw-collapsible', function () {
				generateAndDisplayData();
			} );
		}
	} );
}

/**
 * A function that links together the generateData.js, createTable.js,
 * and createTableText.js files to generate the data and then display
 * it in the summary table. Called by the init function.
 */
function generateAndDisplayData() {
	const showCounts = $( '.mw-checkuser-get-edits-results' ).length !== 0;
	generateData().then( ( data ) => {
		createTable( data, showCounts );
		addCopyElement( createTableText( data, showCounts ) );
	} );
}

module.exports = { init: init };
