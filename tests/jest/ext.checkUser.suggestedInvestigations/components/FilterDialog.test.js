'use strict';

const utils = require( '@vue/test-utils' ),
	{ nextTick } = require( 'vue' );

const mockUpdateFiltersOnPage = jest.fn();
const mockCaseStatusToChipStatus = jest.fn();
jest.mock(
	'../../../../modules/ext.checkUser.suggestedInvestigations/utils.js',
	() => ( {
		updateFiltersOnPage: mockUpdateFiltersOnPage,
		caseStatusToChipStatus: mockCaseStatusToChipStatus
	} )
);

const FilterDialog = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/FilterDialog.vue' );

const renderComponent = ( initialFilters ) => utils.mount( FilterDialog, {
	props: { initialFilters: Object.assign( {}, { status: [], username: [] }, initialFilters ) }
} );

/**
 * Perform tests common to all tests of the suggested investigations filter dialog
 * and then return the dialog component
 *
 * @param {Object} [props] Passed through to {@link renderComponent}
 * @param {string[]} [props.username] Username filter
 * @param {string[]} [props.status] Status filter
 * @return {{ wrapper, dialog }} The dialog component and wrapper
 */
const commonComponentTest = async ( props = {} ) => {
	// Render the component and wait for CdxDialog to run some code
	const wrapper = renderComponent( props );
	await nextTick();

	expect( wrapper.exists() ).toEqual( true );

	// Check the dialog element exists.
	const dialog = wrapper.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog'
	);
	expect( dialog.exists() ).toEqual( true );

	// Expect that the username multi-select component exists (separate tests
	// will check other parts of the component)
	const statusCheckboxesField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-username-filter'
	);
	expect( statusCheckboxesField.exists() ).toEqual( true );
	expect( statusCheckboxesField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-username-filter-header)'
	);
	expect( statusCheckboxesField.html() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-username-filter-placeholder)'
	);

	// Check that the dialog footer exists and that it has the expected buttons
	const footer = dialog.find(
		'.cdx-dialog__footer'
	);
	expect( footer.exists() ).toEqual( true );

	const closeButton = footer.find(
		'.cdx-dialog__footer__default-action'
	);
	expect( closeButton.exists() ).toEqual( true );
	expect( closeButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-filter-dialog-close-button)'
	);

	const showResultsButton = footer.find(
		'.cdx-dialog__footer__primary-action'
	);
	expect( showResultsButton.exists() ).toEqual( true );
	expect( showResultsButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-filter-dialog-show-results-button)'
	);

	return { dialog, wrapper };
};

/**
 * Checks the status filter checkboxes exist and have the expected checked state
 *
 * @param {*} dialog The dialog component
 * @param {{open: boolean, resolved: boolean, invalid: boolean}} expectedCheckedState
 */
const commonStatusFilterCheckboxTest = async ( dialog, expectedCheckedState ) => {
	// Expect that the status checkboxes exist
	const statusCheckboxesField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-status-filter'
	);
	expect( statusCheckboxesField.exists() ).toEqual( true );
	expect( statusCheckboxesField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-status-filter-header)'
	);

	for ( const status of [ 'open', 'resolved', 'invalid' ] ) {
		expect( statusCheckboxesField.text() ).toContain(
			'(checkuser-suggestedinvestigations-status-' + status + ')'
		);

		const checkboxField = statusCheckboxesField.find( 'input[name=filter-status-' + status + ']' );
		expect( checkboxField.exists() ).toEqual( true );
		expect( checkboxField.element.checked ).toEqual( expectedCheckedState[ status ] );
	}
};

describe( 'Suggested Investigations change status dialog', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for when opened with no filters set', async () => {
		const { dialog } = await commonComponentTest();

		await commonStatusFilterCheckboxTest(
			dialog, { open: false, resolved: false, invalid: false }
		);
	} );

	it( 'Renders correctly for when opened with status filter set to open', async () => {
		const { dialog } = await commonComponentTest(
			{ status: [ 'open' ] }
		);

		await commonStatusFilterCheckboxTest(
			dialog, { open: true, resolved: false, invalid: false }
		);
	} );

	it( 'Renders correctly for when opened with status filter set to resolved and invalid', async () => {
		const { dialog } = await commonComponentTest(
			{ status: [ 'resolved', 'invalid' ] }
		);

		await commonStatusFilterCheckboxTest(
			dialog, { open: false, resolved: true, invalid: true }
		);
	} );

	it( 'Closes dialog if "Close" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest();

		// Press the close button
		const closeButton = dialog.find(
			'.cdx-dialog__footer__default-action'
		);
		await closeButton.trigger( 'click' );

		// Expect the dialog has been closed
		expect( wrapper.vm.open ).toEqual( false );
	} );

	it( 'Redirects to filtered view when "Show results" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ status: [ 'resolved' ], username: [ 'TestUser1' ] }
		);

		// Check the "open" status checkbox and wait for the change to be propagated
		const openStatusCheckbox = dialog.find(
			'input[name=filter-status-open]'
		);
		await openStatusCheckbox.setChecked();
		await nextTick();

		// Press the "Show results" button
		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		// Expect that a call to redirect to another page has been made without closing the dialog
		// (the dialog is left open so that it's kept open until the page has reloaded)
		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ status: [ 'open', 'resolved' ], username: [ 'TestUser1' ] }, window
		);
	} );
} );
