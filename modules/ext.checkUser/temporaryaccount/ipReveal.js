const ipRevealUtils = require( './ipRevealUtils.js' );
const { performRevealRequest } = require( './rest.js' );

/**
 * Make a button for revealing IP addresses and add a handler for the 'ipReveal'
 * event. The handler will perform an API lookup and replace the button with some
 * resulting information.
 *
 * @param {string} target
 * @param {Object} revIds Object used to perform the API request, containing:
 *  - targetId: revision ID for the passed-in element
 *  - allIds: array of all revision IDs for the passed-in target
 * @param {Object} logIds Object used to perform the API request, containing:
 *  - targetId: log ID for the passed-in element
 *  - allIds: array of all log IDs for the passed-in target
 * @return {jQuery}
 */
function makeButton( target, revIds, logIds ) {
	const button = new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' ),
		framed: false,
		quiet: true,
		flags: [
			'progressive'
		],
		classes: [ 'ext-checkuser-tempaccount-reveal-ip-button' ]
	} );
	button.once( 'click', () => {
		button.$element.trigger( 'revealIp' );
		button.$element.off( 'revealIp' );
		$( document ).trigger( 'userRevealed', [ target ] );
	} );

	button.$element.on( 'revealIp', () => {
		button.$element.off( 'revealIp' );
		performRevealRequest( target, revIds, logIds ).then( ( response ) => {
			const ip = response.ips[ ( revIds.targetId || logIds.targetId ) || 0 ];
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			button.$element.replaceWith(
				ip ?
					$( '<span>' )
						.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
						.append(
							$( '<a>' )
								.attr( 'href', mw.util.getUrl( 'Special:IPContributions/' + ip ) )
								.addClass( 'ext-checkuser-tempaccount-reveal-ip-anchor' )
								.text( ip )
						) :
					$( '<span>' )
						.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
						.text( mw.msg( 'checkuser-tempaccount-reveal-ip-missing' ) )

			);
		} ).fail( () => {
			button.$element.replaceWith(
				$( '<span>' )
					.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
					.text( mw.msg( 'checkuser-tempaccount-reveal-ip-error' ) )
			);
		} );
	} );

	return button.$element;
}

/**
 * Add buttons to a "typical" page. This functionality is here because
 * it is shared between initOnLoad and initOnHook.
 *
 * @param {jQuery} $content
 */
function addButton( $content ) {
	const allRevIds = {};
	const allLogIds = {};
	const $userLinks = $content.find( '.mw-tempuserlink' );

	$userLinks.each( function () {
		addToAllIds( $( this ), allRevIds, getRevisionId );
		addToAllIds( $( this ), allLogIds, getLogId );
	} );

	$userLinks.after( function () {
		const target = $( this ).text();
		const revIds = getIdsForTarget( $( this ), target, allRevIds, getRevisionId );
		const logIds = getIdsForTarget( $( this ), target, allLogIds, getLogId );
		return makeButton( target, revIds, logIds );
	} );
}

/**
 * Add the log or revision ID for a certain element to a map of each target on the page
 * to all the IDs on the page that are relevant to that target.
 *
 * @param {jQuery} $element A user link
 * @param {Object.<string, number[]>} allIds Map to be populated
 * @param {function(jQuery):number|undefined} getId Callback that gets the ID associated
 *  with the $element (which may be undefined).
 */
function addToAllIds( $element, allIds, getId ) {
	const id = getId( $element );
	if ( id ) {
		const target = $element.text();
		if ( !allIds[ target ] ) {
			allIds[ target ] = [];
		}
		allIds[ target ].push( id );
	}
}

/**
 * Get IDs of a certain type (e.g. revision, log) for a certain target user.
 *
 * @param {jQuery} $element
 * @param {string} target
 * @param {Object.<string, number[]>} allIds Map of all targets to their relevant IDs of
 *  one type (revision or log)
 * @param {function(jQuery):number|undefined} getId Callback that gets the ID associated
 *  with the $element (which may be undefined).
 * @return {Object} Object used to perform the API request, containing:
 *  - targetId: ID for the passed-in element
 *  - allIds: array of all IDs of one type for the passed-in target
 */
function getIdsForTarget( $element, target, allIds, getId ) {
	const id = getId( $element );
	let ids;
	if ( id ) {
		ids = allIds[ target ];
	}
	return {
		targetId: id,
		allIds: ids
	};
}

/**
 * Add enable multireveal for a "typical" page. This functionality is here
 * because it is shared between initOnLoad and initOnHook.
 *
 * @param {jQuery} $element
 */
function enableMultiReveal( $element ) {
	$element.on( 'userRevealed', ( _e, userLookup ) => {
		// Find all temp user links that share the username
		let $userLinks = $( '.mw-tempuserlink' ).filter( function () {
			return $( this ).text() === userLookup;
		} );

		// Convert the user links into pointers to the IP reveal button
		$userLinks = $userLinks.map( ( _i, el ) => $( el ).next( '.ext-checkuser-tempaccount-reveal-ip-button' ) );

		// Synthetically trigger a reveal event
		$userLinks.each( function () {
			$( this ).trigger( 'revealIp' );
		} );
	} );
}

/**
 * Get revision ID from the surrounding mw-changeslist-line list item.
 *
 * @param {jQuery} $element
 * @return {number|undefined}
 */
function getRevisionId( $element ) {
	return $element.closest( '[data-mw-revid]' ).data( 'mw-revid' );
}

/**
 * Get log ID from the surrounding mw-changeslist-line list item.
 *
 * @param {jQuery} $element
 * @return {number|undefined}
 */
function getLogId( $element ) {
	return $element.closest( '[data-mw-logid]' ).data( 'mw-logid' );
}

module.exports = {
	makeButton: makeButton,
	addButton: addButton,
	enableMultiReveal: enableMultiReveal,
	getRevisionId: getRevisionId
};
