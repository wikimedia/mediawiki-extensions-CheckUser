'use strict';

const Vue = require( 'vue' );
const App = require( './components/App.vue' );
const { isUserInfoCardEnabled } = require( './util.js' );
const UserCardButton = require( './components/UserCardButton.vue' );

const BUTTON_SELECTOR = '.ext-checkuser-userinfocard-button';
const ICON_CLASS_PREFIX = 'ext-checkuser-userinfocard-button__icon--';

// Code outside CheckUser can load this module for any viewer, including one who turned the
// card off. Such a viewer must see nothing of it.
const enabled = isUserInfoCardEnabled();

// Some users might need a custom icon in their UIC button (i.e., other than userAvatar/userTemporary).
// Because status designated by such icon can be temporary, it cannot be recorded in the parser cache,
// and we have to apply it here instead.
const customAccountIcons = mw.config.get( 'wgCheckUserUserInfoCardCustomIcons', {} );

// Buttons which already hold a handler. Buttons can come from code outside CheckUser, which owns
// their markup, so keep the record here instead of in an attribute on the element.
const handledButtons = new WeakSet();

// The single card instance shared by every button on the page. It is only available after the
// document is ready, because it needs document.body to mount into.
let popoverApp = null;

let activeButton = null;
let activeUsername = null;

const togglePopover = ( button, username ) => {
	if ( !popoverApp ) {
		return;
	}
	const isCurrentlyOpen = popoverApp.isPopoverOpen();

	// Check if this is the same button that's currently active and
	// the popover is actually open
	if ( isCurrentlyOpen &&
		activeButton === button &&
		activeUsername === username ) {
		// If it's the same button and the popover is open, close it
		popoverApp.close();
		activeButton = null;
		activeUsername = null;
	} else {
		// If it's a different button, the popover is closed, or no
		// button is active, open the popover
		popoverApp.setUserInfo( username );
		popoverApp.open( button );
		activeButton = button;
		activeUsername = username;
	}
};

/**
 * Replace the icon variant of a button.
 *
 * Does nothing if the button holds no icon element.
 *
 * @param {Element} button
 * @param {string} variant 'userAvatar', 'userBlocked' or 'userTemporary'
 */
function setIconVariant( button, variant ) {
	const iconElem = button.querySelector( '.cdx-button__icon' );
	if ( !iconElem ) {
		return;
	}
	iconElem.classList.remove(
		'ext-checkuser-userinfocard-button__icon--userAvatar',
		'ext-checkuser-userinfocard-button__icon--userBlocked',
		'ext-checkuser-userinfocard-button__icon--userTemporary'
	);
	// The variant comes from the server or from the caller, and can be any of the ones
	// UserInfoCardButtonRenderer itself emits. The following CSS classes are used here:
	// * ext-checkuser-userinfocard-button__icon--userAvatar
	// * ext-checkuser-userinfocard-button__icon--userBlocked
	// * ext-checkuser-userinfocard-button__icon--userTemporary
	iconElem.classList.add( ICON_CLASS_PREFIX + variant );
}

/**
 * Get the icon variant to use for a user, without asking the server.
 *
 * @param {string} username
 * @return {string} 'userAvatar', 'userBlocked' or 'userTemporary'
 */
function defaultIconVariant( username ) {
	if ( customAccountIcons[ username ] ) {
		return customAccountIcons[ username ];
	}
	return mw.util.isTemporaryUser( username ) ? 'userTemporary' : 'userAvatar';
}

/**
 * Create a user info card button.
 *
 * The result is a plain DOM element, with the same appearance as the default UIC buttons.
 * It will respond to events once added to the DOM, there's no need to add listeners separately.
 *
 * This function does not perform API queries to determine the proper icon for the target user.
 *
 * @param {string} username Canonical name of the target user
 * @param {Object} [options] Optional configuration for the button
 * @param {string} [options.icon] Icon variant: 'userAvatar', 'userBlocked' or 'userTemporary'.
 *   Defaults to the variant that the server sent for this user, or else to the one that the
 *   name itself implies.
 * @return {HTMLButtonElement|null} Null if the viewer turned the card off
 * @stable for use in gadgets and user scripts
 * @since 1.47
 */
