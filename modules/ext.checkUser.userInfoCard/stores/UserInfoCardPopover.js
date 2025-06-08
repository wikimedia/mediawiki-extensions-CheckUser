'use strict';

const Pinia = require( 'pinia' );
const moment = require( 'moment' );
const { processEditCountByDay } = require( '../util.js' );

const useUserInfoCardPopoverStore = Pinia.defineStore( 'checkuser-userinfocard-popover', {
	state: () => ( {
		isOpen: false,
		currentTrigger: null,
		loading: false,
		error: null,
		userCard: {
			userId: null,
			wikiId: null,
			userPageUrl: null,
			userPageExists: false,
			username: null,
			joinedDate: null,
			joinedRelativeTime: null,
			globalEditCount: null,
			thanksReceivedCount: null,
			thanksGivenCount: null,
			// Array of objects with `date` and `count` properties
			recentLocalEdits: [],
			totalLocalEdits: null
		}
	} ),
	actions: {
		open( target ) {
			this.currentTrigger = target;
			this.isOpen = true;
		},
		close() {
			this.isOpen = false;
			this.currentTrigger = null;
		},
		fetchUserInfo( userId, wikiId ) {
			this.loading = true;
			this.error = null;
			const token = mw.user.tokens.get( 'csrfToken' );
			const rest = new mw.Rest();
			rest.post( `/checkuser/v0/userinfo/${ userId }`, { token } )
				.then( ( userInfo ) => {
					if ( !userInfo ) {
						throw new Error( mw.msg( 'checkuser-userinfocard-error-no-data' ) );
					}

					const {
						name,
						firstRegistration,
						globalEditCount,
						thanksReceived,
						thanksGiven,
						userPageExists,
						editCountByDay
					} = userInfo;
					const userTitleObj = mw.Title.makeTitle( 2, name );
					const userPageUrl = userTitleObj.getUrl();
					const { processedData, totalEdits } = processEditCountByDay( editCountByDay );
					this.userCard = {
						userId,
						wikiId,
						userPageUrl,
						userPageExists: !!userPageExists,
						username: name,
						joinedDate: firstRegistration ?
							moment( firstRegistration, 'YYYYMMDDHHmmss' ).format( 'DD MMM YYYY' ) :
							null,
						joinedRelativeTime: firstRegistration ?
							moment( firstRegistration, 'YYYYMMDDHHmmss' ).fromNow() :
							null,
						globalEditCount,
						thanksReceivedCount: thanksReceived,
						thanksGivenCount: thanksGiven,
						recentLocalEdits: processedData,
						totalLocalEdits: totalEdits
					};
					this.loading = false;
				} )
				.catch( ( err, errOptions ) => {
					// Retrieving the error message from mw.Rest().post()
					const { xhr } = errOptions || {};
					const responseJSON = ( xhr && xhr.responseJSON ) || {};
					const userLang = mw.config.get( 'wgUserLanguage' );
					if (
						responseJSON.messageTranslations &&
						responseJSON.messageTranslations[ userLang ]
					) {
						this.error = responseJSON.messageTranslations[ userLang ];
					} else if ( err.message ) {
						this.error = err.message;
					} else {
						this.error = mw.msg( 'checkuser-userinfocard-error-generic' );
					}
					this.loading = false;
				} );
		}
	}
} );

module.exports = useUserInfoCardPopoverStore;
