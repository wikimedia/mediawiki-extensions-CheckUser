'use strict';

/**
 * This module lets other JavaScript modules (including gadgets and user scripts) put
 * a UserInfo card (UIC) button on user links that they make themselves.
 *
 * Normally, this module is added only to pages that contain UIC buttons in the server
 * output. Therefore, treat its absence as normal, and explicitly require the module
 * before using it. A viewer who disabled UIC in their preferences will not get the buttons,
 * even with the module loaded.
 *
 * @example
 * mw.loader.using( 'ext.checkUser.userInfoCard' ).then( ( require ) => {
 *     const uic = require( 'ext.checkUser.userInfoCard' );
 *     if ( !uic.isEnabled() ) {
 *         return;
 *     }
 *     // If needed, a call to API will be made to determine the button's icon
 *     myUserLinkWrapper.append( uic.createButton( 'Example user' ) );
 * } );
 *
 * @example
 * // Some icons depend on the status of the account, which only the server knows.
 * // If you have existing buttons that require setting their icon, use the code below.
 * // loadIconVariants can also be used for preloading data (which is not needed, unless
 * // the buttons are created over multiple JavaScript ticks).
 * mw.loader.using( 'ext.checkUser.userInfoCard' ).then( async ( require ) => {
 *     const uic = require( 'ext.checkUser.userInfoCard' );
 *     if ( !uic.isEnabled() ) {
 *         return;
 *     }
 *
 *     const usernames = [ ... ];
 *     const buttons = {}; // e.g. from document.querySelectorAll( ... )
 *
 *     const icons = await uic.loadIconVariants( usernames );
 *     for ( const [ user, icon ] of Object.entries( icons ) ) {
 *         uic.setIconVariant( buttons[user], icon );
 *     }
 * } );
 *
 * @module ext.checkUser.userInfoCard
 */

const Vue = require( 'vue' );
const App = require( './components/App.vue' );
const {
	getCustomIconVariant,
	getDefaultIconVariant,
	isUserInfoCardEnabled,
	setCustomIconVariant
} = require( './util.js' );
const { getUserIconVariants, getUserIconVariant } = require( './rest.js' );
const UserCardButton = require( './components/UserCardButton.vue' );

const BUTTON_SELECTOR = '.ext-checkuser-userinfocard-button';
const ICON_CLASS_PREFIX = 'ext-checkuser-userinfocard-button__icon--';

// Code outside CheckUser can load this module for any viewer, including one who turned the
// card off. Such a viewer must see nothing of it.
const enabled = isUserInfoCardEnabled();

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
 * @param {string} variant 'userAvatar', 'userBlocked', 'userTemporary'
 *   or 'suggestedInvestigations'
 * @memberof module:ext.checkUser.userInfoCard
 * @stable for use in gadgets and user scripts
 * @since 1.47
 */
function setIconVariant( button, variant ) {
	const iconElem = button.querySelector( '.cdx-button__icon' );
	if ( !iconElem ) {
		return;
	}
	iconElem.classList.remove(
		'ext-checkuser-userinfocard-button__icon--userAvatar',
		'ext-checkuser-userinfocard-button__icon--userBlocked',
		'ext-checkuser-userinfocard-button__icon--userTemporary',
		'ext-checkuser-userinfocard-button__icon--suggestedInvestigations'
	);
	// The variant comes from the server or from the caller, and can be any of the ones
	// UserInfoCardButtonRenderer itself emits. The following CSS classes are used here:
	// * ext-checkuser-userinfocard-button__icon--userAvatar
	// * ext-checkuser-userinfocard-button__icon--userBlocked
	// * ext-checkuser-userinfocard-button__icon--userTemporary
	// * ext-checkuser-userinfocard-button__icon--suggestedInvestigations
	iconElem.classList.add( ICON_CLASS_PREFIX + variant );
}

