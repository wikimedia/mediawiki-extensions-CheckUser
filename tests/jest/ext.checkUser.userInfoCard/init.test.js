'use strict';

// The card itself, its Vue app and the Codex components are irrelevant here: what is under test is
// the small amount of DOM work init.js does to the trigger buttons on the page.
jest.mock( '../../../modules/ext.checkUser.userInfoCard/components/App.vue', () => ( {} ) );
jest.mock( '../../../modules/ext.checkUser.userInfoCard/components/UserCardButton.vue', () => ( {
	methods: {}
} ) );
jest.mock( 'vue', () => ( {
	createMwApp: () => ( {
		mount: () => {
			global.popoverApp = {
				isPopoverOpen: jest.fn( () => false ),
				open: jest.fn(),
				close: jest.fn(),
				setUserInfo: jest.fn()
			};
			return global.popoverApp;
		}
	} )
} ) );

const jquery = require( 'jquery' );

const ICON_BASE = 'ext-checkuser-userinfocard-button__icon';

/**
 * Load init.js with the given custom-icon map exported by the server, and return the
 * `wikipage.content` handler it registered.
 *
 * @param {Object<string,string>|undefined} customIcons Value of
 *   wgCheckUserUserInfoCardCustomIcons, mapping a target name to its icon variant, or undefined
 *   to simulate the server not exporting it at all
 * @param {string} [relevantUserName] Value of wgRelevantUserName, the user of the page
 * @return {Function}
 */
function loadInit( customIcons, relevantUserName ) {
	mw.config.get = jest.fn( ( key, fallback ) => {
		if ( key === 'wgCheckUserUserInfoCardCustomIcons' && customIcons !== undefined ) {
			return customIcons;
		}
		if ( key === 'wgRelevantUserName' ) {
			return relevantUserName;
		}
		return fallback;
	} );

	let contentHandler;
	mw.hook = jest.fn( () => ( {
		add: ( handler ) => {
			contentHandler = handler;
		}
	} ) );

	jest.isolateModules( () => {
		require( '../../../modules/ext.checkUser.userInfoCard/init.js' );
	} );

	return contentHandler;
}

/**
 * @param {Object} options
 * @param {string} options.username Value of the data-username attribute
 * @param {string} [options.variant] Icon variant baked into the parser output
 * @param {boolean} [options.withIcon] Whether to include the icon span at all
 * @return {HTMLElement} Container holding the button
 */
function makeButton( { username, variant = 'userAvatar', withIcon = true } ) {
	const container = document.createElement( 'div' );
	const icon = withIcon ?
		`<span class="cdx-button__icon ${ ICON_BASE } ${ ICON_BASE }--${ variant }"></span>` :
		'';
	container.innerHTML =
		`<span class="ext-checkuser-userinfocard-button-wrapper">
			<button class="ext-checkuser-userinfocard-button cdx-button" data-username="${ username }" hidden>
				${ icon }
			</button>
		</span>`;
	document.body.appendChild( container );
	return container;
}

/**
 * Add a trigger in the page navigation, as the skins render it. It has to be in the DOM before
 * init.js runs, because init.js attaches to it on load rather than on the wikipage.content hook.
 *
 * @param {Object} [options]
 * @param {boolean} [options.classOnLink] Put the class of the item on the link, as MinervaNeue
 *   does, instead of on the list element
 * @param {boolean} [options.withDropdownCopy] Also add the copy Vector 2022 puts into the page
 *   tools dropdown
 * @return {HTMLElement} Container holding the item
 */
function makeNavigationItem( { classOnLink = false, withDropdownCopy = false } = {} ) {
	const container = document.createElement( 'ul' );
	const itemClass = classOnLink ? '' : 'ext-checkuser-userinfocard-navigation-item';
	const linkClass = classOnLink ? 'ext-checkuser-userinfocard-navigation-item' : '';
	const item = ( idSuffix ) => `
		<li id="ca-checkuser-userinfocard${ idSuffix }" class="mw-list-item ${ itemClass }">
			<a href="#" class="cdx-button cdx-button--icon-only ${ linkClass }">
				<span class="vector-icon mw-ui-icon-wikimedia-userAvatar"></span>
				<span>User info</span>
			</a>
		</li>`;
	container.innerHTML = item( '' ) + ( withDropdownCopy ? item( '-more' ) : '' );
	document.body.appendChild( container );
	return container;
}

