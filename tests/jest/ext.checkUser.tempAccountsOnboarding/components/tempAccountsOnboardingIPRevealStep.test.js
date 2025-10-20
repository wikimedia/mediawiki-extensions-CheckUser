'use strict';

const TempAccountsOnboardingIPRevealStep = require( '../../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingIPRevealStep.vue' ),
	utils = require( '@vue/test-utils' ),
	{ mockJSConfig, mockApiSaveOption, waitForAndExpectTextToExistInElement, mockStorageSessionGetValue, getSaveGlobalPreferenceButton } = require( '../../utils.js' );

/**
 * Mocks mw.storage.session.get to return a specific value when asked for
 * the 'mw-checkuser-ip-reveal-preference-checked-status' key.
 *
 * @param {false|'checked'|''|null} value null when no value was set, false when storage is not
 *   available, empty string when the preference was not checked, string 'checked' when the
 *   preference was checked.
 */
function mockIPRevealPreferenceCheckedSessionStorageValue( value ) {
	mockStorageSessionGetValue( 'mw-checkuser-ip-reveal-preference-checked-status', value );
}

/**
 * Performs tests on the step that are the same for all
 * starting conditions.
 *
 * @return {{ rootElement, mainBodyElement, wrapper }} The root element, main body element,
 *   and wrapper for the component under test
 */
function commonTestRendersCorrectly() {
	const wrapper = utils.mount( TempAccountsOnboardingIPRevealStep );
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
		'ext-checkuser-image-temp-accounts-onboarding-ip-reveal'
	);

	// Expect that the main body is present, contains the title and content, and does not
	// have the preference (as it has already been enabled)
	const mainBodyElement = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-main-body'
	);
	expect( mainBodyElement.exists() ).toEqual( true );
	const titleElement = mainBodyElement.find( 'h5' );
	expect( titleElement.exists() ).toEqual( true );
	expect( titleElement.text() ).toEqual(
		'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-title)'
	);

	return { rootElement: rootElement, mainBodyElement: mainBodyElement, wrapper: wrapper };
}

/**
 * Gets the IP reveal preference checkbox element after checking that it exists.
 *
 * @param {*} mainBodyElement The root element for the IP reveal step
 * @param {boolean} globalPreferencesInstalled Whether GlobalPreferences is installed
 *   (per mocked JS config)
 * @return {*} The IP reveal checkbox element
 */
function getIPRevealPreferenceCheckbox( mainBodyElement, globalPreferencesInstalled ) {
	const ipInfoPreference = mainBodyElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-preference'
	);
	expect( ipInfoPreference.exists() ).toEqual( true );
	let expectedCheckboxMessageKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-checkbox-text';
	if ( globalPreferencesInstalled ) {
		expectedCheckboxMessageKey += '-with-global-preferences';
	}
	expect( ipInfoPreference.text() ).toContain( expectedCheckboxMessageKey );
	const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
	expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );
	return ipInfoPreferenceCheckbox;
}