/**
 * Create a user info card button.
 *
 * The result is a plain DOM element, with the same appearance as the default UIC buttons.
 * It will respond to events once added to the DOM, there's no need to add listeners separately.
 *
 * If the icon for the target user is not yet loaded (and not passed as an option), the function
 * will trigger an API request to determine the icon. Calls in the same tick are grouped into
 * a single API request for performance reasons.
 *
 * @param {string} username Canonical name of the target user
 * @param {Object} [options] Optional configuration for the button
 * @param {string} [options.icon] Icon variant: 'userAvatar', 'userBlocked', 'userTemporary'
 *   or 'suggestedInvestigations'. Defaults to the variant that the server sent for this user,
 *   or else to the one that the name itself implies.
 * @return {HTMLButtonElement|null} The button or null if the viewer turned the card off
 * @memberof module:ext.checkUser.userInfoCard
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

	const iconVariant = options.icon ||
		getCustomIconVariant( username );

	attachInfoCardButtonHandler( button );

	if ( iconVariant ) {
		setIconVariant( button, iconVariant );
	} else {
		// Set the default variant, it'll be the one to keep displaying if anything goes wrong
		setIconVariant( button, getDefaultIconVariant( username ) );
		getUserIconVariant( username ).then( ( receivedIcon ) => {
			setIconVariant( button, receivedIcon );
			setCustomIconVariant( username, receivedIcon );
		} ).catch( () => {} );
	}

	return button;
}

/**
 * Get the icon variant which the button of each of the given users needs.
 *
 * Except for the temporary and "standard" user icons, all other icon variants on the UIC button
 * depend on a state known at the server side. In order to reflect it in the button, this function
 * loads all the necessary information from the API.
 *
 * If this function is invoked with a user, for whom an icon is already known, it's loaded without
 * asking the server. All icons received from the server are also stored in the
 * `wgCheckUserUserInfoCardCustomIcons` config, so that they are applied to any button created
 * in future with the `createButton` function.
 *
 * @param {string[]} usernames Names of the target users.
 * @return {Promise<Object>} Map of the given names to the icon variant of each of them, such as
 *   'userAvatar', 'userBlocked', 'userTemporary' or 'suggestedInvestigations'. Every username
 *   is present in the map, except for a viewer who turned the card off, who gets an empty map.
 * @memberof module:ext.checkUser.userInfoCard
 * @stable for use in gadgets and user scripts
 * @since 1.47
 */
async function loadIconVariants( usernames ) {
	if ( !enabled ) {
		return {};
	}

	const variants = {};
	const unknownUsernames = new Set();
	usernames.forEach( ( username ) => {
		const knownVariant = getCustomIconVariant( username );
		if ( knownVariant ) {
			variants[ username ] = knownVariant;
		} else {
			unknownUsernames.add( username );
		}
	} );

	if ( unknownUsernames.size > 0 ) {
		const downloadedIcons = await getUserIconVariants( [ ...unknownUsernames ] );
		for ( const [ username, icon ] of Object.entries( downloadedIcons ) ) {
			setCustomIconVariant( username, icon );
			variants[ username ] = icon;
		}
	}

	return variants;
}

/**
 * Attaches an event listener to a single button. The function is safe to be called many times
 * on the same element.
 *
 * A typical use case for calling this function is when caller operates on HTML source,
 * for example when handling the Parse API response.
 *
 * @param {HTMLElement} button The element, which should open the UIC when clicked
 * @param {string|null} username The user for whom the UIC should show data. If not specified,
 *     value of the `data-username` attribute on button will be used.
 * @memberof module:ext.checkUser.userInfoCard
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
 * @memberof module:ext.checkUser.userInfoCard
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

			// Buttons emitted by {{#uic:}} are part of the parser output that every viewer
			// shares, and they are marked hidden, so that viewers who did not enable the card
			// don't get a button which cannot work. Given that we know the card is wanted,
			// the attribute can go.
			this.removeAttribute( 'hidden' );

			const username = this.getAttribute( 'data-username' );
			const customIcon = getCustomIconVariant( username );
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

		// The hook above only covers the content area, but buttons can be outside of it: in an
		// interface message which holds {{#uic:}} (T436153), or in the user link on permalink
		// pages (T402196).
		prepareInfoCardButtons( $( document.body ) );
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
module.exports = {
	UserCardButton,
	createButton,
	loadIconVariants,
	setIconVariant,
	attachInfoCardButtonHandler,
	isEnabled
};
