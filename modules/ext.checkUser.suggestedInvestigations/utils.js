/**
 * Updates the status of a case in the DOM after a successful use of
 * the dialog defined in components/ChangeInvestigationStatusDialog.vue
 * to change the status on the backend.
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason
 */
function updateCaseStatusOnPage( caseId, status, reason ) {
	// Set the updated data in the data-* properties of the edit button so that opening
	// the dialog in the future uses the new data
	const caseIdDataSelector = '[data-case-id="' + caseId + '"]';
	const changeStatusButton = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-change-status-button' + caseIdDataSelector
	);
	changeStatusButton.setAttribute( 'data-case-status', status );
	changeStatusButton.setAttribute( 'data-case-status-reason', reason );

	// TODO: Update the table pager row to reflect the changes made here in T404216
}

module.exports = {
	updateCaseStatusOnPage: updateCaseStatusOnPage
};
