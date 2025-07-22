/**
 * Perform a REST API request to reveal the IP address(es) used for given revIds and logIds
 * performed by temporary accounts. If no revIds or logIds are specified, this will return
 * the last IP address used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {Object} aflIds
 * @param {boolean} [retryOnTokenMismatch]
 * @return {Promise}
 */
function performRevealRequest( target, revIds, logIds, aflIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	return performRevealRequestInternal( target, revIds, logIds, aflIds, 1, retryOnTokenMismatch );
}

/**
 * Perform a REST API request to reveal all the IP address(es) used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {Object} aflIds
 * @param {boolean} [retryOnTokenMismatch]
 * @return {Promise}
 */
function performFullRevealRequest( target, revIds, logIds, aflIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	return performRevealRequestInternal( target, revIds, logIds, aflIds, false, retryOnTokenMismatch );
}

function performRevealRequestInternal( target, revIds, logIds, aflIds, limit, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	/**
	 * Formats response data obtained from the backend according to what's
	 * expected by the frontend.
	 *
	 * @param {Object} data Payload returned by the backend.
	 * @param {string} key Name of the property in data that holds the payload.
	 *
	 * @return {{autoReveal: (boolean), ips: string[]}}
	 * @throws Error if the payload is malformed
	 */
	const buildIPsDto = ( data, key ) => {
		// Adjust the response format to what's expected by the caller
		if ( !Object.prototype.hasOwnProperty.call( data, target ) ||
			!Object.prototype.hasOwnProperty.call( data[ target ], key ) ) {
			throw new Error( 'Malformed response' );
		}

		return {
			ips: data[ target ][ key ],
			autoReveal: data.autoReveal
		};
	};

	/**
	 * Takes an array of integers and turns it into an array of strings.
	 *
	 * This is used when building requests for fetching data by log IDs, since
	 * these IDs are integers, but the backend API expects strings.
	 *
	 * @param {number[]} ids Values to cast into strings
	 * @return {string[]}
	 */
	const makeStringIDs = ( ids ) => ids.map( ( id ) => id.toString() );

	api.getToken( 'csrf' ).then( ( token ) => {
		if ( isAbuseFilterLogLookup( aflIds ) ) {
			const request = {
				[ target ]: {
					abuseLogIds: makeStringIDs( aflIds.allIds ),
					revIds: [],
					logIds: [],
					lastUsedIp: true
				}
			};

			performBatchRevealRequestInternal( request, retryOnTokenMismatch )
				.then( ( data ) => deferred.resolve(
					buildIPsDto( data, 'abuseLogIps' )
				) ).catch( ( err ) => {
					deferred.reject( err, {} );
				} );
		} else if ( isRevisionLookup( revIds ) ) {
			const request = {
				[ target ]: {
					revIds: makeStringIDs( revIds.allIds ),
					logIds: [],
					lastUsedIp: true
				}
			};

			performBatchRevealRequestInternal( request, retryOnTokenMismatch )
				.then( ( data ) => deferred.resolve(
					buildIPsDto( data, 'revIps' )
				) ).catch( ( err ) => {
					deferred.reject( err, {} );
				} );
		} else {
			// TODO Using the batch endpoint instead of /logs will be handled via T399712
			// TODO Using the batch endpoint instead of /revisions will be handled via T399713
			restApi.post(
				'/checkuser/v0/temporaryaccount/' + target + buildQuery( revIds, logIds, limit ),
				{ token: token } )
				.then(
					( data ) => {
						deferred.resolve( data );
					},
					( err, errObject ) => {
						if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
							// The CSRF token has expired. Retry the POST with a new token.
							api.badToken( 'csrf' );
							performRevealRequestInternal( target, revIds, logIds, limit, false ).then(
								( data ) => {
									deferred.resolve( data );
								},
								( secondRequestErr, secondRequestErrObject ) => {
									deferred.reject( secondRequestErr, secondRequestErrObject );
								}
							);
						} else {
							deferred.reject( err, errObject );
						}
					}
				);
		}
	} ).catch( ( err, errObject ) => {
		deferred.reject( err, errObject );
	} );

	return deferred.promise();
}

