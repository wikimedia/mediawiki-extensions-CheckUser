'use strict';

// The card itself, its Vue app and the Codex components are irrelevant here: what is under test is
// the small amount of DOM work init.js does to the trigger buttons on the page, and the API it
// gives to gadgets.
jest.mock( '../../../modules/ext.checkUser.userInfoCard/components/App.vue', () => ( {} ) );
jest.mock( '../../../modules/ext.checkUser.userInfoCard/components/UserCardButton.vue', () => ( {
	methods: {}
} ) );
jest.mock( 'vue', () => ( {
	createMwApp: () => ( {
		mount: () => {
			// The card keeps its own open state, and init.js reads it back to decide whether a
			// button must open or close the card. Keep that state here.
			let isOpen = false;
			global.popoverApp = {
				isPopoverOpen: jest.fn( () => isOpen ),
				open: jest.fn( () => {
					isOpen = true;
				} ),
				close: jest.fn( () => {
					isOpen = false;
				} ),
				setUserInfo: jest.fn()
			};
			return global.popoverApp;
		}
	} )
} ) );

const jquery = require( 'jquery' );

const ICON_BASE = 'ext-checkuser-userinfocard-button__icon';
const ARIA_LABEL_KEY = 'checkuser-userinfocard-toggle-button-aria-label';
const BODY_CLASS = 'ext-checkuser-userinfocard-enabled';

// Ready callbacks that the `$` shim holds back, for the tests which ask for them not to run at
// load time.
let readyCallbacks = [];
let deferReady = false;

/**
 * Run the ready callbacks that the `$` shim held back, as jQuery does when the document becomes
 * ready.
 */
function runReadyCallbacks() {
	const callbacks = readyCallbacks;
	readyCallbacks = [];
	callbacks.forEach( ( callback ) => callback() );
}

// The `wikipage.content` handler that the last loadInit() call registered, or undefined if it
// registered none. It is the only way to reach the in-content preparation, which the module does
// not export.
let contentHandler;

/**
 * Make the viewer one who wants the card, or one who turned it off.
 *
 * @param {boolean} wanted
 */
function setPreference( wanted ) {
	mw.user.isNamed = jest.fn( () => wanted );
	mw.user.options.get = jest.fn( ( key ) => (
		key === 'checkuser-userinfocard-enable' ? wanted : undefined
	) );
}

/**
 * Load init.js with the given custom-icon map exported by the server.
 *
 * The module reads the viewer preference at load time, so call setPreference() before this.
 *
 * @param {Object<string,string>|undefined} customIcons Value of
 *   wgCheckUserUserInfoCardCustomIcons, mapping a target name to its icon variant, or undefined
 *   to simulate the server not exporting it at all
 * @param {string} [relevantUserName] Value of wgRelevantUserName, the user of the page
 * @return {Object} The module exports
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

	contentHandler = undefined;
	mw.hook = jest.fn( () => ( {
		add: ( handler ) => {
			contentHandler = handler;
		}
	} ) );

	let moduleExports;
	jest.isolateModules( () => {
		moduleExports = require( '../../../modules/ext.checkUser.userInfoCard/init.js' );
	} );

	return moduleExports;
}

/**
 * @param {Object} options
 * @param {string} options.username Value of the data-username attribute
 * @param {string} [options.variant] Icon variant baked into the parser output
 * @param {boolean} [options.withIcon] Whether to include the icon span at all
 * @param {string} [options.containerId] Value of the id attribute of the container
 * @return {HTMLElement} Container holding the button
 */
