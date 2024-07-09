'use strict';

const Utils = require( '../../../../modules/ext.checkUser/temporaryaccount/ipRevealUtils.js' );

QUnit.module( 'ext.checkUser.temporaryaccount.ipRevealUtils' );

QUnit.test( 'Test getRevealedStatus when no value set', ( assert ) => {
	assert.strictEqual(
		Utils.getRevealedStatus( 'abcdef' ),
		null,
		'getRevealedStatus return value when setRevealedStatus has not been called'
	);
} );

QUnit.test( 'Test setRevealedStatus', ( assert ) => {
	mw.config.set( 'wgCheckUserTemporaryAccountMaxAge', 1500 );
	Utils.setRevealedStatus( 'abcdef' );
	assert.strictEqual(
		Utils.getRevealedStatus( 'abcdef' ),
		'true',
		'getRevealedStatus return value after setRevealedStatus is called'
	);
	// Remove the cookie after the test to avoid breaking other tests.
	mw.storage.remove( 'mw-checkuser-temp-abcdef' );
} );
