/**
 * Composable for handling watchlist functionality for user pages
 *
 * @param {string} username The username to watch/unwatch
 * @param {boolean} initialWatchState The initial watch state
 * @return {Object} The watchlist state and methods
 */
const { ref, computed } = require( 'vue' );

function useWatchList( username, initialWatchState = false ) {
	const isWatched = ref( initialWatchState );

	/**
	 * Toggle the watchlist status for the user page
	 */
	function toggleWatchList() {
		const userPageTitle = mw.Title.makeTitle( 2, username );
		const api = new mw.Api();

		if ( isWatched.value ) {
			api.unwatch( userPageTitle.getPrefixedText() )
				.then( () => {
					isWatched.value = false;
					mw.notify(
						mw.message( 'removedwatchtext', username ),
						{ type: 'success' }
					);
				} )
				.catch( () => {
					mw.notify( mw.message( 'checkuser-userinfocard-error-generic' ), { type: 'error' } );
				} );
		} else {
			api.watch( userPageTitle.getPrefixedText() )
				.then( () => {
					isWatched.value = true;
					mw.notify(
						mw.message( 'addedwatchtext', username ),
						{ type: 'success' }
					);
				} )
				.catch( () => {
					mw.notify( mw.message( 'checkuser-userinfocard-error-generic' ), { type: 'error' } );
				} );
		}
	}

	/**
	 * Computed property for the watchlist label
	 */
	const watchListLabel = computed( () => isWatched.value ?
		mw.msg( 'checkuser-userinfocard-menu-remove-from-watchlist' ) :
		mw.msg( 'checkuser-userinfocard-menu-add-to-watchlist' )
	);

	return {
		toggleWatchList,
		watchListLabel
	};
}

module.exports = useWatchList;
