'use strict';

const { mockCodePointLength } = require( '../utils.js' );

// Need to run this here as the import of ChangeInvestigationStatusDialog.vue
// without mediawiki.String defined causes errors in running these tests.
mockCodePointLength();

const ChangeInvestigationStatusDialog = require( '../../../modules/ext.checkUser.suggestedInvestigations/components/ChangeInvestigationStatusDialog.vue' ),
	utils = require( '@vue/test-utils' ),
	{ nextTick } = require( 'vue' ),
	{ waitFor } = require( '../utils.js' );

const renderComponent = ( props ) => utils.mount( ChangeInvestigationStatusDialog, {
	props: Object.assign( {}, { initialStatus: 'open', initialStatusReason: '' }, props )
} );

/**
 * Perform tests common to all tests of the suggested investigations change status
 * dialog and then return the dialog component
 *
 * @param {{ initialStatus: 'open'|'resolved'|'invalid', initialStatusReason: string }} props
 *   Passed through to {@link renderComponent}
 * @return {{ wrapper, dialog }} The dialog component and wrapper
 */
const commonComponentTest = async ( props ) => {
	// Render the component and wait for CdxDialog to run some code
	const wrapper = renderComponent( props );
	await nextTick();

	expect( wrapper.exists() ).toEqual( true );

	// Check the dialog element exists.
	const dialog = wrapper.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog'
	);
	expect( dialog.exists() ).toEqual( true );

	// Check that the description exists and has the expected text
	const description = dialog.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-description'
	);
	expect( description.exists() ).toEqual( true );
	expect( description.text() ).toEqual(
		'(checkuser-suggestedinvestigations-change-status-dialog-text)'
	);

	// Expect that the status radio options exist, including the invalid radio option
	// description
	const statusRadioOptionsField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-status-radio'
	);
	expect( statusRadioOptionsField.exists() ).toEqual( true );
	expect( statusRadioOptionsField.text() ).toContain(
		'(checkuser-suggestedinvestigations-change-status-dialog-status-list-header)'
	);

	for ( const status of [ 'open', 'resolved', 'invalid' ] ) {
		expect( statusRadioOptionsField.text() ).toContain(
			'(checkuser-suggestedinvestigations-status-' + status + ')'
		);
	}
	expect( statusRadioOptionsField.text() ).toContain(
		'(checkuser-suggestedinvestigations-status-description-invalid)'
	);

	const selectedRadioOption = dialog.find(
		'input[name=checkuser-suggestedinvestigations-change-status-dialog-status-option]:checked'
	);
	expect( selectedRadioOption.element.value ).toEqual( props.initialStatus );

	// Check that the dialog footer exists and that it has the expected buttons
	const footer = dialog.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-footer'
	);
	expect( footer.exists() ).toEqual( true );

	const cancelButton = footer.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn'
	);
	expect( cancelButton.exists() ).toEqual( true );
	expect( cancelButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-change-status-dialog-cancel-btn)'
	);
	const submitButton = footer.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__submit-btn'
	);
	expect( submitButton.exists() ).toEqual( true );
	expect( submitButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-change-status-dialog-submit-btn)'
	);

	return { dialog, wrapper };
};

/**
 * Validates that the status reason field exists in the dialog
 * along with some other checks common to all tests (that expect
 * the reason field to exist in the dialog)
 *
 * @param {*} dialog The component for the dialog
 * @param {string} status The case status that is currently selected
 * @param {string} expectedStatusReason The expected text in the status reason field
 * @return {*} The status reason field element
 */
const commonValidateStatusReasonField = ( dialog, status, expectedStatusReason ) => {
	const statusReasonField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason'
	);
	expect( statusReasonField.exists() ).toEqual( true );
	expect( statusReasonField.text() ).toContain(
		'(checkuser-suggestedinvestigations-change-status-dialog-status-reason-header)'
	);

	if ( status !== 'open' ) {
		expect( statusReasonField.text() ).toContain(
			'(checkuser-suggestedinvestigations-change-status-dialog-reason-description-' + status + ')'
		);
	}

	const reasonInputField = statusReasonField.find(
		'.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason__input input'
	);
	expect( reasonInputField.exists() ).toEqual( true );
	if ( status !== 'open' ) {
		expect( reasonInputField.element.placeholder ).toEqual(
			'(checkuser-suggestedinvestigations-change-status-dialog-reason-placeholder-' + status + ')'
		);
	}
	expect( reasonInputField.element.value ).toEqual( expectedStatusReason );

	return statusReasonField;
};

