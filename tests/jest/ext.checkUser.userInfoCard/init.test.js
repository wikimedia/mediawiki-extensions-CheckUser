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
 * @return {Function}
 */
function loadInit( customIcons ) {
	mw.config.get = jest.fn( ( key, fallback ) => (
		key === 'wgCheckUserUserInfoCardCustomIcons' && customIcons !== undefined ?
			customIcons :
			fallback
	) );

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
} );
