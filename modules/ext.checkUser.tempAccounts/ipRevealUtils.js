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
 * @return {string|null|false} Timestamp when auto-reveal mode expires, if it is on. Otherwise
 *  null if expired or not set, or false if storage is not avaialble.
 */
function getAutoRevealStatus() {
	return mw.storage.get( getAutoRevealStatusKey() );
}

/**
 * Update the auto-reveal status of a user to switch on, switch off, or extend expiry.
 *
 * The value stored and the expiry are the same, except that the value is a string and
 * the expiry is a number.
 *
 * @param {string} relativeTimestamp Number of seconds the "on" mode should be enabled
 *  or empty string to turn it "off".
 */
function setAutoRevealStatus( relativeTimestamp ) {
	if ( !relativeTimestamp ) {
		mw.storage.remove( getAutoRevealStatusKey() );
		return;
	}

	const relativeExpiry = Number( relativeTimestamp );
	const absoluteExpiry = String( Math.floor( Date.now() / 1000 ) + relativeExpiry );

	mw.storage.set(
		getAutoRevealStatusKey(),
		absoluteExpiry,
		relativeExpiry
	);
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus,
	getAutoRevealStatus: getAutoRevealStatus,
	setAutoRevealStatus: setAutoRevealStatus
};