function iconOf( container ) {
	return container.querySelector( '.cdx-button__icon' );
}

function buttonOf( container ) {
	return container.querySelector( '.ext-checkuser-userinfocard-button' );
}

describe( 'ext.checkUser.userInfoCard init', () => {
	beforeAll( () => {
		// init.js runs its work inside a jQuery ready callback. Invoke it synchronously so the
		// tests don't depend on ready-queue timing.
		global.$ = function ( arg ) {
			if ( typeof arg === 'function' ) {
				arg();
				return;
			}
			return jquery( arg );
		};
	} );

	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'blocked target icons', () => {
		it( 'swaps the avatar icon for the blocked icon', () => {
			const attach = loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user' } );

			attach( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( false );
		} );

		it( 'swaps the temporary-user icon for the blocked icon', () => {
			const attach = loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user', variant: 'userTemporary' } );

			attach( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userTemporary` ) ).toBe( false );
		} );

		it( 'applies the variant the server asked for, rather than a hardcoded one', () => {
			const attach = loadInit( { 'Some user': 'loremIpsum' } );
			const container = makeButton( { username: 'Some user' } );

			attach( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--loremIpsum` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( false );
		} );

		it( 'leaves icons of targets that are not blocked alone', () => {
			const attach = loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Other user' } );

			attach( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( false );
		} );

		it( 'leaves every icon alone when the server exported no custom icons', () => {
			const attach = loadInit( undefined );
			const container = makeButton( { username: 'Some user' } );

			attach( jquery( container ) );

			expect(
				iconOf( container ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( false );
		} );

		it( 'matches target names exactly, not by prefix', () => {
			const attach = loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user 2' } );

			attach( jquery( container ) );

			expect(
				iconOf( container ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( false );
		} );
	} );

	describe( 'button setup', () => {
		it( 'reveals the button', () => {
			const attach = loadInit( {} );
			const container = makeButton( { username: 'Some user' } );

			attach( jquery( container ) );

			expect( buttonOf( container ).hasAttribute( 'hidden' ) ).toBe( false );
		} );

		it( 'opens the card when a revealed button is clicked', () => {
			const attach = loadInit( {} );
			const container = makeButton( { username: 'Some user' } );

			attach( jquery( container ) );
			buttonOf( container ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
		} );
	} );

	describe( 'page navigation trigger', () => {
		it( 'opens the card for the user of the page', () => {
			const container = makeNavigationItem();
			loadInit( {}, 'Some user' );

			const link = container.querySelector( 'a' );
			link.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
			expect( global.popoverApp.open ).toHaveBeenCalledWith( link );
		} );

		it( 'opens the card when the class of the item is on the link', () => {
			const container = makeNavigationItem( { classOnLink: true } );
			loadInit( {}, 'Some user' );

			container.querySelector( 'a' ).dispatchEvent(
				new MouseEvent( 'click', { bubbles: true } )
			);

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
		} );

		it( 'attaches to the copy in the page tools dropdown as well', () => {
			const container = makeNavigationItem( { withDropdownCopy: true } );
			loadInit( {}, 'Some user' );

			const copy = container.querySelector( '#ca-checkuser-userinfocard-more a' );
			copy.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

			expect( global.popoverApp.open ).toHaveBeenCalledWith( copy );
		} );

		it( 'does nothing when the page has no relevant user', () => {
			const container = makeNavigationItem();
			loadInit( {}, undefined );

			container.querySelector( 'a' ).dispatchEvent(
				new MouseEvent( 'click', { bubbles: true } )
			);

			expect( global.popoverApp.setUserInfo ).not.toHaveBeenCalled();
		} );
	} );
} );
