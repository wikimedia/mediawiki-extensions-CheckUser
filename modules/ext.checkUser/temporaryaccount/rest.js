/**
 * Perform a REST API request to reveal the IP address(es) used for given revIds and logIds
 * performed by temporary accounts. If no revIds or logIds are specified, this will return
 * the last IP address used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performRevealRequest( target, revIds, logIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	return performRevealRequestInternal( target, revIds, logIds, 1, retryOnTokenMismatch );
}

/**
 * Perform a REST API request to reveal all the IP address(es) used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performFullRevealRequest( target, revIds, logIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	return performRevealRequestInternal( target, revIds, logIds, false, retryOnTokenMismatch );
}

function performRevealRequestInternal( target, revIds, logIds, limit, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();
	api.getToken( 'csrf' ).then( ( token ) => {
		restApi.post(
			'/checkuser/v0/temporaryaccount/' + target + buildQuery( revIds, logIds, limit ),
			{ token: token }
		).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if (
					retryOnTokenMismatch &&
					errObject.xhr &&
					errObject.xhr.responseJSON &&
					errObject.xhr.responseJSON.errorKey &&
					errObject.xhr.responseJSON.errorKey === 'rest-badtoken'
				) {
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
	} ).fail( ( err, errObject ) => {
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
 * @return {boolean} There are revision IDs
 */
function isRevisionLookup( revIds ) {
	return !!( revIds && revIds.allIds && revIds.allIds.length );
}

/**
 * Determine whether to look up IPs for log IDs.
 *
 * @return {boolean} There are log IDs
 */
function isLogLookup( logIds ) {
	return !!( logIds && logIds.allIds && logIds.allIds.length );
}

module.exports = {
	performRevealRequest: performRevealRequest,
	performFullRevealRequest: performFullRevealRequest,
	isRevisionLookup: isRevisionLookup,
	isLogLookup: isLogLookup
};
