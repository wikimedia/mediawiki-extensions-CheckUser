'use strict';

$( () => {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );
	const UserCardButton = require( './components/UserCardButton.vue' );

	// Create and append the popover container to the DOM
	const popover = document.createElement( 'div' );
	popover.id = 'ext-checkuser-userinfocard-popover';
	popover.classList.add( 'ext-checkuser-userinfocard-popover' );
	document.body.appendChild( popover );

	const popoverApp = Vue.createMwApp( App ).mount( popover );

	// Track the currently active button and user info
	let activeButton = null;
	let activeUsername = null;

	const togglePopover = ( button, username ) => {
		const isCurrentlyOpen = popoverApp.isPopoverOpen();

		// Check if this is the same button that's currently active and
		// the popover is actually open
		if ( isCurrentlyOpen &&
			activeButton === button &&
			activeUsername === username ) {
			// If it's the same button and the popover is open, close it
			popoverApp.close();
			activeButton = null;
			activeUsername = null;
		} else {
			// If it's a different button, the popover is closed, or no
			// button is active, open the popover
			popoverApp.setUserInfo( username );
			popoverApp.open( button );
			activeButton = button;
			activeUsername = username;
		}
	};

	/**
	 * Attaches the UserInfoCard event handler to the specified element
	 *
	 * @param {HTMLElement} element The element, which should open the UIC when clicked
	 * @param {string|null} username The user for whom the UIC should show data. If not specified,
	 *     value of the `data-username` attribute on element will be used.
	 */
	const attachInfoCardHandler = ( element, username = null ) => {
		$( element ).on( 'click keydown', ( event ) => {
			// For keyboard events, only respond to Enter key
			if ( event.type === 'keydown' &&
				event.key !== 'Enter' &&
				event.keyCode !== 13 ) {
				return;
			}
			event.preventDefault();

			if ( username === null ) {
				username = element.getAttribute( 'data-username' );
			}

			if ( username ) {
				togglePopover( element, username );
			}
		} );
	};

	// Some users might need a custom icon in their UIC button (i.e., other than userAvatar/userTemporary).
	// Because status designated by such icon can be temporary, it cannot be recorded in the parser cache,
	// and we have to apply it here instead.
	const customAccountIcons = mw.config.get( 'wgCheckUserUserInfoCardCustomIcons', {} );

	const attachInfoCardHandlers = ( $content ) => {
		// FIXME: The popover will lose its position when "Live update" mode
		// is enabled. See T397609 for follow-up work.
		// Set up event listeners for the user info card buttons
		$content.find( '.ext-checkuser-userinfocard-button' ).each( function () {
			// Buttons emitted into page content by {{#uic:}} are part of the parser output that
			// every viewer shares, and they are marked hidden so that consumers of that HTML
			// which load no CheckUser CSS don't show a button that cannot work.
			// Now, given that we know UIC is wanted, we can remove the hidden attribute.
			// It should have no impact (the button is shown with CSS), but let's do it just in case.
			this.removeAttribute( 'hidden' );

			// Correct the icon of buttons whose target needs one other than the variant baked into
			// the parser output. Buttons rendered server-side already carry the right icon, in
			// which case this is a no-op.
			const customIcon = customAccountIcons[ this.getAttribute( 'data-username' ) ];
			if ( customIcon ) {
				const iconElem = this.querySelector( '.cdx-button__icon' );
				if ( iconElem ) {
					iconElem.classList.remove(
						'ext-checkuser-userinfocard-button__icon--userTemporary',
						'ext-checkuser-userinfocard-button__icon--userAvatar'
					);
					// The variant comes from the server, and can be any of the ones
					// UserInfoCardButtonRenderer itself emits. The following CSS classes are used
					// here:
					// * ext-checkuser-userinfocard-button__icon--userAvatar
					// * ext-checkuser-userinfocard-button__icon--userTemporary
					// * ext-checkuser-userinfocard-button__icon--userBlocked
					iconElem.classList.add( 'ext-checkuser-userinfocard-button__icon--' + customIcon );
				}
			}

			attachInfoCardHandler( this );
		} );
	};

	// T403700 - the trigger in the page navigation of user pages and user talk pages is rendered
	// by the skin, which keeps no data attribute with the username, so use the relevant user of
	// the page. UserInfoCardNavigationHandler and wgRelevantUserName use the same source for it.
	const attachNavigationHandlers = () => {
		const relevantUserName = mw.config.get( 'wgRelevantUserName' );
		if ( !relevantUserName ) {
			return;
		}

		// Vector 2022 puts a copy of the item into the page tools dropdown for narrow screens,
		// so there can be more than one trigger.
		$( '.ext-checkuser-userinfocard-navigation-item' ).each( function () {
			// Some skins put the class of the item on the list element, others on the link.
			const trigger = this.matches( 'a' ) ? this : this.querySelector( 'a' );
			if ( !trigger ) {
				return;
			}

			attachInfoCardHandler( trigger, relevantUserName );
		} );
	};

	mw.hook( 'wikipage.content' ).add( attachInfoCardHandlers );
	// T402196 - user link on permalink pages is outside #mw-content-text,
	// so it's not covered by the hook above
	attachInfoCardHandlers( $( '#contentSub' ) );
	attachNavigationHandlers();

	UserCardButton.methods.togglePopover = togglePopover;
	module.exports = { UserCardButton };
} );
