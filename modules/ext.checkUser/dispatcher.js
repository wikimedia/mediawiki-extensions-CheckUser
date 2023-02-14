( function () {
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'Investigate' ) {
		require( './investigate/init.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'InvestigateBlock' ) {
		require( './investigateblock/investigateblock.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CheckUser' ) {
		require( './checkuser/cidr.js' );
		require( './checkuser/caMultiLock.js' );
		require( './checkuser/checkUserHelper.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'CheckUserLog' ) {
		require( './checkuserlog/highlightScroll.js' );
	}

	var excludePages = [ 'Investigate', 'InvestigateBlock', 'CheckUser', 'CheckUserLog' ];
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'Contributions':
			if ( mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './temporaryaccount/SpecialContributions.js' );
			}
			break;
		default:
			if ( excludePages.indexOf( mw.config.get( 'wgCanonicalSpecialPageName' ) ) === -1 ) {
				require( './temporaryaccount/init.js' );
			}
	}
}() );
