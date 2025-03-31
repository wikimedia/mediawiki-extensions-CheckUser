const ipRevealUtils = require( './ipRevealUtils.js' );
const { performRevealRequest, performBatchRevealRequest, isRevisionLookup, isLogLookup } = require( './rest.js' );

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

	button.$element.data( 'target', target );
	button.$element.data( 'revIds', revIds );
	button.$element.data( 'logIds', logIds );

	button.once( 'click', () => {
		button.$element.trigger( 'revealIp' );
		button.$element.off( 'revealIp' );
	} );

	button.$element.on( 'revealIp', ( _, ip, batchResponse ) => {
		button.$element.off( 'revealIp' );

		if ( batchResponse ) {
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			replaceButton( button.$element, ip, true );

			let ips = {};
			if ( isRevisionLookup( revIds ) ) {
				revIds.allIds.forEach( ( revId ) => {
					ips[ revId ] = batchResponse[ target ].revIps[ revId ];
				} );
			} else if ( isLogLookup( logIds ) ) {
				logIds.allIds.forEach( ( logId ) => {
					ips[ logId ] = batchResponse[ target ].logIps[ logId ];
				} );
			} else {
				ips = [ ip ];
			}
			$( documentRoot ).trigger( 'userRevealed', [
				target,
				ips,
				isRevisionLookup( revIds ),
				isLogLookup( logIds ),
				batchResponse
			] );

			return;
		}

		performRevealRequest( target, revIds, logIds ).then( ( response ) => {
			const targetIp = response.ips[ ( revIds.targetId || logIds.targetId ) || 0 ];
			if ( !ipRevealUtils.getRevealedStatus( target ) ) {
				ipRevealUtils.setRevealedStatus( target );
			}
			replaceButton( button.$element, targetIp, true );
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
 * Get all temporary account user links inside $content that should have a "Show IP" button.
 *
 * @param {jQuery} $content
 * @return {jQuery} The user links
 */
function getUserLinks( $content ) {
	// Get the "normal" temp user links which are those which are not inside a log entry line.
	const $normalUserLinks = $content.find( '.mw-tempuserlink' ).filter( function () {
		return $( this ).closest( '.mw-logevent-loglines, .mw-changeslist-log-entry, .mw-changeslist-log' ).length === 0;
	} );

	// Get the log line temp user links which are inside log lines that are marked as being
	// performed by a temporary account and support IP reveal.
	const $logLinePerformerUserLinks = $content
		.find( '.mw-changeslist-log, .mw-logevent-loglines, .mw-changeslist-log-entry' )
		.find( '.ext-checkuser-log-line-supports-ip-reveal' )
		.addBack( '.ext-checkuser-log-line-supports-ip-reveal' )
		.map( function () {
			return $( this ).find( '.mw-tempuserlink' ).first().get();
		} );

	return $normalUserLinks.add( $logLinePerformerUserLinks );
}

/**
 * Add IP reveal buttons next to temporary user links on a page. See getUserLinks for which
 * links are excluded.
 *
 * @param {jQuery} $content
 * @return {jQuery} The IP reveal buttons within $content
 */
function addIpRevealButtons( $content ) {
	const allRevIds = {};
	const allLogIds = {};
	const $userLinks = getUserLinks( $content );

	$userLinks.each( function () {
		addToAllIds( $( this ), allRevIds, getRevisionId );
		addToAllIds( $( this ), allLogIds, getLogId );
	} );

	$userLinks.each( function () {
		const target = $( this ).text();
		if ( $( this ).next().is( '.ext-checkuser-tempaccount-reveal-ip-button' ) ) {
			return;
		}
		$( this ).after( function () {
			const revIds = getIdsForTarget( $( this ), target, allRevIds, getRevisionId );
			const logIds = getIdsForTarget( $( this ), target, allLogIds, getLogId );
			return makeButton( target, revIds, logIds );
		} );
	} );

	return $userLinks.next( '.ext-checkuser-tempaccount-reveal-ip-button' );
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
		ids = [ ...new Set( allIds[ target ] ) ];
	}
	return {
		targetId: id,
		allIds: ids
	};
}

/**
 * Enable multi-reveal for a "typical" page, not including contributions pages, which are
 * handled separately in SpecialContributions.js.
 *
 * "Multi-reveal" refers to replacing multiple lookup buttons for the same user with IPs.
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
		 * @param {Object|undefined} batchResponse
		 */
		( _e, userLookup, ips, isRev, isLog, batchResponse ) => {
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
				if ( !ips || ips.length === 0 ) {
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
					} else if ( !ipsIsRevMap && revId && batchResponse ) {
						// If the current button has a revId but the reveal
						// didn't set ipsIsRevMap due to the reveal happening
						// from another button without the revId, and we also
						// have a batch response, we don't need to trigger a
						// new lookup. The data we need should be in the batch
						// response.
						const ip = batchResponse[ userLookup ].revIps[ revId ];
						replaceButton( $( this ), ip, true );
					} else if ( !ipsIsLogMap && logId && batchResponse ) {
						// If the current button has a logId but the reveal
						// didn't set ipsIsLogMap due to the reveal happening
						// from another button without the logId, and we also
						// have a batch response, we don't need to trigger a
						// new lookup. The data we need should be in the batch
						// response.
						const ip = batchResponse[ userLookup ].logIps[ logId ];
						replaceButton( $( this ), ip, true );
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
 * Lookup IP addresses for multiple temporary users in a single REST API call
 * and reveal the respective buttons per reveal request.
 *
 * "Batch reveal" refers to looking up IPs for multiple different temporary users.
 * "Multi-reveal" refers to replacing multiple lookup buttons for the same user with the looked-up
 * IP addresses.
 *
 * @param {Object} request Object used to perform the API request. Keys are temporary user
 *  names and values are objects specifying which IP addresses to look up, containing:
 *  - revIds: array of revision IDs
 *  - logIds: array of log IDs
 *  - lastUsedIp: boolean, whether to look up the most recently used IP
 * @param {jQuery} $ipRevealButtons The buttons to replace with IP addresses
 */
function batchRevealIps( request, $ipRevealButtons ) {
	performBatchRevealRequest( request ).then( ( response ) => {
		// Replace the lookup buttons with the IPs by triggering 'revealIp'.
		$ipRevealButtons.each( function () {
			const target = $( this ).data( 'target' );

			// Skip buttons that got revealed by multi-reveal.
			const $button = $( this );
			if ( !$button.get( 0 ) ) {
				return;
			}

			if ( Object.prototype.hasOwnProperty.call( response, target ) ) {
				const revId = $button.data( 'revIds' ).targetId;
				const logId = $button.data( 'logIds' ).targetId;

				let ip = null;
				if ( revId && response[ target ].revIps !== null ) {
					ip = response[ target ].revIps[ revId ];
				} else if ( logId && response[ target ].logIps !== null ) {
					ip = response[ target ].logIps[ logId ];
				} else if ( response[ target ].lastUsedIp ) {
					ip = response[ target ].lastUsedIp;
				}

				if ( ip !== null ) {
					$button.trigger( 'revealIp', [ ip, response ] );
				}
			}
		} );
	} ).fail( () => {
		$ipRevealButtons.each( function () {
			const target = $( this ).data( 'target' );

			if ( Object.prototype.hasOwnProperty.call( request, target ) ) {
				replaceButton( $( this ), false, false );
			}
		} );
	} );
}

/**
 * Automatically reveal IPs for temporary users, where appropriate. This includes:
 * - Users who have been revealed recently
 * - All users if auto-reveal mode is on
 *
 * Note that this uses the `batch-temporaryaccount` API endpoint.
 *
 * @param {jQuery} $ipRevealButtons
 */
function automaticallyRevealUsers( $ipRevealButtons ) {
	const request = {};
	const usersToReveal = [];
	let $buttonsToReveal;

	if ( ipRevealUtils.getAutoRevealStatus() ) {
		$buttonsToReveal = $ipRevealButtons;
	} else {
		$buttonsToReveal = $ipRevealButtons.filter( function () {
			return ipRevealUtils.getRevealedStatus( $( this ).data( 'target' ) );
		} );
	}

	$buttonsToReveal.each( function () {
		const target = $( this ).data( 'target' );
		const $button = $( this );

		if ( !Object.prototype.hasOwnProperty.call( request, target ) ) {
			request[ target ] = { revIds: [], logIds: [], lastUsedIp: false };
		}
		if (
			$button.data( 'revIds' ).allIds &&
			request[ target ].revIds.length === 0
		) {
			request[ target ].revIds = request[ target ].revIds.concat(
				$button.data( 'revIds' ).allIds.map( ( x ) => String( x ) )
			);
		}
		if (
			$button.data( 'logIds' ).allIds &&
			request[ target ].logIds.length === 0
		) {
			request[ target ].logIds = request[ target ].logIds.concat(
				$button.data( 'logIds' ).allIds.map( ( x ) => String( x ) )
			);
		}
		if ( request[ target ].revIds.length === 0 && request[ target ].logIds.length === 0 ) {
			request[ target ].lastUsedIp = true;
		}

		usersToReveal.push( target );
	} );

	// Trigger a batch lookup for all revealed users.
	if ( usersToReveal.length > 0 ) {
		batchRevealIps( request, $buttonsToReveal );
	}
}

/**
 * Get revision ID from the surrounding DOM. Look in ancestors, then siblings.
 *
 * @param {jQuery} $element
 * @return {number|undefined}
 */
function getRevisionId( $element ) {
	let id = $element.closest( '[data-mw-revid]' ).data( 'mw-revid' );
	if ( id === undefined ) {
		id = $element.siblings( '[data-mw-revid]' ).eq( 0 ).data( 'mw-revid' );
	}
	return id;
}

/**
 * Get log ID from the surrounding DOM. Look in ancestors, then siblings.
 *
 * @param {jQuery} $element
 * @return {number|undefined}
 */
function getLogId( $element ) {
	let id = $element.closest( '[data-mw-logid]' ).data( 'mw-logid' );
	if ( id === undefined ) {
		id = $element.siblings( '[data-mw-logid]' ).eq( 0 ).data( 'mw-logid' );
	}
	return id;
}

/**
 * Add IP reveal functionality to contributions pages that show contributions made by a single
 * temporary user. There are no user links on these pages.
 *
 * This is similar to initOnLoad except with the following customizations:
 * - Since there are no user links, add the buttons for revealing IPs to a specified place
 *   within each revision line.
 * - Use simpler, customized logic for enabling multi-reveal and automatically revealing users,
 *   since the page does not have multiple users, and all buttons are related to revisions.
 *
 * @param {string|*} documentRoot A Document or selector to use as the root of the
 *   search for elements
 * @param {string} pageTitle Declare what page this is being run on.
 *   This is for compatibility across Special:Contributions and Special:DeletedContributions,
 *   as they have different guaranteed existing elements.
 */
function enableIpRevealForContributionsPage( documentRoot, pageTitle ) {
	if ( !documentRoot ) {
		documentRoot = document;
	}

	// Define the class name of the element that the "Show IP" button should be appended after.
	// This can't point to the element yet as it'll be the child of a container revision line.
	let revAppendAfter;
	if ( pageTitle === 'Contributions' ) {
		revAppendAfter = '.mw-diff-bytes';
	} else if ( pageTitle === 'DeletedContributions' ) {
		revAppendAfter = '.mw-deletedcontribs-tools';
	}

	const target = mw.config.get( 'wgRelevantUserName' );
	const revIds = [];

	const $userLinks = $( '#bodyContent', documentRoot ).find( '.mw-contributions-list [data-mw-revid]' );
	$userLinks.each( function () {
		const revId = getRevisionId( $( this ) );
		revIds.push( revId );
	} );
	$userLinks.each( function () {
		const revId = getRevisionId( $( this ) );
		$( this ).find( revAppendAfter ).after( () => {
			const ids = {
				targetId: revId,
				allIds: revIds
			};
			return [
				' ',
				$( '<span>' ).addClass( 'mw-changeslist-separator' ),
				makeButton( target, ids, undefined, documentRoot )
			];
		} );
	} );

	$( documentRoot ).on( 'userRevealed', ( _e, _userLookup, ips ) => {
		$( '.ext-checkuser-tempaccount-reveal-ip-button' ).each( function () {
			const id = $( this ).closest( '[data-mw-revid]' ).data( 'mw-revid' );
			const ip = ( ips && ips[ id ] ) ? ips[ id ] : false;
			replaceButton( $( this ), ip, true );
		} );
	} );

	// If the user has been revealed lately, trigger a lookup from the first button
	if (
		ipRevealUtils.getAutoRevealStatus() ||
		ipRevealUtils.getRevealedStatus( mw.config.get( 'wgRelevantUserName' ) )
	) {
		$( '.ext-checkuser-tempaccount-reveal-ip-button' ).first().trigger( 'revealIp' );
	}
}

module.exports = {
	makeButton: makeButton,
	addIpRevealButtons: addIpRevealButtons,
	replaceButton: replaceButton,
	enableMultiReveal: enableMultiReveal,
	automaticallyRevealUsers: automaticallyRevealUsers,
	batchRevealIps: batchRevealIps,
	getRevisionId: getRevisionId,
	getLogId: getLogId,
	enableIpRevealForContributionsPage: enableIpRevealForContributionsPage
};
