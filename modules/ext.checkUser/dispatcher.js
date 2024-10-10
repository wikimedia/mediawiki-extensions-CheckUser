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
			require( './temporaryaccount/SpecialBlock.js' ).onLoad();
			break;
		case 'Recentchanges':
		case 'Watchlist':
			require( './temporaryaccount/initOnHook.js' )();
			break;
		case 'Contributions':
			if ( mw.config.get( 'wgRelevantUserName' ) &&
				mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './temporaryaccount/SpecialContributions.js' )( document, 'Contributions' );
			}
			break;
		case 'DeletedContributions':
			if ( mw.config.get( 'wgRelevantUserName' ) &&
				mw.util.isTemporaryUser( mw.config.get( 'wgRelevantUserName' ) ) ) {
				require( './temporaryaccount/SpecialContributions.js' )( document, 'DeletedContributions' );
			}
			break;
		case 'IPContributions': {
			// wgRelevantUserName is `null` if a range is the target so check the variable passed from
			// SpecialIPContributions instead.
			const ipRangeTarget = mw.config.get( 'wgIPRangeTarget' );

			// Only trigger if the target is an IP range. A single IP target doesn't need IP reveal buttons.
			if ( ipRangeTarget &&
				mw.util.isIPAddress( ipRangeTarget, true ) &&
				!mw.util.isIPAddress( ipRangeTarget ) ) {
				require( './temporaryaccount/initOnLoad.js' )();
			}
			break;
		}
	}

	// Include resources for all but a few specific special pages
	// and for non-special pages that load this module
	const excludePages = [
		'Investigate',
		'InvestigateBlock',
		'IPContributions',
		'GlobalContributions',
		'CheckUser',
		'Contributions',
		'Recentchanges',
		'Watchlist',
		'Log'
	];
	if (
		!mw.config.get( 'wgCanonicalSpecialPageName' ) ||
		excludePages.indexOf( mw.config.get( 'wgCanonicalSpecialPageName' ) ) === -1
	) {
		require( './temporaryaccount/initOnLoad.js' )();
	}
}() );
