const ipRevealUtils = require( './ipRevealUtils.js' );
const { performRevealRequest, isRevisionLookup, isLogLookup } = require( './rest.js' );

/**
 * Replace a button with an IP address, or a message indicating that the IP address
 * was not found.
 *
 * @param {jQuery} $element The button element
 * @param {string|false} ip IP address, or false if the IP is unavaiable
 * @param {boolean} success The IP lookup was successful. Indicates how to interpret
 *  a value of `false` for the IP address. If the lookup was successful but the IP,
 *  then the IP address is legitimately missing, likely because it has been purged.
 */
function replaceButton( $element, ip, success ) {
	if ( success ) {
		$element.replaceWith(
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
	} else {
		$element.replaceWith(
			$( '<span>' )
				.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
				.text( mw.msg( 'checkuser-tempaccount-reveal-ip-error' ) )
		);
	}
}

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
 * @param {string|*} documentRoot A Document or selector to use as the context
 *  for firing the 'userRevealed' event, handled by buttons within that context.
 * @return {jQuery}
 */
function makeButton( target, revIds, logIds, documentRoot ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

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
	} );

	button.$element.on( 'revealIp', () => {
		button.$element.off( 'revealIp' );

		performRevealRequest( target, revIds, logIds ).then( ( response ) => {
			const ip = response.ips[ ( revIds.targetId || logIds.targetId ) || 0 ];
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			replaceButton( button.$element, ip, true );
			$( documentRoot ).trigger( 'userRevealed', [
				target,
				response.ips,
				isRevisionLookup( revIds ),
				isLogLookup( logIds )
			] );
		} ).fail( () => {
			replaceButton( button.$element, false, false );
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
	$element.on(
		'userRevealed',
		/**
		 * @param {Event} _e
		 * @param {string} userLookup
		 * @param {ips} ips An array of IPs from most recent to oldest, or a map of revision
		 *  or log IDs to the IP address used while making the edit or performing the action.
		 * @param {boolean} isRev The map keys are revision IDs
		 * @param {boolean} isLog The map keys are log IDs
		 */
		( _e, userLookup, ips, isRev, isLog ) => {
			// Find all temp user links that share the username
			const $userLinks = $( '.mw-tempuserlink' ).filter( function () {
				return $( this ).text() === userLookup;
			} );

			// Convert the user links into pointers to the IP reveal button
			let $userButtons = $userLinks.map( ( _i, el ) => $( el ).next( '.ext-checkuser-tempaccount-reveal-ip-button' ) );
			$userButtons = $userButtons.filter( function () {
				return $( this ).length > 0;
			} );

			// The lookup may have returned a map of IDs to IPs or an array of IPs. If it
			// returned an array, but subsequent buttons have IDs, they will need to do
			// another lookup to get the map. Needed for grouped recent changes: T369662
			const ipsIsRevMap = !Array.isArray( ips ) && isRev;
			const ipsIsLogMap = !Array.isArray( ips ) && isLog;
			let $triggerNext;

			$userButtons.each( function () {
				if ( !ips ) {
					replaceButton( $( this ), false, true );
				} else {
					const revId = getRevisionId( $( this ) );
					const logId = getLogId( $( this ) );
					if ( ipsIsRevMap && revId ) {
						replaceButton( $( this ), ips[ revId ], true );
					} else if ( ipsIsLogMap && logId ) {
						replaceButton( $( this ), ips[ logId ], true );
					} else if ( !ipsIsRevMap && !ipsIsLogMap && !revId && !logId ) {
						replaceButton( $( this ), ips[ 0 ], true );
					} else {
						// There is a mismatch, so trigger a new lookup for this button.
						// Each time revealIp is triggered, an API request is performed,
						// so only trigger it for one button at a time, and allow those
						// results to be shared to avoid extra lookups.
						$triggerNext = $( this );
					}
				}
			} );

			if ( $triggerNext ) {
				$triggerNext.trigger( 'revealIp' );
			}
		}
	);
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
	replaceButton: replaceButton,
	enableMultiReveal: enableMultiReveal,
	getRevisionId: getRevisionId,
	getLogId: getLogId
};
