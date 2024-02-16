( function () {
	/**
	 * Set up the listener for the postEdit hook, if client hints are supported by the browser.
	 *
	 * @param {Navigator|Object} navigatorData
	 * @return {boolean} true if client hints integration has been set up on postEdit hook,
	 *   false otherwise.
	 */
	function init( navigatorData ) {
		var hasHighEntropyValuesMethod = navigatorData.userAgentData &&
			navigatorData.userAgentData.getHighEntropyValues;
		if ( !hasHighEntropyValuesMethod ) {
			// The browser doesn't support navigator.userAgentData.getHighEntropyValues. Used
			// for tests.
			return false;
		}

		var wgCheckUserClientHintsHeadersJsApi = mw.config.get( 'wgCheckUserClientHintsHeadersJsApi' );

		/**
		 * POST an object with user-agent client hint data to a CheckUser REST endpoint.
		 *
		 * @param {Object} clientHintData Data structured returned by
		 *  navigator.userAgentData.getHighEntropyValues()
		 * @param {boolean} retryOnTokenMismatch Whether to retry the POST if the CSRF token is a
		 *  mismatch. A mismatch can happen if the token has expired.
		 * @return {jQuery.Promise} A promise that resolves after the POST is complete.
		 */
		function postClientHintData( clientHintData, retryOnTokenMismatch ) {
			var restApi = new mw.Rest();
			var api = new mw.Api();
			var deferred = $.Deferred();
			api.getToken( 'csrf' ).then( function ( token ) {
				clientHintData.token = token;
				restApi.post(
					'/checkuser/v0/useragent-clienthints/revision/' + mw.config.get( 'wgCurRevisionId' ),
					clientHintData
				).then(
					function ( data ) {
						deferred.resolve( data );
					}
				).fail( function ( err, errObject ) {
					mw.log.error( errObject );
					var errMessage = errObject.exception;
					if (
						errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.messageTranslations
					) {
						errMessage = errObject.xhr.responseJSON.messageTranslations.en;
					}
					if (
						retryOnTokenMismatch &&
						errObject.xhr &&
						errObject.xhr.responseJSON &&
						errObject.xhr.responseJSON.errorKey &&
						errObject.xhr.responseJSON.errorKey === 'rest-badtoken'
					) {
						// The CSRF token has expired. Retry the POST with a new token.
						api.badToken( 'csrf' );
						postClientHintData( clientHintData, false ).then(
							function ( data ) {
								deferred.resolve( data );
							},
							function ( secondRequestErr, secondRequestErrObject ) {
								deferred.reject( secondRequestErr, secondRequestErrObject );
							}
						);
					} else {
						mw.errorLogger.logError( new Error( errMessage ), 'error.checkuser' );
						deferred.reject( err, errObject );
					}
				} );
			} ).fail( function ( err, errObject ) {
				mw.log.error( errObject );
				var errMessage = errObject.exception;
				if ( errObject.xhr &&
				errObject.xhr.responseJSON &&
				errObject.xhr.responseJSON.messageTranslations ) {
					errMessage = errObject.xhr.responseJSON.messageTranslations.en;
				}
				mw.errorLogger.logError( new Error( errMessage ), 'error.checkuser' );
				deferred.reject( err, errObject );
			} );
			return deferred.promise();
		}

		/**
		 * Respond to postEdit hook, fired by MediaWiki core, VisualEditor and DiscussionTools.
		 *
		 * Note that CheckUser only adds this code to article page views if
		 * CheckUserClientHintsEnabled is set to true.
		 */
		mw.hook( 'postEdit' ).add( function () {
			try {
				navigatorData.userAgentData.getHighEntropyValues(
					wgCheckUserClientHintsHeadersJsApi
				).then( function ( userAgentHighEntropyValues ) {
					return postClientHintData( userAgentHighEntropyValues, true );
				} );
			} catch ( err ) {
				// Handle NotAllowedError, if the browser throws it.
				mw.log.error( err );
				mw.errorLogger.logError( new Error( err ), 'error.checkuser' );
			}
		} );
		return true;
	}

	init( navigator );

	module.exports = {
		init: init
	};
}() );
