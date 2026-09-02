const Constants = {
	caseStatuses: [ 'open', 'resolved', 'invalid' ],
	lastUpdatedOptions: [
		{ value: '1', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-today' },
		{ value: '3', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last3days' },
		{ value: '7', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last7days' },
		{ value: '90', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last90days' },
		// The empty string value for 'all time' maps to null (no filter) on the server side.
		{ value: '', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-all-time' }
	],
	editAndBlockFilterOptions: [
		{
			value: 'edits-and-blocks',
			labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-edits-and-blocks-filter',
			useGlobalEditsLabelMsg: 'checkuser-suggestedinvestigations-filter-dialog-global-edits-and-blocks-filter'
		}, {
			value: 'edits-or-blocks',
			labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-edits-or-blocks-filter',
			useGlobalEditsLabelMsg: 'checkuser-suggestedinvestigations-filter-dialog-global-edits-or-blocks-filter'
		}, {
			value: 'edits-only',
			labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-edits-only-filter',
			useGlobalEditsLabelMsg: 'checkuser-suggestedinvestigations-filter-dialog-global-edits-only-filter'
		}, {
			value: 'blocks-only',
			labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-blocks-only-filter'
		}, {
			value: 'none',
			labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-no-edit-block-filter'
		}
	]
};

module.exports = Constants;
