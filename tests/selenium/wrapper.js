'use strict';

const Launcher = require( '@wdio/cli' ).default,
	getTestString = require( 'wdio-mediawiki/Util' ).getTestString;

const optsOverride = {
	checkUserAccountUsername: getTestString( 'User-' ),
	checkUserAccountPassword: getTestString()
};

const preSpecsWdio = new Launcher( 'tests/selenium/wdio.pre-specs.conf.js', optsOverride );

preSpecsWdio.run().then( () => {
	const specsWdio = new Launcher( 'tests/selenium/wdio.conf.js', optsOverride );

	specsWdio.run().then( ( code ) => {
		// eslint-disable-next-line n/no-process-exit
		process.exit( code );
	}, () => {
		// eslint-disable-next-line n/no-process-exit
		process.exit( 1 );
		console.error( 'Unable to use Launcher to run the specs.' );
	} );
}, () => {
	console.error( 'Unable to use Launcher to run the pre-specs.' );
	// eslint-disable-next-line n/no-process-exit
	process.exit( 1 );
} );
