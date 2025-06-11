'use strict';

$( () => {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );

	// Create and append the popover container to the DOM
	const popover = document.createElement( 'div' );
	popover.id = 'ext-checkuser-userinfocard-popover';
	popover.classList.add( 'ext-checkuser-userinfocard-popover' );
	document.body.appendChild( popover );

	const popoverApp = Vue.createMwApp( App ).mount( popover );

	// Track the currently active button and user info
	let activeButton = null;
	let activeUsername = null;

	// Set up event listeners for the user info card buttons
	const buttons = document.querySelectorAll( '.ext-checkuser-userinfocard-button' );
	buttons.forEach( ( button ) => {
		$( button ).on( 'click', ( event ) => {
			event.preventDefault();

			const username = button.getAttribute( 'data-username' );

			if ( username ) {
				popoverApp.setUserInfo( username );
				popoverApp.open( event.target );

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
					popoverApp.open( event.target );
					activeButton = button;
					activeUsername = username;
				}
			}
		} );
	} );
} );
