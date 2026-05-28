'use strict';

const { getOpenContext } = require( '../../../modules/ext.checkUser.userInfoCard/util.js' );

describe( 'getOpenContext', () => {
	function setConfig( overrides ) {
		const defaults = {
			wgCanonicalSpecialPageName: false,
			wgAction: 'view'
		};
		mw.config.get = jest.fn( ( key ) => {
			const cfg = Object.assign( {}, defaults, overrides );
			return cfg[ key ];
		} );
	}

	beforeEach( () => {
		setConfig( {} );
	} );

	function makeButton( ancestorHtml ) {
		const wrapper = document.createElement( 'div' );
		wrapper.innerHTML = ancestorHtml;
		document.body.appendChild( wrapper );
		return wrapper.querySelector( 'button' );
	}

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'log', () => {
		it( 'returns "log" on Special:Log by page name', () => {
			setConfig( { wgCanonicalSpecialPageName: 'Log' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'log' } );
		} );

		it( 'returns "log" when trigger is inside .mw-logevent-loglines', () => {
			const button = makeButton(
				'<ul class="mw-logevent-loglines"><li><button></button></li></ul>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'log' } );
		} );

		it( 'returns "log" over "checkuser" when trigger is inside .mw-logevent-loglines on a CheckUser special page', () => {
			setConfig( { wgCanonicalSpecialPageName: 'CheckUser' } );
			const button = makeButton(
				'<ul class="mw-logevent-loglines"><li><button></button></li></ul>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'log' } );
		} );
	} );

	describe( 'checkuser', () => {
		it.each( [ 'CheckUser', 'Investigate', 'SuggestedInvestigations' ] )(
			'returns "checkuser" on Special:%s',
			( specialPage ) => {
				setConfig( { wgCanonicalSpecialPageName: specialPage } );
				const button = document.createElement( 'button' );
				expect( getOpenContext( button ) ).toStrictEqual( { page: 'checkuser' } );
			}
		);
	} );

	describe( 'blocklist', () => {
		it( 'returns "blocklist" on Special:BlockList', () => {
			setConfig( { wgCanonicalSpecialPageName: 'BlockList' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'blocklist' } );
		} );
	} );

	describe( 'rc', () => {
		it( 'returns "rc" on Special:RecentChanges', () => {
			setConfig( { wgCanonicalSpecialPageName: 'Recentchanges' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'rc' } );
		} );
	} );

	describe( 'special', () => {
		it( 'returns "special" on any other special page', () => {
			setConfig( { wgCanonicalSpecialPageName: 'Contributions' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'special' } );
		} );
	} );

	describe( 'history', () => {
		it( 'returns "history" for action=history', () => {
			setConfig( { wgAction: 'history' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'history' } );
		} );

		it( 'returns "history" for action=info', () => {
			setConfig( { wgAction: 'info' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'history' } );
		} );
	} );

	describe( 'page', () => {
		it( 'returns "page" when trigger is inside #mw-content-text', () => {
			const button = makeButton(
				'<div id="mw-content-text"><button></button></div>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'page' } );
		} );
	} );

	describe( 'diff', () => {
		it( 'returns "diff" when trigger is inside #mw-revision-info', () => {
			const button = makeButton(
				'<div id="mw-revision-info"><button></button></div>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'diff' } );
		} );

		it( 'returns "diff" when trigger is inside .diff-title', () => {
			const button = makeButton(
				'<table><tbody><tr class="diff-title"><td><button></button></td></tr></tbody></table>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'diff' } );
		} );

		it( 'returns "diff" over "page" when trigger is inside .diff-title nested in #mw-content-text', () => {
			const button = makeButton(
				'<div id="mw-content-text"><table><tbody><tr class="diff-title"><td><button></button></td></tr></tbody></table></div>'
			);
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'diff' } );
		} );
	} );

	describe( 'other', () => {
		it( 'returns "other" when no condition matches', () => {
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'other' } );
		} );
	} );
} );
