'use strict';

/**
 * Waits until the specified selector to no longer match any elements in the QUnit test fixture.
 *
 * @param {string} selector The JQuery selector to check
 * @return {Promise}
 */
function waitUntilElementDisappears( selector ) {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	return new Promise( ( resolve ) => {
		// Check every 10ms if the class matches any element in the QUnit test fixture.
		// If the class is no longer present, then resolve is called.
		// If this condition is not met ever, then QUnit will time the test out after 6s.
		function runCheck() {
			setTimeout( () => {
				if ( !$( selector, $qunitFixture ).length ) {
					return resolve();
				}
				runCheck();
			}, 10 );
		}
		runCheck();
	} );
}

module.exports = waitUntilElementDisappears;
