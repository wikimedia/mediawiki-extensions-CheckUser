( function () {
	if ( !navigator.userAgentData ) {
		// The browser doesn't support navigator.userAgentData
		return;
	}

	var wgCheckUserClientHintsHeadersJsApi =
		mw.config.get( 'wgCheckUserClientHintsHeadersJsApi' );

	/**
	 * POST an object with user-agent client hint data to a CheckUser REST endpoint.
	 *
	 * @param {Object} clientHintData Data structured returned by
	 *  navigator.userAgentData.getHighEntropyValues()
	 * @return {jQuery.Promise} A promise that resolves after the POST is complete.
	 */
	function postClientHintData( clientHintData ) {
		return new mw.Rest().post(
			'/checkuser/v0/useragent-clienthints/revision/' + mw.config.get( 'wgCurRevisionId' ),
			clientHintData
		).fail( function ( err, errObject ) {
			mw.log.error( errObject );
			if ( errObject.xhr &&
				errObject.xhr.responseJSON &&
				errObject.xhr.responseJSON.messageTranslations ) {
				mw.errorLogger.logError( new Error(
					errObject.xhr.responseJSON.messageTranslations[
						mw.config.get( 'wgContentLanguage' ) ]
				), 'error.checkuser' );
			}
		} );
	}

	/**
	 * Respond to postEdit hook, fired by MediaWiki core, VisualEditor and DiscussionTools.
	 *
	 * Note that CheckUser only adds this code to article page views if
	 * CheckUserClientHintsEnabled is set to true.
	 */
	mw.hook( 'postEdit' ).add( function () {
		try {
			// eslint-disable-next-line compat/compat
			navigator.userAgentData
				.getHighEntropyValues(
					wgCheckUserClientHintsHeadersJsApi
				).then( function ( userAgentHighEntropyValues ) {
					return postClientHintData( userAgentHighEntropyValues );
				} );
		} catch ( err ) {
			// Handle NotAllowedError, if the browser throws it.
			mw.log.error( err );
			mw.errorLogger.logError( new Error( err ), 'error.checkuser' );
		}
	} );
}() );
