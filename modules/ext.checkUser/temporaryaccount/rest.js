/**
 * Perform a REST API request to reveal the IP address used for the given revId. If revId is not
 * specified, this will returnthe last IP address used by a temporary account.
 *
 * @param {string} target
 * @param {string|null} revId
 * @param {boolean} retryOnTokenMismatch
 * @return {Promise}
 */
function performRevealRequest( target, revId, retryOnTokenMismatch ) {
	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}
	var restApi = new mw.Rest();
	var api = new mw.Api();
	var deferred = $.Deferred();
	api.getToken( 'csrf' ).then( function ( token ) {
		restApi.post(
			'/checkuser/v0/temporaryaccount/' + target + buildQuery( revId ),
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
					performRevealRequest( target, revId, false ).then(
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
 * @param {string|null} revId
 * @return {string}
 */
function buildQuery( revId ) {
	var urlParams = '';
	var queryStringParams = new URLSearchParams();
	queryStringParams.set( 'limit', 1 );

	if ( revId ) {
		urlParams += '/revisions/' + revId;
	}

	return urlParams + '?' + queryStringParams.toString();
}

module.exports = performRevealRequest;
