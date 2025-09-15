'use strict';

const utils = require( 'ext.checkUser.suggestedInvestigations/utils.js' );

QUnit.module( 'ext.checkUser.suggestedInvestigations.utils', QUnit.newMwEnvironment() );

/**
 * Generates an element which matches the structure of the button used to
 * update the status of a case
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason
 * @return {Element}
 */
const generateChangeStatusButton = ( caseId, status, reason ) => {
	const changeStatusButton = document.createElement( 'span' );
	changeStatusButton.setAttribute( 'class', 'mw-checkuser-suggestedinvestigations-change-status-button' );
	changeStatusButton.setAttribute( 'data-case-id', caseId );
	changeStatusButton.setAttribute( 'data-case-status', status );
	changeStatusButton.setAttribute( 'data-case-status-reason', reason );
	return changeStatusButton;
};

QUnit.test.each( 'Test updateCaseStatusOnPage', {
	'status goes from open to resolved': [
		'open', '', 'resolved', 'testingabc'
	],
	'status goes from resolved to open': [
		'resolved', 'testingabc', 'open', 'testingabc'
	],
	'status goes from open to invalid': [
		'open', '', 'invalid', 'testing'
	],
	'no change in status, but change in reason': [
		'resolved', 'testingabc', 'resolved', 'testingabcdef'
	]
}, ( assert, [ initialStatus, initialStatusReason, newStatus, newStatusReason ] ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const changeStatusButton = generateChangeStatusButton(
		123, initialStatus, initialStatusReason
	);
	$qunitFixture.append( changeStatusButton );

	utils.updateCaseStatusOnPage( 123, newStatus, newStatusReason );

	assert.strictEqual(
		changeStatusButton.getAttribute( 'data-case-status' ),
		newStatus,
		'New status in data attribute is correct'
	);
	assert.strictEqual(
		changeStatusButton.getAttribute( 'data-case-status-reason' ),
		newStatusReason,
		'New status reason in data attribute is correct'
	);
} );