function makeButton( { username, variant = 'userAvatar', withIcon = true, containerId } ) {
	const container = document.createElement( 'div' );
	if ( containerId ) {
		container.id = containerId;
	}
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

function click( element ) {
	element.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
}

function pressKey( element, key ) {
	element.dispatchEvent( new KeyboardEvent( 'keydown', { key, bubbles: true } ) );
}

describe( 'ext.checkUser.userInfoCard init', () => {
	beforeAll( () => {
		// init.js mounts the card inside a jQuery ready callback. Invoke it synchronously so the
		// tests don't depend on ready-queue timing, unless a test asks to hold it back.
		global.$ = function ( arg ) {
			if ( typeof arg === 'function' ) {
				if ( deferReady ) {
					readyCallbacks.push( arg );
				} else {
					arg();
				}
				return;
			}
			return jquery( arg );
		};
		mw.util.isTemporaryUser = jest.fn( ( username ) => username.startsWith( '~' ) );
	} );

	beforeEach( () => {
		document.body.innerHTML = '';
		document.body.className = '';
		readyCallbacks = [];
		deferReady = false;
		global.popoverApp = null;
		setPreference( true );
	} );

	describe( 'blocked target icons', () => {
		it( 'swaps the avatar icon for the blocked icon', () => {
			loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user' } );

			contentHandler( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( false );
		} );

		it( 'swaps the temporary-user icon for the blocked icon', () => {
			loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user', variant: 'userTemporary' } );

			contentHandler( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userTemporary` ) ).toBe( false );
		} );

		it( 'applies the variant the server asked for, rather than a hardcoded one', () => {
			loadInit( { 'Some user': 'loremIpsum' } );
			const container = makeButton( { username: 'Some user' } );

			contentHandler( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--loremIpsum` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( false );
		} );

		it( 'leaves icons of targets that are not blocked alone', () => {
			loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Other user' } );

			contentHandler( jquery( container ) );

			const icon = iconOf( container );
			expect( icon.classList.contains( `${ ICON_BASE }--userAvatar` ) ).toBe( true );
			expect( icon.classList.contains( `${ ICON_BASE }--userBlocked` ) ).toBe( false );
		} );

		it( 'leaves every icon alone when the server exported no custom icons', () => {
			loadInit( undefined );
			const container = makeButton( { username: 'Some user' } );

			contentHandler( jquery( container ) );

			expect(
				iconOf( container ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( false );
		} );

		it( 'matches target names exactly, not by prefix', () => {
			loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user 2' } );

			contentHandler( jquery( container ) );

			expect(
				iconOf( container ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( false );
		} );
	} );

	describe( 'in-content buttons', () => {
		it( 'are prepared by a wikipage.content handler', () => {
			loadInit( {} );

			expect( mw.hook ).toHaveBeenCalledWith( 'wikipage.content' );
			expect( typeof contentHandler ).toBe( 'function' );
		} );

		it( 'are revealed', () => {
			loadInit( {} );
			const container = makeButton( { username: 'Some user' } );

			contentHandler( jquery( container ) );

			expect( buttonOf( container ).hasAttribute( 'hidden' ) ).toBe( false );
		} );

		it( 'open the card when clicked', () => {
			loadInit( {} );
			const container = makeButton( { username: 'Some user' } );

			contentHandler( jquery( container ) );
			click( buttonOf( container ) );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
			expect( global.popoverApp.open ).toHaveBeenCalledWith( buttonOf( container ) );
		} );

		it( 'keep one handler when the same content is prepared again', () => {
			loadInit( {} );
			const container = makeButton( { username: 'Some user' } );

			contentHandler( jquery( container ) );
			contentHandler( jquery( container ) );
			click( buttonOf( container ) );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledTimes( 1 );
			expect( global.popoverApp.close ).not.toHaveBeenCalled();
		} );

		it( 'are prepared in #contentSub as well, which the hook does not reach (T402196)', () => {
			const container = makeButton( {
				username: 'Blocked user', containerId: 'contentSub'
			} );

			loadInit( { 'Blocked user': 'userBlocked' } );
			click( buttonOf( container ) );

			expect( buttonOf( container ).hasAttribute( 'hidden' ) ).toBe( false );
			expect(
				iconOf( container ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( true );
			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Blocked user' );
		} );

		it( 'do not fail the handler when the page holds none', () => {
			loadInit( {} );

			expect( () => contentHandler( jquery( document.body ) ) ).not.toThrow();
		} );

		it( 'are revealed even when they hold no icon', () => {
			loadInit( { 'Blocked user': 'userBlocked' } );
			const container = makeButton( { username: 'Blocked user', withIcon: false } );

			expect( () => contentHandler( jquery( container ) ) ).not.toThrow();
			expect( buttonOf( container ).hasAttribute( 'hidden' ) ).toBe( false );
		} );
	} );

	describe( 'activation', () => {
		/**
		 * @param {string} username
		 * @return {HTMLElement} A button which is ready to open the card
		 */
		function preparedButton( username ) {
			const container = makeButton( { username } );
			contentHandler( jquery( container ) );
			return buttonOf( container );
		}

		it( 'opens the card on Enter', () => {
			loadInit( {} );
			const button = preparedButton( 'Some user' );

			pressKey( button, 'Enter' );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
		} );

		it( 'closes the card when the same button is activated again', () => {
			loadInit( {} );
			const button = preparedButton( 'Some user' );

			click( button );
			click( button );

			expect( global.popoverApp.close ).toHaveBeenCalled();
		} );
	} );

	describe( 'body class', () => {
		it( 'marks the page as one that shows cards', () => {
			loadInit( {} );

			expect( document.body.classList.contains( BODY_CLASS ) ).toBe( true );
		} );
	} );

	describe( 'createButton', () => {
		it( 'builds the same markup as the server does', () => {
			const { createButton } = loadInit( {} );

			const button = createButton( 'Some user' );

			expect( button.tagName ).toBe( 'BUTTON' );
			expect( button.getAttribute( 'type' ) ).toBe( 'button' );
			expect( button.getAttribute( 'data-username' ) ).toBe( 'Some user' );
			expect( button.getAttribute( 'aria-haspopover' ) ).toBe( 'dialog' );
			expect( button.getAttribute( 'aria-label' ) )
				.toBe( `(${ ARIA_LABEL_KEY }, Some user)` );
			expect( button.className.split( ' ' ) ).toEqual( expect.arrayContaining( [
				'ext-checkuser-userinfocard-button',
				'cdx-button',
				'cdx-button--action-default',
				'cdx-button--weight-quiet',
				'cdx-button--icon-only'
			] ) );
			expect( button.hasAttribute( 'hidden' ) ).toBe( false );
			expect(
				iconOf( button ).classList.contains( `${ ICON_BASE }--userAvatar` )
			).toBe( true );
		} );

		it( 'uses the temporary-user icon for a temporary account', () => {
			const { createButton } = loadInit( {} );

			const button = createButton( '~2026-1' );

			expect(
				iconOf( button ).classList.contains( `${ ICON_BASE }--userTemporary` )
			).toBe( true );
		} );

		it( 'uses the icon the server sent for that user', () => {
			const { createButton } = loadInit( { 'Blocked user': 'userBlocked' } );

			const button = createButton( 'Blocked user' );

			expect(
				iconOf( button ).classList.contains( `${ ICON_BASE }--userBlocked` )
			).toBe( true );
		} );

		it( 'opens the card once inserted, without any further call', () => {
			const { createButton } = loadInit( {} );
			const button = createButton( 'Some user' );

			document.body.appendChild( button );
			click( button );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
			expect( global.popoverApp.open ).toHaveBeenCalledWith( button );
		} );

		it( 'keeps one handler when its button goes through wikipage.content', () => {
			const { createButton } = loadInit( {} );
			const container = document.createElement( 'div' );
			container.appendChild( createButton( 'Some user' ) );
			document.body.appendChild( container );

			contentHandler( jquery( container ) );
			click( container.querySelector( '.ext-checkuser-userinfocard-button' ) );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledTimes( 1 );
			expect( global.popoverApp.close ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'attachInfoCardButtonHandler', () => {
		it( 'attaches one handler only, however many times it is called', () => {
			const { attachInfoCardButtonHandler } = loadInit( {} );
			const button = buttonOf( makeButton( { username: 'Some user' } ) );

			attachInfoCardButtonHandler( button );
			attachInfoCardButtonHandler( button );
			attachInfoCardButtonHandler( button );
			click( button );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledTimes( 1 );
			expect( global.popoverApp.close ).not.toHaveBeenCalled();
		} );
	} );

	// A gadget can load the module and use its API at once, which can be before the document is
	// ready. The card is not there yet at that point, because it needs document.body to mount into.
	describe( 'before the card mounts', () => {
		beforeEach( () => {
			deferReady = true;
		} );

		it( 'does nothing when a button is activated', () => {
			const { createButton } = loadInit( {} );
			const button = createButton( 'Some user' );
			document.body.appendChild( button );

			expect( () => click( button ) ).not.toThrow();
			expect( global.popoverApp ).toBe( null );
		} );

		it( 'does nothing when the Vue button asks to toggle the card', () => {
			const { UserCardButton } = loadInit( {} );

			expect(
				() => UserCardButton.methods.togglePopover( document.body, 'Some user' )
			).not.toThrow();
			expect( global.popoverApp ).toBe( null );
		} );

		it( 'keeps the button working once the card is there, with no further call', () => {
			const { createButton } = loadInit( {} );
			const button = createButton( 'Some user' );
			document.body.appendChild( button );
			click( button );

			runReadyCallbacks();
			click( button );

			expect( global.popoverApp.setUserInfo ).toHaveBeenCalledWith( 'Some user' );
			expect( global.popoverApp.open ).toHaveBeenCalledWith( button );
		} );
	} );

	describe( 'a viewer who turned the card off', () => {
		it( 'says so', () => {
			setPreference( false );

			expect( loadInit( {} ).isEnabled() ).toBe( false );
		} );

		it( 'gets no button from createButton', () => {
			setPreference( false );

			expect( loadInit( {} ).createButton( 'Some user' ) ).toBe( null );
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
