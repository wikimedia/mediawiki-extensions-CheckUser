const { getAutoRevealStatus } = require( './ipRevealUtils.js' );

/**
 * Run code when the page loads.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 */
module.exports = function ( documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	$( '.checkuser-ip-auto-reveal', documentRoot ).on(
		'click',
		() => {
			mw.loader.using( [ 'vue', '@wikimedia/codex' ] ).then( () => {
				$( 'body' ).append(
					$( '<div>' ).attr( { id: 'checkuser-ip-auto-reveal' } )
				);

				let App;
				if ( getAutoRevealStatus() ) {
					App = require( './components/IPAutoRevealOffDialog.vue' );
				} else {
					App = require( './components/IPAutoRevealOnDialog.vue' );
				}
				const Vue = require( 'vue' );
				Vue.createMwApp( App ).mount( '#checkuser-ip-auto-reveal' );
			} );
		} );
};
