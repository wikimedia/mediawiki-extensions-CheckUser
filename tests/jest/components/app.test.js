'use strict';

jest.mock( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/icons.json', () => ( {
	cdxIconNext: '',
	cdxIconPrevious: ''
} ), { virtual: true } );

const App = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/App.vue' ),
	utils = require( '@vue/test-utils' );

const renderComponent = () => utils.mount( App );

/**
 * Mocks mw.config.get to mock the value of the
 * wgCheckUserIPInfoExtensionLoaded JS config.
 *
 * @param {boolean} isIPInfoEnabled
 */
function mockJSConfig( isIPInfoEnabled ) {
	jest.spyOn( mw.config, 'get' ).mockImplementation( ( actualConfigName ) => {
		if ( actualConfigName === 'wgCheckUserIPInfoExtensionLoaded' ) {
			return isIPInfoEnabled;
		} else {
			throw new Error( 'Did not expect a call to get the value of ' + actualConfigName );
		}
	} );
}

describe( 'Main app component', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly when IPInfo not installed', () => {
		mockJSConfig( false );

		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );

		// Check the dialog exists and that the introduction to temporary accounts step is shown.
		const rootElement = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		expect( rootElement.exists() ).toEqual( true );
		const introStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		);
		expect( introStepImage.exists() ).toEqual( true );

		// Expect that only one step exists, because IPInfo is not installed. This is done by
		// checking for the "Close" navigation button added by the dialog component.
		const footer = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const closeButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( closeButton.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-close-label)'
		);

		// Double check this by checking that the 'steps' prop only has one step
		expect( wrapper.vm.steps ).toHaveLength( 1 );
	} );

	it( 'Renders correctly when IPInfo installed', () => {
		mockJSConfig( true );

		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );

		// Check the dialog exists and that the introduction to temporary accounts step is shown.
		const rootElement = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		expect( rootElement.exists() ).toEqual( true );
		const introStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		);
		expect( introStepImage.exists() ).toEqual( true );

		// Expect that another step exists by checking that the next button is shown.
		const footer = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const nextButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( nextButton.exists() ).toEqual( true );
		expect( nextButton.attributes() ).toHaveProperty(
			'aria-label', '(checkuser-temporary-accounts-onboarding-dialog-next-label)'
		);

		// Double check this by checking that the 'steps' prop has two steps.
		expect( wrapper.vm.steps ).toHaveLength( 2 );
	} );
} );
