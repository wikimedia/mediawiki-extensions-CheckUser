( function () {
	const REQUEST_FIELD = 'checkuserclienthints';
	let collectedClientHints = null;
	let collectionPromise = null;

	/**
	 * @param {jQuery} $editForm
	 */
	function addClientHintsToEditForm( $editForm ) {
		if (
			collectedClientHints === null ||
			!$editForm.length ||
			$editForm[ 0 ].elements[ REQUEST_FIELD ]
		) {
			return;
		}
		$editForm.append( $( '<input>' )
			.attr( { type: 'hidden', name: REQUEST_FIELD } )
			.val( collectedClientHints ) );
	}

	/**
	 * Ask the browser for the data
	 *
	 * @param {Function} collect Returns a Promise of the Client Hints data
	 * @return {Promise} Never rejects
	 */
	function startCollection( collect ) {
		if ( collectionPromise ) {
			return collectionPromise;
		}

		collectionPromise = collect()
			.then( ( clientHintData ) => {
				collectedClientHints = JSON.stringify( clientHintData );
			} )
			// This must never reject, because a rejected step in the save options
			// process would abort the save in VisualEditor
			.catch( () => {} );

		return collectionPromise;
	}

	/**
	 * @param {Function} collect Returns a Promise of the Client Hints data
	 */
	function listenForEditIntent( collect ) {
		mw.hook( 've.newTarget' ).add( ( target ) => {
			// Only a target that saves through getSaveOptions() reads saveFields
			if ( typeof target.getSaveOptionsProcess !== 'function' ) {
				return;
			}
			target.getSaveOptionsProcess().next(
				() => startCollection( collect ).then( () => {
					if ( collectedClientHints !== null ) {
						target.saveFields[ REQUEST_FIELD ] = () => collectedClientHints;
					}
				} )
			);
		} );

		// The MobileFrontend source editor can't delay its save, so collect when it renders
		mw.hook( 'mobileFrontend.sourceEditor.preRenderFinished' )
			.add( () => startCollection( collect ) );
		mw.hook( 'mobileFrontend.sourceEditor.saveBegin' ).add( ( payload ) => {
			if ( collectedClientHints !== null ) {
				payload.options[ REQUEST_FIELD ] = collectedClientHints;
			}
		} );

		mw.hook( 'wikipage.editform' ).add( ( $editForm ) => {
			startCollection( collect ).then( () => addClientHintsToEditForm( $editForm ) );
		} );

		// Editors that build their save with mw.Api#prepareExtensibleApiRequest
		const addToExtensibleRequest = ( data ) => {
			data.promise = data.promise.then(
				() => startCollection( collect ).then( () => {
					if ( collectedClientHints !== null ) {
						data.params[ REQUEST_FIELD ] = collectedClientHints;
					}
				} )
			);
		};
		mw.hook( 'discussionToolsExtendSave' ).add( addToExtensibleRequest );
		mw.hook( 'ext.proofreadpage.editinsequence-extend-save' ).add( addToExtensibleRequest );
	}

	/**
	 * Set up the listener for the postEdit hook, if client hints are supported by the browser.
	 *
	 * @param {Navigator|Object} navigatorData
	 * @return {boolean} true if client hints integration has been set up on postEdit hook,
	 *   false otherwise.
	 */
	function init( navigatorData ) {
		const hasHighEntropyValuesMethod = navigatorData.userAgentData &&
			navigatorData.userAgentData.getHighEntropyValues;
		if ( !hasHighEntropyValuesMethod ) {
			// The browser doesn't support navigator.userAgentData.getHighEntropyValues. Used
			// for tests.
			return false;
		}

		const wgCheckUserClientHintsHeadersJsApi = mw.config.get( 'wgCheckUserClientHintsHeadersJsApi' );

		let highEntropyValuesPromise = null;

		/**
		 * POST an object with user-agent client hint data to a CheckUser REST endpoint.
		 *
		 * @param {Object} clientHintData Data structured returned by
		 *  navigator.userAgentData.getHighEntropyValues()
		 * @param {number} identifier The ID associated with the event
		 * @param {string} type The type of event (e.g. 'revision').
		 * @param {boolean} retryOnTokenMismatch Whether to retry the POST if the CSRF token is a
		 *  mismatch. A mismatch can happen if the token has expired.
		 * @return {jQuery.Promise} A promise that resolves after the POST is complete.
		 */
		function postClientHintData(
			clientHintData, identifier, type, retryOnTokenMismatch
		) {
			const restApi = new mw.Rest();
			const api = new mw.Api();
			const deferred = $.Deferred();
			api.getToken( 'csrf' ).then( ( token ) => {
				clientHintData.token = token;
				restApi.post(
					'/checkuser/v0/useragent-clienthints/' + type + '/' + identifier,
					clientHintData
				).then(
					( data ) => {
						deferred.resolve( data );
					}
				).catch( ( err, errObject ) => {
					mw.log.error( errObject );
					let errMessage = errObject.exception;
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
						postClientHintData( clientHintData, identifier, type, false ).then(
							( data ) => {
								deferred.resolve( data );
							},
							( secondRequestErr, secondRequestErrObject ) => {
								deferred.reject( secondRequestErr, secondRequestErrObject );
							}
						);
					} else {
						logError( 'Unable to submit client hints', errMessage );
						deferred.reject( err, errObject );
					}
				} );
			} ).catch( ( err, errObject ) => {
				mw.log.error( errObject );
				let errMessage = errObject.exception;
				if ( errObject.xhr &&
				errObject.xhr.responseJSON &&
				errObject.xhr.responseJSON.messageTranslations ) {
					errMessage = errObject.xhr.responseJSON.messageTranslations.en;
				}
				logError( 'Unable to get token for recording client hints', errMessage );
				deferred.reject( err, errObject );
			} );
			return deferred.promise();
		}

		/**
		 * Collect and POST Client Hints data for a given event.
		 *
		 * @param {number} identifier The ID associated with the event
		 * @param {string} type The type of event (e.g. 'revision').
		 * @return {Promise<Object>}
		 */
		function collectAndSendClientHintsData( identifier, type ) {
			return collectClientHintsData().then( ( userAgentHighEntropyValues ) => {
				postClientHintData( userAgentHighEntropyValues, identifier, type, true );
			} );
		}

		/**
		 * Collect high entropy client hints data.
		 *
		 * @return {Promise<Object>}
		 */
		function collectClientHintsData() {
			if ( highEntropyValuesPromise ) {
				return highEntropyValuesPromise;
			}
			try {
				highEntropyValuesPromise = navigatorData.userAgentData.getHighEntropyValues(
					wgCheckUserClientHintsHeadersJsApi
				);
				return highEntropyValuesPromise;
			} catch ( err ) {
				// Handle NotAllowedError, if the browser throws it.
				mw.log.error( err );
				logError( 'Unable to collect high entropy client hints', err.toString() );
				return Promise.reject( err );
			}
		}

		/**
		 * Logs error to Logstash
		 *
		 * @param {string} message A message, without varying parts (not to spam Logstash)
		 * @param {string} originalMessage Original error message, will be added to the log context
		 */
		function logError( message, originalMessage ) {
			const loggedError = new Error( message );
			/* eslint-disable camelcase */
			loggedError.error_context = { originalMessage };
			/* eslint-enable camelcase */
			mw.errorLogger.logError( loggedError, 'error.checkuser' );
		}

		// Collect and send Client Hints data if the user has just performed a
		// CheckUser private event.
		const privateEventId = mw.config.get( 'wgCheckUserClientHintsPrivateEventId' );
		if ( privateEventId ) {
			collectAndSendClientHintsData( privateEventId, 'privatelog' );
		}

		const inEditRequest = mw.config.get( 'wgCheckUserClientHintsInEditRequest' );
		if ( inEditRequest ) {
			listenForEditIntent( () => collectClientHintsData() );
		}

		/**
		 * Respond to postEdit hook, fired by MediaWiki core, VisualEditor, DiscussionTools,
		 * and other interfaces.
		 *
		 * Used to collect Client Hints data when not collected via the edit request.
		 */
		mw.hook( 'postEdit' ).add( () => {
			// Rollback cannot send the field with its request, so send the data after the save
			if ( inEditRequest && !mw.config.get( 'wgRollbackSuccess' ) ) {
				return;
			}
			collectAndSendClientHintsData( mw.config.get( 'wgCurRevisionId' ), 'revision' );
		} );

		/**
		 * Respond to JS logout flow in core, and add high entropy client hint data to
		 * the request to ApiLogout.
		 */
		mw.hook( 'extendLogout' ).add( ( data ) => {
			// eslint-disable-next-line arrow-body-style
			data.promise = data.promise.then( () => {
				return collectClientHintsData().then( ( userAgentHighEntropyValues ) => {
					data.params.checkuserclienthints = JSON.stringify( userAgentHighEntropyValues );
				} );
			} );
		} );

		return true;
	}

	init( navigator );

	if ( window.QUnit ) {
		module.exports = {
			init: init,
			addClientHintsToEditForm: addClientHintsToEditForm,
			startCollection: startCollection,
			setCollectedClientHints: function ( value ) {
				collectedClientHints = value;
				collectionPromise = null;
			}
		};
	}
}() );
