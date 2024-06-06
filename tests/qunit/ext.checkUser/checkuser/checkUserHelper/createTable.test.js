'use strict';

const createTable = require( '../../../../../modules/ext.checkUser/checkuser/checkUserHelper/createTable.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.createTable', QUnit.newMwEnvironment() );

QUnit.test( 'Test that createTable makes the expected table', ( assert ) => {
	const cases = require( './cases/createTable.json' );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );

	cases.forEach( ( caseItem ) => {
		function performTest( expectedHtml, msg ) {
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
				expectedHtml,
				msg
			);
		}

		mw.config.set( 'wgCheckUserDisplayClientHints', false );
		performTest( caseItem.expectedHtml, caseItem.msg + '.' );

		mw.config.set( 'wgCheckUserDisplayClientHints', true );
		performTest(
			caseItem.expectedHtmlWhenClientHintsEnabled,
			caseItem.msg + ' with Client Hints display enabled.'
		);
	} );
} );
