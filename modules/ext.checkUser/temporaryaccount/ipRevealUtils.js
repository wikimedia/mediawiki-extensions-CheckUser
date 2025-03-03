/**
 * Gets the revealed status key for a user.
 *
 * @param {string} user The username of the temporary account.
 * @return {string}
 */
function getRevealedStatusKey( user ) {
	return 'mw-checkuser-temp-' + user;
}

/**
 * Gets the revealed status of a user.
 *
 * @param {string} user The username of the temporary account to check.
 * @return {null|true} The revealed status of the user (null if not revealed, true if revealed).
 */
function getRevealedStatus( user ) {
	return mw.storage.get( getRevealedStatusKey( user ) );
}

/**
 * Update the revealed status of a user to indicate that the user has been revealed.
 *
 * @param {string} user The username of the temporary account that has had its IPs revealed.
 */
function setRevealedStatus( user ) {
	if ( getAutoRevealStatus() ) {
		// Don't set auto-revealed users as pre-revealed, to avoid cluttering localStorage.
		return;
	}
	if ( !getRevealedStatus( getRevealedStatusKey( user ) ) ) {
		mw.storage.set(
			getRevealedStatusKey( user ),
			true,
			mw.config.get( 'wgCheckUserTemporaryAccountMaxAge' )
		);
	}
}

/**
 * Gets the auto-reveal status key.
 *
 * @return {string}
 */
function getAutoRevealStatusKey() {
	return 'mw-checkuser-auto-reveal-temp';
}

/**
 * Gets the auto-reveal status.
 *
 * @return {boolean} The auto-reveal status (false if off, true if on).
 */
function getAutoRevealStatus() {
	return mw.storage.get( getAutoRevealStatusKey() );
}

/**
 * Update the auto-reveal status of a user to switch on, switch off, or extend expiry.
 *
 * @param {string} timestamp When to expire the "on" mode or empty string to turn it "off".
 */
function setAutoRevealStatus( timestamp ) {
	if ( !timestamp ) {
		mw.storage.remove( getAutoRevealStatusKey() );
		return;
	}

	mw.storage.set(
		getAutoRevealStatusKey(),
		timestamp,
		timestamp
	);
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus,
	getAutoRevealStatus: getAutoRevealStatus,
	setAutoRevealStatus: setAutoRevealStatus
};
