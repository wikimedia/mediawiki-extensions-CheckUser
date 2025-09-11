'use strict';

const ChangeInvestigationStatusDialog = require( '../../../modules/ext.checkUser.suggestedInvestigations/components/ChangeInvestigationStatusDialog.vue' ),
	utils = require( '@vue/test-utils' ),
	{ nextTick } = require( 'vue' );

const renderComponent = () => utils.mount( ChangeInvestigationStatusDialog );

/**
 * Perform tests common to all tests of the suggested investigations change status
 * dialog and then return the dialog component
 *
 * @return {{ wrapper: VueWrapper, dialog: DOMWrapper<Element> }} The dialog component and wrapper
 */
const commonComponentTest = async () => {
	// Render the component and wait for CdxDialog to run some code
	const wrapper = renderComponent();
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

describe( 'Suggested Investigations change status dialog', () => {
	it( 'Closes dialog if "Cancel" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest();

		// Press the cancel button
		const cancelButton = dialog.find(
			'.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn'
		);
		await cancelButton.trigger( 'click' );

		// Expect the dialog has been closed
		expect( wrapper.vm.open ).toEqual( false );
	} );
} );
