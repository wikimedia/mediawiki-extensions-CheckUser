// Keep in sync with UserInfoIconsHandler::MAX_USERS.
const MAX_ICON_USERS = 500;

// Users which wait for the next icon request, each mapped to the promise that its callers
// wait on. Null when no request is due.
let pendingIconUsers = null;

/**
 * Sends a POST request with a CSRF token to a REST endpoint.
 *
 * @param {string} path Path of the endpoint, with the query string
 * @param {Object} body Request body, without the token
 * @param {boolean} retryOnTokenMismatch Get a new token and send the request again if the
 *   token is not valid
 * @return {jQuery.Promise}
 */
function postWithCsrfToken( path, body, retryOnTokenMismatch ) {
	const api = new mw.Api();
	const deferred = $.Deferred();

	api.getToken( 'csrf' ).then( ( token ) => {
		new mw.Rest().post( path, Object.assign( { token: token }, body ) ).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					postWithCsrfToken( path, body, false ).then(
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
	}, ( err, errObject ) => {
		deferred.reject( err, errObject );
	} );

	return deferred.promise();
}

/**
 * Gets UserInfoCard data for a given username
 *
 * @param {string} username
 * @param {string} [openedFrom] Type of the place which the card is opened from, as returned
 *   by getOpenContext() in util.js
 * @return {jQuery.Promise}
 */
function getUserInfo( username, openedFrom ) {
	// T404682
	const language = mw.config.get( 'wgUserLanguage' );

	// T435585
	const sourcePage = mw.config.get( 'wgPageName' );

	const body = { username: username };
	if ( sourcePage ) {
		body.sourcePage = sourcePage;
	}
	if ( openedFrom ) {
		body.openedFrom = openedFrom;
	}

	return postWithCsrfToken( '/checkuser/v0/userinfo?uselang=' + language, body, true );
}

/**
 * Gets the number of blocked accounts that share IP addresses or browser versions with a user.
 *
 * @param {string} username
 * @return {jQuery.Promise<{ip: {count: number, complete: boolean},
 *   browserVersion: {count: number, complete: boolean}}>}
 */
function getSharedBlockedAccounts( username ) {
	return postWithCsrfToken(
		'/checkuser/v0/userinfo/sharedblocked/' + encodeURIComponent( username ) +
			'?uselang=' + mw.config.get( 'wgUserLanguage' ),
		{},
		true
	);
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

/**
 * Gets the icon variants to show on the UserInfoCard buttons for the given users.
 *
 * Callers which ask about a single user should use getUserIconVariant() instead, because it
 * puts the requests of all the buttons in the same tick together.
 *
 * If more users are asked for than supported by the server in a single request, the function
 * will perform a few requests sequentially and resolve the promise after the last response
 * comes in.
 *
 * @param {string[]} usernames Names of the users. The names do not have to be canonical,
 *   and the response uses them as given here.
 * @return {Promise<Object<string,string>>} Map of the given names to the icon variant for
 *   each of them: 'userAvatar', 'userTemporary', 'userBlocked' or 'suggestedInvestigations'.
 *   Every requested name is present in the map. Rejects with the error details of the first
 *   request which fails, and makes no request after it.
 */
async function getUserIconVariants( usernames ) {
	const icons = {};
	for ( let i = 0; i < usernames.length; i += MAX_ICON_USERS ) {
		const chunk = usernames.slice( i, i + MAX_ICON_USERS );
		Object.assign( icons, await requestUserIconVariants( chunk ) );
	}

	return icons;
}

/**
 * Makes one request for the icon variants of the given users.
 *
 * @param {string[]} usernames Names of the users, at most MAX_ICON_USERS
 * @return {Promise<Object<string,string>>} Map of the given names to the icon variant for
 *   each of them. Rejects with the error details of the request if it fails.
 */
function requestUserIconVariants( usernames ) {
	return new Promise( ( resolve, reject ) => {
		const request = new mw.Rest()
			.post( '/checkuser/v0/userinfo/icons', { users: usernames } );

		request.then(
			( data ) => resolve( data.icons ),
			( code, error ) => {
				const loggedError = new Error( 'Failed to load icon variants for UIC button' );
				/* eslint-disable camelcase */
				loggedError.error_context = {
					code,
					status: error.xhr && error.xhr.status,
					usernames
				};
				/* eslint-enable camelcase */
				mw.errorLogger.logError( loggedError, 'error.checkuser' );
				return reject( error );
			}
		);
	} );
}

/**
 * Gets the icon variant to show on the UserInfoCard button for a user.
 *
 * Calls which happen in the same tick go to the server in one request that
 * deduplicates users.
 *
 * @param {string} username Name of the user, which does not have to be canonical
 * @return {Promise<string>} 'userAvatar', 'userTemporary', 'userBlocked'
 *   or 'suggestedInvestigations'. Rejects if the server named no icon for the user.
 */
function getUserIconVariant( username ) {
	if ( !pendingIconUsers ) {
		pendingIconUsers = new Map();
		// Let the rest of the current tick add its users before the request goes out.
		Promise.resolve().then( flushUserIconVariants );
	}

	if ( !pendingIconUsers.has( username ) ) {
		let resolve, reject;
		const promise = new Promise( ( promiseResolve, promiseReject ) => {
			resolve = promiseResolve;
			reject = promiseReject;
		} );
		pendingIconUsers.set( username, { promise, resolve, reject } );
	}

	return pendingIconUsers.get( username ).promise;
}

/**
 * Requests the icon variants for the users which asked since the last request, and settles
 * what their callers wait on.
 */
function flushUserIconVariants() {
	const pending = pendingIconUsers;
	pendingIconUsers = null;

	const usernames = Array.from( pending.keys() );
	getUserIconVariants( usernames ).then(
		( icons ) => {
			usernames.forEach( ( username ) => {
				if ( username in icons ) {
					pending.get( username ).resolve( icons[ username ] );
				} else {
					pending.get( username ).reject( 'missing-icon' );
				}
			} );
		},
		( error ) => {
			usernames.forEach( ( username ) => {
				pending.get( username ).reject( error );
			} );
		}
	);
}

module.exports = {
	getUserInfo: getUserInfo,
	getSharedBlockedAccounts: getSharedBlockedAccounts,
	getUserIconVariants: getUserIconVariants,
	getUserIconVariant: getUserIconVariant
};
