function getCookieKey( user ) {
	return 'mw-checkuser-temp-' + user;
}

function getRevealedStatus( user ) {
	return mw.storage.get( getCookieKey( user ) );
}

function setRevealedStatus( user ) {
	if ( !getRevealedStatus( getCookieKey( user ) ) ) {
		mw.storage.set( getCookieKey( user ), true, mw.config.get( 'wgCheckUserTemporaryAccountMaxAge' ) );
	}
}

module.exports = {
	getRevealedStatus: getRevealedStatus,
	setRevealedStatus: setRevealedStatus
};
