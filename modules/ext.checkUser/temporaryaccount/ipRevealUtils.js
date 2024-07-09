/**
 * Gets the cookie key for a user.
 *
 * @param {string} user The username of the temporary account.
 * @returns {string}
 */
function getCookieKey( user ) {
	return 'mw-checkuser-temp-' + user;
}

/**
 * Gets the revealed status of a user.
 *
 * @param {string} user The username of the temporary account to check.
 * @returns {null|true} The revealed status of the user (null if not revealed, true if revealed).
 */
function getRevealedStatus( user ) {
	return mw.storage.get( getCookieKey( user ) );
}

/**
 * Update the revealed status of a user to indicate that the user has been revealed.
 *
 * @param {string} user The username of the temporary account that has had its IPs revealed.
 */
function setRevealedStatus( user ) {
	if ( !getRevealedStatus( getCookieKey( user ) ) ) {
		mw.storage.set( getCookieKey( user ), true, mw.config.get( 'wgCheckUserTemporaryAccountMaxAge' ) );
	}
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus
};
