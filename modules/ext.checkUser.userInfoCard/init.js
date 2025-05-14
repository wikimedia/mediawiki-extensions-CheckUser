'use strict';

$( () => {
	const Vue = require( 'vue' );
	const App = require( './components/App.vue' );
	const Pinia = require( 'pinia' );
	const pinia = Pinia.createPinia();

	const popover = document.createElement( 'div' );
	popover.id = 'ext-checkuser-userinfocard-popover';
	popover.classList.add( 'ext-checkuser-userinfocard-popover' );
	document.body.appendChild( popover );
	const popoverApp = Vue.createApp( App ).use( pinia ).mount( popover );

	const buttons = document.querySelectorAll( '.ext-checkuser-userinfocard-button' );
	buttons.forEach( ( button ) => {
		$( button ).on( 'click', ( event ) => {
			event.preventDefault();

			// fetch wikiId and userId from the classname ext-checkuser-userinfocard-id-$wikiId:$userId
			const idClass = Array.from( button.classList ).find(
				( cls ) => cls.startsWith( 'ext-checkuser-userinfocard-id-' )
			);
			if ( idClass ) {
				const [ wikiId, userId ] = idClass.replace(
					'ext-checkuser-userinfocard-id-', ''
				).split( ':' );
				if ( wikiId && userId ) {
					popoverApp.store.fetchUserInfo( userId, wikiId );
					popoverApp.store.open( event.target );
				}
			}
		} );
	} );
} );