/**
 * @typedef {Object} RevealRequest
 * @property {string[]} revIds
 * @property {string[]} logIds
 * @property {boolean} lastUsedIp
 */

/** @typedef {Map<string, RevealRequest>} BatchRevealRequest */

/** @type {Object<string, Promise>} */
const requests = {};

/**
 * Reveal multiple IP addresses in a single request.
 *
 * @param {BatchRevealRequest} request
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performBatchRevealRequest( request, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	// De-duplicate requests using the same request parameters.
	const serialized = JSON.stringify( request );
	if ( Object.prototype.hasOwnProperty.call( requests, serialized ) ) {
		return requests[ serialized ];
	}

	const requestPromise = performBatchRevealRequestInternal( request, retryOnTokenMismatch )
		.then( ( response ) => {
			delete requests[ serialized ];
			return response;
		} )
		.catch( ( err ) => {
			delete requests[ serialized ];
			return err;
		} );

	requests[ serialized ] = requestPromise;

	return requestPromise;
}

/**
 * @param {BatchRevealRequest} request
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performBatchRevealRequestInternal( request, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post( '/checkuser/v0/batch-temporaryaccount', { token: token, users: request } ).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					performBatchRevealRequestInternal( request, false ).then(
						( data ) => {
							deferred.resolve( data );
						},
						( secondRequestErr, secondRequestErrObject ) => {
							deferred.reject( secondRequestErr, secondRequestErrObject );
						}
					);
				} else {
					deferred.reject( err, errObject );
				}
			}
		);
	} ).catch( ( err, errObject ) => {
		deferred.reject( err, errObject );
	} );

	return deferred.promise();
}

/**
 * Generate the query string and URL parameters for the REST API request.
 *
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {number|false} limit
 * @return {string}
 */
function buildQuery( revIds, logIds, limit ) {
	let urlParams = '';
	const queryStringParams = new URLSearchParams();

	if ( isRevisionLookup( revIds ) ) {
		urlParams += '/revisions/' + revIds.allIds.join( '|' );
	} else if ( isLogLookup( logIds ) ) {
		urlParams += '/logs/' + logIds.allIds.join( '|' );
	} else if ( limit ) {
		queryStringParams.set( 'limit', String( limit ) );
	}

	if ( queryStringParams.toString() === '' ) {
		// Don't append a '?' if there are no query string parameters
		return urlParams;
	}
	return urlParams + '?' + queryStringParams.toString();
}

/**
 * Determine whether to look up IPs for revision IDs.
 *
 * @param {Object} revIds
 * @return {boolean} There are revision IDs
 */
function isRevisionLookup( revIds ) {
	return !!( revIds && revIds.allIds && revIds.allIds.length );
}

/**
 * Determine whether to look up IPs for log IDs.
 *
 * @param {Object} logIds
 * @return {boolean} There are log IDs
 */
function isLogLookup( logIds ) {
	return !!( logIds && logIds.allIds && logIds.allIds.length );
}

/**
 * Determine whether to look up IPs for AbuseFilter log IDs.
 *
 * @param {Object} aflIds
 * @return {boolean} There are revision IDs
 */
function isAbuseFilterLogLookup( aflIds ) {
	return !!( aflIds && aflIds.allIds && aflIds.allIds.length );
}

/**
 * Checks if an error response is caused by providing a bad CSRF token.
 *
 * @param {Object} errObject
 * @return {boolean}
 * @internal
 */
function isBadTokenError( errObject ) {
	return errObject.xhr &&
		errObject.xhr.responseJSON &&
		errObject.xhr.responseJSON.errorKey &&
		errObject.xhr.responseJSON.errorKey === 'rest-badtoken';
}

module.exports = {
	performRevealRequest: performRevealRequest,
	performFullRevealRequest: performFullRevealRequest,
	performBatchRevealRequest: performBatchRevealRequest,
	isRevisionLookup: isRevisionLookup,
	isLogLookup: isLogLookup,
	isAbuseFilterLogLookup: isAbuseFilterLogLookup
};
