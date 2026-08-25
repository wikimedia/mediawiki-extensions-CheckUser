'use strict';

const {
	getOpenContext,
	isUserInfoCardEnabled
} = require( '../../../modules/ext.checkUser.userInfoCard/util.js' );

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

	describe( 'user-page-toolbar', () => {
		function makeNavigationTrigger( { classOnLink = false } = {} ) {
			const wrapper = document.createElement( 'ul' );
			const itemClass = classOnLink ? '' : 'ext-checkuser-userinfocard-navigation-item';
			const linkClass = classOnLink ? 'ext-checkuser-userinfocard-navigation-item' : '';
			wrapper.innerHTML =
				`<li id="ca-checkuser-userinfocard" class="${ itemClass }">` +
				`<a href="#" class="${ linkClass }">User info</a></li>`;
			document.body.appendChild( wrapper );
			return wrapper.querySelector( 'a' );
		}

		it( 'returns "user-page-toolbar" when the class of the item is on the list element', () => {
			expect( getOpenContext( makeNavigationTrigger() ) )
				.toStrictEqual( { page: 'user-page-toolbar' } );
		} );

		it( 'returns "user-page-toolbar" when the class of the item is on the link', () => {
			expect( getOpenContext( makeNavigationTrigger( { classOnLink: true } ) ) )
				.toStrictEqual( { page: 'user-page-toolbar' } );
		} );

		it( 'returns "user-page-toolbar" over "history" on a history page', () => {
			setConfig( { wgAction: 'history' } );
			expect( getOpenContext( makeNavigationTrigger() ) )
				.toStrictEqual( { page: 'user-page-toolbar' } );
		} );
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
		it.each( [ 'CheckUser', 'Investigate' ] )(
			'returns "checkuser" on Special:%s',
			( specialPage ) => {
				setConfig( { wgCanonicalSpecialPageName: specialPage } );
				const button = document.createElement( 'button' );
				expect( getOpenContext( button ) ).toStrictEqual( { page: 'checkuser' } );
			}
		);
	} );

	describe( 'suggested-investigations', () => {
		it( 'returns "suggested-investigations" on Special:SuggestedInvestigations', () => {
			setConfig( { wgCanonicalSpecialPageName: 'SuggestedInvestigations' } );
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'suggested-investigations' } );
		} );
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
		it( 'returns "page" when trigger is inside .mw-parser-output', () => {
			const button = makeButton(
				'<div class="mw-parser-output"><button></button></div>'
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
	} );

	describe( 'other', () => {
		it( 'returns "other" when no condition matches', () => {
			const button = document.createElement( 'button' );
			expect( getOpenContext( button ) ).toStrictEqual( { page: 'other' } );
		} );
	} );
} );

describe( 'isUserInfoCardEnabled', () => {
	function setViewer( isNamed, preference ) {
		mw.user.isNamed = jest.fn( () => isNamed );
		mw.user.options.get = jest.fn( ( key ) => (
			key === 'checkuser-userinfocard-enable' ? preference : undefined
		) );
	}

	it( 'is on for a named user who set the preference', () => {
		setViewer( true, '1' );
		expect( isUserInfoCardEnabled() ).toBe( true );
	} );

	it( 'is off for a named user who did not set the preference', () => {
		setViewer( true, '' );
		expect( isUserInfoCardEnabled() ).toBe( false );
	} );

	it( 'is off when the preference is absent', () => {
		setViewer( true, undefined );
		expect( isUserInfoCardEnabled() ).toBe( false );
	} );

	it( 'is off for a viewer who is not a named user', () => {
		setViewer( false, '1' );
		expect( isUserInfoCardEnabled() ).toBe( false );
	} );
} );
