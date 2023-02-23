( function () {
	// Include resources for specific special pages
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'Investigate':
			require( './investigate/init.js' );
			break;
		case 'InvestigateBlock':
			require( './investigateblock/investigateblock.js' );
			break;
		case 'CheckUser':
			require( './checkuser/cidr.js' );
			require( './checkuser/caMultiLock.js' );
			require( './checkuser/checkUserHelper.js' );
			break;
		case 'CheckUserLog':
			require( './checkuserlog/highlightScroll.js' );
			break;
		case 'Contributions':
			if ( mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './temporaryaccount/SpecialContributions.js' );
			}
			break;
	}

	// Include resources for all but a few specific special pages
	var excludePages = [
		'Investigate',
		'InvestigateBlock',
		'CheckUser',
		'Contributions'
	];
	if ( excludePages.indexOf( mw.config.get( 'wgCanonicalSpecialPageName' ) ) === -1 ) {
		require( './temporaryaccount/init.js' );
	}
}() );
