'use strict';

const Pinia = require( 'pinia' );
const moment = require( 'moment' );

const useUserInfoCardPopoverStore = Pinia.defineStore( 'checkuser-userinfocard-popover', {
	state: () => ( {
		isOpen: false,
		currentTrigger: null,
		loading: false,
		userCard: {
			userId: null,
			wikiId: null,
			userPageUrl: null,
			userPageExists: null,
			username: null,
			joinedDate: null,
			joinedRelativeTime: null,
			globalEditCount: null,
			thanksReceivedCount: null,
			thanksGivenCount: null
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
		setUserCard( { userId } ) {
			this.userCard.userId = userId;
		},
		fetchUserInfo( userId, wikiId ) {
			this.loading = true;
			const token = mw.user.tokens.get( 'csrfToken' );
			const rest = new mw.Rest();
			rest.post( `/checkuser/v0/userinfo/${ userId }`, { token } )
				.then( ( userInfo ) => {
					if ( !userInfo ) {
						throw new Error( 'Invalid user info' );
					}

					const {
						name,
						firstRegistration,
						globalEditCount,
						thanksReceived,
						thanksGiven,
						userPageExists
					} = userInfo;
					const userTitleObj = mw.Title.makeTitle( 2, name );
					const userPageUrl = userTitleObj.getUrl();
					this.userCard = {
						userId,
						wikiId,
						userPageUrl,
						userPageExists,
						username: name,
						joinedDate: firstRegistration ?
							moment( firstRegistration, 'YYYYMMDDHHmmss' ).format( 'DD MMM YYYY' ) :
							null,
						joinedRelativeTime: firstRegistration ?
							moment( firstRegistration, 'YYYYMMDDHHmmss' ).fromNow() :
							null,
						globalEditCount,
						thanksReceivedCount: thanksReceived,
						thanksGivenCount: thanksGiven
					};
					this.loading = false;
				} )
				.catch( () => {
					// TODO: T393014 handle errors
					this.loading = false;
				} );
		}
	}
} );

module.exports = useUserInfoCardPopoverStore;
