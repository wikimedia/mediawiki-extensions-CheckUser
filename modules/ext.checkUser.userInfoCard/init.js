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
	let activeUserId = null;
	let activeWikiId = null;

	// Set up event listeners for the user info card buttons
	const buttons = document.querySelectorAll( '.ext-checkuser-userinfocard-button' );
	buttons.forEach( ( button ) => {
		$( button ).on( 'click', ( event ) => {
			event.preventDefault();

			// fetch wikiId and userId from the classname
			// ext-checkuser-userinfocard-id-$wikiId:$userId
			const idClass = Array.from( button.classList ).find(
				( cls ) => cls.startsWith( 'ext-checkuser-userinfocard-id-' )
			);
			if ( idClass ) {
				const [ wikiId, userId ] = idClass.replace(
					'ext-checkuser-userinfocard-id-', ''
				).split( ':' );
				if ( wikiId && userId ) {
					const isCurrentlyOpen = popoverApp.isPopoverOpen();

					// Check if this is the same button that's currently active and
					// the popover is actually open
					if ( isCurrentlyOpen &&
						activeButton === button &&
						activeUserId === userId &&
						activeWikiId === wikiId ) {
						// If it's the same button and the popover is open, close it
						popoverApp.close();
						activeButton = null;
						activeUserId = null;
						activeWikiId = null;
					} else {
						// If it's a different button, the popover is closed, or no
						// button is active, open the popover
						popoverApp.setUserInfo( userId, wikiId );
						popoverApp.open( event.target );
						activeButton = button;
						activeUserId = userId;
						activeWikiId = wikiId;
					}
				}
			}
		} );
	} );
} );
