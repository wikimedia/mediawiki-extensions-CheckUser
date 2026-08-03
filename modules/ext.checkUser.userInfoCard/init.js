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

			$( this ).on( 'click keydown', ( event ) => {
				// For keyboard events, only respond to Enter key
				if ( event.type === 'keydown' &&
					event.key !== 'Enter' &&
					event.keyCode !== 13 ) {
					return;
				}
				event.preventDefault();

				const username = this.getAttribute( 'data-username' );

				if ( username ) {
					togglePopover( this, username );
				}
			} );
		} );
	};

	mw.hook( 'wikipage.content' ).add( attachInfoCardHandlers );
	// T402196 - user link on permalink pages is outside #mw-content-text,
	// so it's not covered by the hook above
	attachInfoCardHandlers( $( '#contentSub' ) );

	UserCardButton.methods.togglePopover = togglePopover;
	module.exports = { UserCardButton };
} );
