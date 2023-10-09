'use strict';

const createTableText = require( '../../../../modules/ext.checkUser/checkuser/checkUserHelper/createTableText.js' );

QUnit.module( 'ext.checkUser.checkUserHelper.createTableText' );

QUnit.test( 'Test that createTableText returns the expected wikitext', function ( assert ) {
	const cases = require( './cases/createTableText.json' );

	cases.forEach( function ( caseItem ) {
		assert.strictEqual(
			createTableText( caseItem.data, caseItem.showCounts ),
			caseItem.expectedWikitext,
			caseItem.msg
		);
	} );
} );
