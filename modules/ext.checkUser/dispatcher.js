( function () {
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Investigate' ) {
		require( './investigate/init.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'InvestigateBlock' ) {
		require( './investigateblock/investigateblock.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CheckUser' ) {
		require( './checkuser/cidr.js' );
		require( './checkuser/caMultiLock.js' );
	}
}() );
