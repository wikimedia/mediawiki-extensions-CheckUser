'use strict';

const createTable = require( '../../../../modules/ext.checkUser/checkuser/checkUserHelper/createTable.js' );

QUnit.module( 'ext.checkUser.checkUserHelper.createTable' );

QUnit.test( 'Test that createTable makes the expected table', function ( assert ) {
	const cases = require( './cases/createTable.json' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	cases.forEach( function ( caseItem ) {
		const node = document.createElement( 'table' );
		node.className = 'mw-checkuser-helper-table';
		$qunitFixture.html( node );
		createTable( caseItem.data, caseItem.showCounts );
		let $actualHtmlElement = $( node );
		if ( $actualHtmlElement.find( 'tbody' ).length ) {
			$actualHtmlElement = $actualHtmlElement.find( 'tbody' )[ 0 ];
		}
		let actualHtml = $actualHtmlElement.innerHTML;
		if ( actualHtml === undefined ) {
			actualHtml = '';
		}
		assert.strictEqual(
			actualHtml,
			caseItem.expectedHtml,
			caseItem.msg
		);
	} );
} );
