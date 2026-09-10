// Keep in sync with UserInfoIconsHandler::MAX_USERS.
const MAX_ICON_USERS = 500;

// Users which wait for the next icon request, each mapped to the promise that its callers
// wait on. Null when no request is due.
let pendingIconUsers = null;

/**
 * Gets UserInfoCard data for a given username
 *
 * @param {string} username
 * @param {string} [openedFrom] Type of the place which the card is opened from, as returned
 *   by getOpenContext() in util.js
 * @param {boolean} [retryOnTokenMismatch=true]
 * @return {Promise<{caseId: number, status: string, reason: string, formattedReason: string}>}
 */
function getUserInfo( username, openedFrom, retryOnTokenMismatch ) {
	const restApi = new mw.Rest();
	const api = new mw.Api();
	const deferred = $.Deferred();

	if ( retryOnTokenMismatch === undefined ) {
		// Default value for the argument is true.
		retryOnTokenMismatch = true;
	}

	// T404682
	const language = mw.config.get( 'wgUserLanguage' );

	// T435585
	const sourcePage = mw.config.get( 'wgPageName' );

	api.getToken( 'csrf' ).then( ( token ) => {
		const body = {
			token: token,
			username: username
		};

		if ( sourcePage ) {
			body.sourcePage = sourcePage;
		}

		if ( openedFrom ) {
			body.openedFrom = openedFrom;
		}

		restApi.post(
			'/checkuser/v0/userinfo?uselang=' + language,
			body
		).then(
			( data ) => {
				deferred.resolve( data );
			},
			( err, errObject ) => {
				if ( retryOnTokenMismatch && isBadTokenError( errObject ) ) {
					// The CSRF token has expired. Retry the POST with a new token.
					api.badToken( 'csrf' );
					getUserInfo( username, openedFrom, false ).then(
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
	} );

	return deferred.promise();
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
 * @param {string[]} usernames Names of the users, at most 500. The names do not have to be
 *   canonical, and the response uses them as given here.
 * @return {Promise<Object<string,string>>} Map of the given names to the icon variant for
 *   each of them: 'userAvatar', 'userTemporary', 'userBlocked' or 'suggestedInvestigations'.
 *   Every requested name is present in the map. Rejects with the error details of the
 *   request if it fails.
 */
function getUserIconVariants( usernames ) {
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
	for ( let i = 0; i < usernames.length; i += MAX_ICON_USERS ) {
		const chunk = usernames.slice( i, i + MAX_ICON_USERS );
		getUserIconVariants( chunk ).then(
			( icons ) => {
				chunk.forEach( ( username ) => {
					if ( username in icons ) {
						pending.get( username ).resolve( icons[ username ] );
					} else {
						pending.get( username ).reject( 'missing-icon' );
					}
				} );
			},
			( error ) => {
				chunk.forEach( ( username ) => {
					pending.get( username ).reject( error );
				} );
			}
		);
	}
}

module.exports = {
	getUserInfo: getUserInfo,
	getUserIconVariants: getUserIconVariants,
	getUserIconVariant: getUserIconVariant
};