describe( 'IP reveal step of temporary accounts onboarding dialog', () => {
	it( 'renders correctly when GlobalPreferences not installed and preference already checked', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( null );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: false,
			wgCheckUserIPRevealPreferenceGloballyChecked: true,
			wgCheckUserIPRevealPreferenceLocallyChecked: true,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		const contentElement = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-content'
		);
		expect( contentElement.exists() ).toEqual( true );
		expect( contentElement.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content)'
		);

		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( true );
		expect( preferenceNotice.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-locally-enabled)'
		);

		const preference = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( preference.exists() ).toEqual( false );
	} );

	it( 'renders correctly when GlobalPreferences installed and preference already checked', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( null );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: true,
			wgCheckUserIPRevealPreferenceGloballyChecked: true,
			wgCheckUserIPRevealPreferenceLocallyChecked: true,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		const contentElement = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-content'
		);
		expect( contentElement.exists() ).toEqual( true );
		expect( contentElement.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-step-content-with-global-preferences)'
		);

		// Expect that the preference is not there and an information icon is displayed indicating
		// the preference has already been enabled.
		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( true );
		expect( preferenceNotice.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-globally-enabled)'
		);
		const preference = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( preference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly when IP reveal preference was checked previously via the dialog', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( 'checked' );
		// Mock the JS config says the preference is unchecked, which can happen if the user
		// had checked the preference and then moved back to this step.
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: true,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: false,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		// Expect that the step content says that the preference is already enabled,
		// even though the JS config didn't say this.
		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( true );
		expect( preferenceNotice.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-globally-enabled)'
		);
		const preference = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( preference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IP reveal preference is unchecked with GlobalPreferences installed', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( false );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: true,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: false,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		// Expect that the step content has the IP reveal preference which unchecked
		const ipRevealPreferenceSectionTitle = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipRevealPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipRevealPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title-with-global-preferences)'
		);

		const ipRevealPreferenceCheckbox = getIPRevealPreferenceCheckbox( mainBodyElement, false );
		expect( ipRevealPreferenceCheckbox.element.checked ).toEqual( false );

		const ipRevealPreferenceDescription = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-description'
		);
		expect( ipRevealPreferenceDescription.exists() ).toEqual( true );
		expect( ipRevealPreferenceDescription.text() ).toContain(
			'(checkuser-tempaccount-enable-preference-description)'
		);

		getSaveGlobalPreferenceButton( mainBodyElement, true );

		// No notice should be displayed as the user has not enabled the preference anywhere
		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IP reveal preference is unchecked without GlobalPreferences installed', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( false );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: false,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: false,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		// Expect that the step content has the IP reveal preference which unchecked
		const ipRevealPreferenceSectionTitle = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipRevealPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipRevealPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title)'
		);

		const ipRevealPreferenceCheckbox = getIPRevealPreferenceCheckbox( mainBodyElement, false );
		expect( ipRevealPreferenceCheckbox.element.checked ).toEqual( false );

		getSaveGlobalPreferenceButton( mainBodyElement, false );

		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IP reveal preference is globally unchecked but locally checked', () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( false );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: true,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: true,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement } = commonTestRendersCorrectly();

		// Expect that the step content has the IP reveal preference which unchecked
		const ipRevealPreferenceSectionTitle = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipRevealPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipRevealPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-title-with-global-preferences)'
		);

		const ipRevealPreferenceCheckbox = getIPRevealPreferenceCheckbox( mainBodyElement, false );
		expect( ipRevealPreferenceCheckbox.element.checked ).toEqual( false );

		getSaveGlobalPreferenceButton( mainBodyElement, true );

		// Because the preference was locally enabled, there should be a note
		// to indicate this to the user even though the preference checkbox is displayed
		const preferenceNotice = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-notice'
		);
		expect( preferenceNotice.exists() ).toEqual( true );
		expect( preferenceNotice.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-reveal-preference-locally-enabled)'
		);
	} );

	it( 'Updates IP reveal preference value after checkbox and submit pressed', async () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( '' );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: true,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: false,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );
		const apiSaveOptionMock = mockApiSaveOption( true );

		const { mainBodyElement, wrapper } = commonTestRendersCorrectly();

		const ipRevealPreferenceCheckbox = getIPRevealPreferenceCheckbox( mainBodyElement, true );
		const ipRevealSavePreferenceButton = getSaveGlobalPreferenceButton( mainBodyElement, true );
		const ipRevealPreference = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Check the preference checkbox and then press the "Save preference" button
		// and check that an API call is made to set the preference.
		ipRevealPreferenceCheckbox.setChecked();
		await ipRevealSavePreferenceButton.trigger( 'click' );
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'checkuser-temporary-account-enable', 1, { global: 'create' } );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			ipRevealPreference, '(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);

		// Check that if the preference saved, the user can move forward to another
		// step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );
	} );

	it( 'Prevents step move and dialog close if IP reveal preference checked but not saved', async () => {
		mockIPRevealPreferenceCheckedSessionStorageValue( '' );
		mockJSConfig( {
			wgCheckUserGlobalPreferencesExtensionLoaded: false,
			wgCheckUserIPRevealPreferenceGloballyChecked: false,
			wgCheckUserIPRevealPreferenceLocallyChecked: false,
			wgCheckUserTemporaryAccountAutoRevealPossible: false
		} );

		const { mainBodyElement, wrapper } = commonTestRendersCorrectly();

		const ipRevealPreferenceCheckbox = getIPRevealPreferenceCheckbox( mainBodyElement, false );

		// Check the preference checkbox, but don't save the preference using the button
		ipRevealPreferenceCheckbox.setChecked();

		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( false );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( true );
	} );
} );
