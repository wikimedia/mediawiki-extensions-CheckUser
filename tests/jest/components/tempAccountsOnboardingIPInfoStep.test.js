'use strict';

const TempAccountsOnboardingIPInfoStep = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingIPInfoStep.vue' ),
	utils = require( '@vue/test-utils' ),
	{ mockApiSaveOption, waitFor } = require( '../utils.js' );

const renderComponent = () => utils.mount( TempAccountsOnboardingIPInfoStep );

/**
 * Mocks mw.user.options.get to mock the value of the
 * ipinfo-use-agreement preference.
 *
 * @param {string|0} ipInfoPreferenceValue Value of the ipinfo-use-agreement
 *    preference for the test
 */
function mockUserOptions( ipInfoPreferenceValue ) {
	jest.spyOn( mw.user.options, 'get' ).mockImplementation( ( actualPreferenceName ) => {
		if ( actualPreferenceName === 'ipinfo-use-agreement' ) {
			return ipInfoPreferenceValue;
		} else {
			throw new Error(
				'Did not expect a call to get the value of ' + actualPreferenceName
			);
		}
	} );
}

/**
 * Performs tests on the step that are the same for all
 * starting conditions.
 *
 * @return {*} The root element for the step
 */
function commonTestRendersCorrectly() {
	const wrapper = renderComponent();
	expect( wrapper.exists() ).toEqual( true );

	// Check the root element exists
	const rootElement = wrapper.find(
		'.ext-checkuser-temp-account-onboarding-dialog-step'
	);
	expect( rootElement.exists() ).toEqual( true );

	// Check that the image element is present and has the necessary
	// class for the image to be placed there.
	const imageElement = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-image'
	);
	expect( imageElement.classes() ).toContain(
		'ext-checkuser-image-temp-accounts-onboarding-ip-info'
	);

	// Expect that the main body is present, and contains the title and content
	const mainBodyElement = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-main-body'
	);
	expect( mainBodyElement.exists() ).toEqual( true );
	const titleElement = mainBodyElement.find( 'h5' );
	expect( titleElement.exists() ).toEqual( true );
	expect( titleElement.text() ).toEqual(
		'(checkuser-temporary-accounts-onboarding-dialog-ip-info-step-title)'
	);
	const contentElement = mainBodyElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-content'
	);
	expect( contentElement.exists() ).toEqual( true );
	expect( contentElement.text() ).toContain(
		'(checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content)'
	);

	return rootElement;
}

describe( 'IPInfo step temporary accounts onboarding dialog', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for when IPInfo preference was already checked', () => {
		mockUserOptions( '1' );

		const rootElement = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has already checked it.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IPInfo preference is default value', () => {
		// Test using the integer 0, as the default value is the integer 0 for users which do
		// not have a different value for the preference.
		mockUserOptions( 0 );

		const rootElement = commonTestRendersCorrectly();

		// Check that the preference exists in the step and verify the structure of the preference
		// and it's title.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipInfoPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title)'
		);
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( true );
		expect( ipInfoPreference.text() ).toContain(
			'(ipinfo-preference-use-agreement)'
		);
		const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
		expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );
	} );

	it( 'Updates IPInfo preference value if checkbox is checked', async () => {
		// Test using the string with 0 in it, to test in case the user_properties table has the
		// preference specifically marked as unchecked.
		mockUserOptions( '0' );
		const apiSaveOptionMock = mockApiSaveOption( true );

		const rootElement = commonTestRendersCorrectly();

		// Check that the preference exists in the step
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( true );
		const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
		expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );

		// Check the preference and check that an API call is made to set the preference.
		ipInfoPreferenceCheckbox.setChecked();
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );

		// Set the checked value of the checkbox back to unchecked to test the
		// API being called to uncheck the preference.
		ipInfoPreferenceCheckbox.setChecked( false );
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 0 );
	} );

	it( 'Displays error message if IPInfo preference check failed', async () => {
		mockUserOptions( '0' );
		const apiSaveOptionMock = mockApiSaveOption(
			false, { error: { info: 'Wiki is in read only mode' } }
		);

		const rootElement = commonTestRendersCorrectly();

		// Check that the preference exists in the step
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( true );
		const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
		expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );

		// Check the preference, expect that to fail and wait for the error to appear.
		ipInfoPreferenceCheckbox.setChecked();
		await waitFor( () => ipInfoPreference.text().indexOf(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error'
		) !== -1 );
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );

		// Assert that an error message is displayed because the request failed,
		// which should contain the reason for the request failure.
		expect( ipInfoPreference.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error' +
				', Wiki is in read only mode)'
		);
	} );

	it( 'Displays error message if IPInfo preference check failed for no response', async () => {
		mockUserOptions( '0' );
		const apiSaveOptionMock = mockApiSaveOption( false, {}, 'http' );

		const rootElement = commonTestRendersCorrectly();

		// Check the preference, expect that to fail and wait for the error to appear.
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
		ipInfoPreferenceCheckbox.setChecked();
		await waitFor( () => ipInfoPreference.text().indexOf(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error'
		) !== -1 );
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );

		// Assert that an error message is displayed because the request
		// failed and that the error code is used because no human friendly
		// error was provided.
		expect( ipInfoPreference.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error, http)'
		);
	} );
} );
