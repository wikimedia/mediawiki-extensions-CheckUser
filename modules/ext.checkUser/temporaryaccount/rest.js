/**
 * Perform a REST API request to reveal the IP address(es) used for given revIds and logIds
 * performed by temporary accounts. If no revIds or logIds are specified, this will return
 * the last IP address used by a temporary account.
 *
 * @param {string} target
 * @param {Object} revIds
 * @param {Object} logIds
 * @param {boolean} retryOnTokenMismatch
 * @returns {Promise}
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
 * @returns {Promise}
 */
function performFullRevealRequest( target, revIds, logIds, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	return performRevealRequestInternal( target, revIds, logIds, false, retryOnTokenMismatch );
}

function performRevealRequestInternal( target, revIds, logIds, limit, retryOnTokenMismatch ) {
	var restApi = new mw.Rest();
	var api = new mw.Api();
	var deferred = $.Deferred();
	api.getToken( 'csrf' ).then( function ( token ) {
		restApi.post(
			'/checkuser/v0/temporaryaccount/' + target + buildQuery( revIds, logIds, limit ),
			{ token: token }
		).then(
			function ( data ) {
				deferred.resolve( data );
			},
			function ( err, errObject ) {
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
						function ( data ) {
							deferred.resolve( data );
						},
						function ( secondRequestErr, secondRequestErrObject ) {
							deferred.reject( secondRequestErr, secondRequestErrObject );
						}
					);
				} else {
					deferred.reject( err, errObject );
				}
			}
		);
	} ).fail( function ( err, errObject ) {
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
 * @returns {string}
 */
function buildQuery( revIds, logIds, limit ) {
	var urlParams = '';
	var queryStringParams = new URLSearchParams();

	if ( revIds && revIds.allIds && revIds.allIds.length ) {
		urlParams += '/revisions/' + revIds.allIds.join( '|' );
	} else if ( logIds && logIds.allIds && logIds.allIds.length ) {
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

module.exports = {
	performRevealRequest: performRevealRequest,
	performFullRevealRequest: performFullRevealRequest
};
