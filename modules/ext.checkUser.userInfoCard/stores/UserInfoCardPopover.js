'use strict';

const Pinia = require( 'pinia' );

const useUserInfoCardPopoverStore = Pinia.defineStore( 'checkuser-userinfocard-popover', {
	state: () => ( {
		isOpen: false,
		currentTrigger: null,
		userCard: {
			userId: null
		}
	} ),
	actions: {
		open( target ) {
			this.isOpen = true;
			this.currentTrigger = target;
		},
		setUserCard( { userId } ) {
			this.userCard.userId = userId;
		}
	}
} );

module.exports = useUserInfoCardPopoverStore;