function createButton( username, options = {} ) {
	if ( !enabled ) {
		return null;
	}

	const icon = document.createElement( 'span' );
	icon.className = 'cdx-button__icon ext-checkuser-userinfocard-button__icon';

	const button = document.createElement( 'button' );
	button.type = 'button';
	button.classList.add(
		'ext-checkuser-userinfocard-button',
		'cdx-button',
		'cdx-button--action-default',
		'cdx-button--weight-quiet',
		'cdx-button--icon-only'
	);
	button.setAttribute( 'aria-haspopover', 'dialog' );
	button.setAttribute(
		'aria-label',
		mw.msg( 'checkuser-userinfocard-toggle-button-aria-label', username )
	);
	button.setAttribute( 'data-username', username );
	button.appendChild( icon );

	attachInfoCardButtonHandler( button );
	setIconVariant( button, options.icon || defaultIconVariant( username ) );

	return button;
}

/**
 * Attaches an event listener to a single button. The function is safe to be called many times
 * on the same element.
 *
 * @param {HTMLElement} button The element, which should open the UIC when clicked
 * @param {string|null} username The user for whom the UIC should show data. If not specified,
 *     value of the `data-username` attribute on button will be used.
 * @stable for use in gadgets and user scripts
 * @since 1.47
 */
function attachInfoCardButtonHandler( button, username = null ) {
	if ( !enabled || handledButtons.has( button ) ) {
		return;
	}
	handledButtons.add( button );

	// FIXME: The popover will lose its position when "Live update" mode
	// is enabled. See T397609 for follow-up work.
	$( button ).on( 'click keydown', function ( event ) {
		// For keyboard events, only respond to Enter key
		if ( event.type === 'keydown' &&
			event.key !== 'Enter' &&
			event.keyCode !== 13 ) {
			return;
		}
		event.preventDefault();

		const target = username || this.getAttribute( 'data-username' );
		if ( target ) {
			togglePopover( this, target );
		}
	} );
}

/**
 * Check whether the viewer wants the user info card.
 *
 * Everything else in this module does nothing when this returns false.
 *
 * @return {boolean}
 * @stable for use in gadgets and user scripts
 * @since 1.47
 */
function isEnabled() {
	return enabled;
}

if ( enabled ) {
	const prepareInfoCardButtons = ( $content ) => {
		// FIXME: The popover will lose its position when "Live update" mode
		// is enabled. See T397609 for follow-up work.
		// Set up event listeners for the user info card buttons
		$content.find( BUTTON_SELECTOR ).each( function () {
			attachInfoCardButtonHandler( this );

			// Buttons emitted into page content by {{#uic:}} are part of the parser output that
			// every viewer shares, and they are marked hidden so that consumers of that HTML
			// which load no CheckUser CSS don't show a button that cannot work.
			// Now, given that we know UIC is wanted, we can remove the hidden attribute.
			// It should have no impact (the button is shown with CSS), but let's do it just in case.
			this.removeAttribute( 'hidden' );

			const customIcon = customAccountIcons[ this.getAttribute( 'data-username' ) ];
			if ( customIcon ) {
				setIconVariant( this, customIcon );
			}
		} );
	};

	// T403700 - the trigger in the page navigation of user pages and user talk pages is rendered
	// by the skin, which keeps no data attribute with the username, so use the relevant user of
	// the page. UserInfoCardNavigationHandler and wgRelevantUserName use the same source for it.
	const attachNavigationHandlers = () => {
		const relevantUserName = mw.config.get( 'wgRelevantUserName' );
		if ( !relevantUserName ) {
			return;
		}

		// Vector 2022 puts a copy of the item into the page tools dropdown for narrow screens,
		// so there can be more than one trigger.
		$( '.ext-checkuser-userinfocard-navigation-item' ).each( function () {
			// Some skins put the class of the item on the list element, others on the link.
			const trigger = this.matches( 'a' ) ? this : this.querySelector( 'a' );
			if ( !trigger ) {
				return;
			}

			attachInfoCardButtonHandler( trigger, relevantUserName );
		} );
	};

	$( () => {
		document.body.classList.add( 'ext-checkuser-userinfocard-enabled' );

		mw.hook( 'wikipage.content' ).add( prepareInfoCardButtons );

		// T402196 - user link on permalink pages is outside #mw-content-text,
		// so it's not covered by the hook above
		prepareInfoCardButtons( $( '#contentSub' ) );
		attachNavigationHandlers();

		// Create and append the popover container to the DOM
		const popover = document.createElement( 'div' );
		popover.id = 'ext-checkuser-userinfocard-popover';
		popover.classList.add( 'ext-checkuser-userinfocard-popover' );
		document.body.appendChild( popover );

		popoverApp = Vue.createMwApp( App ).mount( popover );
	} );
}

UserCardButton.methods.togglePopover = togglePopover;
module.exports = { UserCardButton, createButton, attachInfoCardButtonHandler, isEnabled };