describe( 'Suggested Investigations change status dialog', () => {
	beforeEach( () => {
		jest.spyOn( mw.language, 'convertNumber' ).mockImplementation( ( number ) => number );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for initial open status with no pre-filled reason', async () => {
		const { dialog } = await commonComponentTest( { initialStatus: 'open' } );

		// Expect that the reason field does not exist, as it should not be present if
		// no reason was already set and the selected status is open
		const statusReasonField = dialog.find(
			'.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason'
		);
		expect( statusReasonField.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for initial resolved status with no pre-filled reason', async () => {
		const { dialog } = await commonComponentTest( { initialStatus: 'resolved' } );

		// The status reason field should always exist when the status is not "open"
		commonValidateStatusReasonField( dialog, 'resolved', '' );
	} );

	it( 'Renders correctly for initial invalid status', async () => {
		const { dialog } = await commonComponentTest( {
			initialStatus: 'invalid',
			initialStatusReason: 'test'
		} );

		// The status reason field should always exist when the status is not "open"
		commonValidateStatusReasonField( dialog, 'invalid', 'test' );
	} );

	it( 'Renders correctly for initial open status with prefilled reason', async () => {
		const { dialog } = await commonComponentTest( {
			initialStatus: 'open',
			initialStatusReason: 'testing'
		} );

		// The status reason field should always exist when the status is not "open"
		commonValidateStatusReasonField( dialog, 'open', 'testing' );
	} );

	it( 'Empty status reason field disappears when switching from resolved to open status', async () => {
		const { dialog } = await commonComponentTest( {
			initialStatus: 'resolved',
			initialStatusReason: ''
		} );

		// The status reason field should always exist when the status is not "open"
		commonValidateStatusReasonField( dialog, 'resolved', '' );

		// Switch the status radio to "open" and wait for the change to be propagated
		const openStatusRadioOption = dialog.find(
			'input[name=checkuser-suggestedinvestigations-change-status-dialog-status-option][value=open]'
		);
		await openStatusRadioOption.setChecked();
		await waitFor( () => !dialog.find( '.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason' ).exists() );

		// Validate that the reason field did not disappear with the change in status
		const statusReasonField = dialog.find( '.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason' );
		expect( statusReasonField.exists() ).toEqual( false );
	} );

	it( 'Keeps status reason field when text present and switching to "open" status', async () => {
		const { dialog } = await commonComponentTest( {
			initialStatus: 'resolved',
			initialStatusReason: ''
		} );

		// The status reason field should always exist when the status is not "open"
		commonValidateStatusReasonField( dialog, 'resolved', '' );

		// Add something to the status reason field
		const reasonInputField = dialog.find(
			'.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason__input input'
		);
		reasonInputField.setValue( 'testing' );

		// Switch the status radio to "open" and wait for the change to be propagated
		const openStatusRadioOption = dialog.find(
			'input[name=checkuser-suggestedinvestigations-change-status-dialog-status-option][value=open]'
		);
		await openStatusRadioOption.setChecked();
		await nextTick();

		// Validate that the reason field did not disappear with the change in status
		const statusReasonField = dialog.find( '.ext-checkuser-suggestedinvestigations-change-status-dialog-status-reason' );
		expect( statusReasonField.exists() ).toEqual( true );
	} );

	it( 'Closes dialog if "Cancel" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest( { initialStatus: 'open' } );

		// Press the cancel button
		const cancelButton = dialog.find(
			'.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn'
		);
		await cancelButton.trigger( 'click' );

		// Expect the dialog has been closed
		expect( wrapper.vm.open ).toEqual( false );
	} );
} );
