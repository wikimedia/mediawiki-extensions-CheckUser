/* jshint browser: true */
/* jshint -W097 */

'use strict';

let tooltipIdSeed = 1;

/**
 * @param {string} userName
 * @param {{
 *          ip: {},
 *          ua: {},
 *          sorted: {ip: string[], ua: string[]},
 *          linkUserPage: boolean,
 *          classes: string
 *        }} userData
 * @return {HTMLElement}
 */
function buildUserElement( userName, userData ) {
	let userElement;

	// Only link the username to the user page if it was linked in the results.
	// No link can be used if the username is hidden.
	if ( !userData.linkUserPage ) {
		userElement = document.createElement( 'span' );
		userElement.innerHTML = userName;

		return userElement;
	}

	userElement = document.createElement( 'a' );
	userElement.setAttribute(
		'href',
		mw.util.getUrl( 'Special:Contributions/' + userName )
	);

	if ( userData.classes ) {
		userElement.setAttribute( 'class', userData.classes );

		const classes = userData.classes.split( ' ' );
		if ( classes.indexOf( 'mw-tempuserlink-expired' ) !== -1 ) {
			const tooltip = getTooltip();
			userElement.innerHTML = userName;

			userElement.setAttribute(
				'aria-describedby',
				tooltip.tooltipId
			);

			const wrapper = document.createElement( 'span' );
			wrapper.appendChild( userElement.cloneNode( true ) );
			wrapper.appendChild( tooltip.element );

			userElement = wrapper;
		} else {
			userElement.innerHTML = userName;
		}
	} else {
		userElement.innerHTML = userName;
	}

	return userElement;
}

/**
 * @return {{tooltipId: string, element: HTMLDivElement}}
 */
function getTooltip() {

	const tooltip = document.createElement( 'div' );
	const tooltipId = 'mw-tempuserlink-expired-tooltip-' + tooltipIdSeed++;

	tooltip.setAttribute( 'id', tooltipId );
	tooltip.setAttribute( 'role', 'tooltip' );
	tooltip.setAttribute( 'class',
		'cdx-tooltip mw-tempuserlink-expired--tooltip'
	);

	tooltip.innerHTML = mw.message( 'tempuser-expired-link-tooltip' );

	return {
		tooltipId: tooltipId,
		element: tooltip
	};
}

module.exports = buildUserElement;
