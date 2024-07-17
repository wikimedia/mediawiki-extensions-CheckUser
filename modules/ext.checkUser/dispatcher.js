( function () {
	// Include resources for specific special pages
	switch ( mw.config.get( 'wgCanonicalSpecialPageName' ) ) {
		case 'Investigate':
			require( './investigate/init.js' );
			break;
		case 'InvestigateBlock':
			require( './investigateblock/investigateblock.js' );
			break;
		case 'CheckUser': {
			require( './cidr/cidr.js' );
			require( './checkuser/getUsersBlockForm.js' )();
			const CheckUserHelper = require( './checkuser/checkUserHelper/init.js' );
			CheckUserHelper.init();
			break;
		}
		case 'CheckUserLog':
			require( './checkuserlog/highlightScroll.js' );
			break;
		case 'Block':
			require( './temporaryaccount/SpecialBlock.js' );
			break;
		case 'Recentchanges':
		case 'Watchlist':
			require( './temporaryaccount/initOnHook.js' );
			break;
		case 'Contributions':
			if ( mw.config.get( 'wgRelevantUserName' ) &&
				mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './temporaryaccount/SpecialContributions.js' );
			}
			break;
	}

	// Include resources for all but a few specific special pages
	// and for non-special pages that load this module
	const excludePages = [
		'Investigate',
		'InvestigateBlock',
		'IPContributions',
		'CheckUser',
		'Contributions',
		'Recentchanges',
		'Watchlist'
	];
	if (
		!mw.config.get( 'wgCanonicalSpecialPageName' ) ||
		excludePages.indexOf( mw.config.get( 'wgCanonicalSpecialPageName' ) ) === -1
	) {
		require( './temporaryaccount/initOnLoad.js' )();
	}
}() );
