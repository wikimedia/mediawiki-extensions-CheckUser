'use strict';

jest.mock( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/icons.json', () => ( {
	cdxIconNext: '',
	cdxIconPrevious: ''
} ), { virtual: true } );

const TempAccountsOnboardingDialog = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingDialog.vue' ),
	utils = require( '@vue/test-utils' );

const renderComponent = ( slots, props ) => {
	const wrapper = utils.mount( TempAccountsOnboardingDialog, {
		props: Object.assign( {}, {
			open: true, totalSteps: 1,
			'onUpdate:open': async ( newOpenState ) => {
				await new Promise( process.nextTick );
				wrapper.setProps( { open: newOpenState } );
			}
		}, props ),
		slots: Object.assign( {}, slots )
	} );
	return wrapper;
};

/**
 * Mocks mw.Api().saveOption() and returns a jest.fn()
 * that is used as the saveOption() method. This can
 * be used to expect that the saveOption() method is
 * called with the correct arguments.
 *
 * @return {jest.fn}
 */
function mockApiSaveOption() {
	const apiSaveOption = jest.fn();
	apiSaveOption.mockResolvedValue( { options: 'success' } );
	jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
		saveOption: apiSaveOption
	} ) );
	return apiSaveOption;
}

/**
 * Waits for the return value of a given function to be true.
 * Will wait for a maximum of 1 second for the condition to be true.
 *
 * @param {Function} conditionCheck
 */
async function waitFor( conditionCheck ) {
	let tries = 0;
	while ( !conditionCheck() && tries < 20 ) {
		tries++;
		await new Promise( ( resolve ) => {
			setTimeout( () => resolve(), 50 );
		} );
	}
}

describe( 'Temporary Accounts dialog stepper component', () => {
	beforeEach( () => {
		jest.spyOn( mw.language, 'convertNumber' ).mockImplementation( ( number ) => number );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Dialog hidden when open is false', () => {
		const wrapper = renderComponent( {}, { open: false } );
		const dialog = wrapper.find( '.ext-checkuser-temp-account-onboarding-dialog' );
		expect( dialog.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for a total of one step', () => {
		const wrapper = renderComponent(
			{ step1: '<div class="test-class">Test content for step 1</div>' }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Check the dialog element exists.
		const dialog = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		expect( dialog.exists() ).toEqual( true );

		// Check that the header and footer elements exist.
		const header = dialog.find( '.ext-checkuser-temp-account-onboarding-dialog__header' );
		expect( header.exists() ).toEqual( true );
		const footer = dialog.find( '.ext-checkuser-temp-account-onboarding-dialog__footer' );
		expect( footer.exists() ).toEqual( true );

		// Check that the header element contains the "Skip all" button, the title,
		// and the stepper component.
		const skipAllButton = header.find(
			'.ext-checkuser-temp-account-onboarding-dialog__header__top__button'
		);
		expect( skipAllButton.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-skip-all)'
		);
		const title = header.find(
			'.ext-checkuser-temp-account-onboarding-dialog__header__top__title'
		);
		expect( title.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-title)'
		);
		const stepperComponent = header.find(
			'.ext-checkuser-temp-account-onboarding-dialog__header__stepper'
		);
		expect( stepperComponent.exists() ).toEqual( true );

		// Check that the footer contains the "Close" button only
		const closeButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( closeButton.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-close-label)'
		);
		const previousButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--prev'
		);
		expect( previousButton.exists() ).toEqual( false );

		// Expect that the content exists in the dialog that is defined via a slot
		const contentElement = wrapper.find( '.test-class' );
		expect( contentElement.exists() ).toEqual( true );
		expect( contentElement.text() ).toEqual( 'Test content for step 1' );
	} );

	it( 'Moves forward a step when next button clicked', async () => {
		const wrapper = renderComponent(
			{
				step1: '<div class="step1">Test content for step 1</div>',
				step2: '<div class="step2">Test content for step 2</div>'
			},
			{ totalSteps: 2 }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Check the footer has a "Next" button only.
		const footer = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const nextButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( nextButton.exists() ).toEqual( true );
		expect( nextButton.attributes() ).toHaveProperty(
			'aria-label', '(checkuser-temporary-accounts-onboarding-dialog-next-label)'
		);

		// Check that initially the step 1 content is displayed
		expect( wrapper.find( '.step1' ).exists() ).toEqual( true );
		expect( wrapper.find( '.step2' ).exists() ).toEqual( false );

		// Click the next button and wait for the DOM to be updated.
		await nextButton.trigger( 'click' );
		await waitFor( () => !wrapper.find( '.step1' ).exists() );

		// Expect that the previous and close buttons are now shown instead of next.
		const closeButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( closeButton.exists() ).toEqual( true );
		expect( closeButton.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-close-label)'
		);
		const previousButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--prev'
		);
		expect( previousButton.exists() ).toEqual( true );
		expect( previousButton.attributes() ).toHaveProperty(
			'aria-label', '(checkuser-temporary-accounts-onboarding-dialog-previous-label)'
		);

		// Expect that the second step is now shown
		expect( wrapper.find( '.step1' ).exists() ).toEqual( false );
		expect( wrapper.find( '.step2' ).exists() ).toEqual( true );
	} );

	it( 'Moves back a step when previous button clicked', async () => {
		const wrapper = renderComponent(
			{
				step1: '<div class="step1">Test content for step 1</div>',
				step2: '<div class="step2">Test content for step 2</div>'
			},
			{ totalSteps: 2 }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Click the next button to move to the second step and wait for the DOM to be updated.
		const footer = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const nextButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( nextButton.exists() ).toEqual( true );
		await nextButton.trigger( 'click' );
		await waitFor( () => !wrapper.find( '.step1' ).exists() );

		// Verify that the next button click worked.
		expect( wrapper.find( '.step1' ).exists() ).toEqual( false );
		expect( wrapper.find( '.step2' ).exists() ).toEqual( true );

		// Click the previous button to back to the first step and wait for the DOM to be updated.
		const previousButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--prev'
		);
		expect( previousButton.exists() ).toEqual( true );
		await previousButton.trigger( 'click' );
		await waitFor( () => !wrapper.find( '.step2' ).exists() );

		// Verify that we are now back on the first step after clicking the previous button
		expect( wrapper.find( '.step1' ).exists() ).toEqual( true );
		expect( wrapper.find( '.step2' ).exists() ).toEqual( false );
	} );

	it( 'Closes dialog if "Skip all" pressed and marks dialog as seen', async () => {
		const mockSaveOption = mockApiSaveOption();
		const wrapper = renderComponent(
			{
				step1: '<div class="step1">Test content for step 1</div>',
				step2: '<div class="step2">Test content for step 2</div>'
			},
			{ totalSteps: 2 }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Click the "Skip all" button and wait for the open property to be updated.
		const dialog = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		const header = dialog.find( '.ext-checkuser-temp-account-onboarding-dialog__header' );
		const skipAllButton = header.find(
			'.ext-checkuser-temp-account-onboarding-dialog__header__top__button'
		);
		expect( skipAllButton.exists() ).toEqual( true );
		await skipAllButton.trigger( 'click' );
		await waitFor( () => !wrapper.vm.open );

		// Expect the dialog has been closed, and the preference has been set to
		// indicate that the dialog been seen.
		expect( wrapper.vm.open ).toEqual( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'checkuser-temporary-accounts-onboarding-dialog-seen', 1
		);
	} );

	it( 'Closes dialog if "Close" button pressed and marks dialog as seen', async () => {
		const mockSaveOption = mockApiSaveOption();
		const wrapper = renderComponent(
			{ step1: '<div class="step1">Test content for step 1</div>' },
			{ totalSteps: 1 }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Click the "Close" button and wait for the open property to be updated.
		const dialog = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		const footer = dialog.find( '.ext-checkuser-temp-account-onboarding-dialog__footer' );
		const closeButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		expect( closeButton.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-close-label)'
		);
		await closeButton.trigger( 'click' );
		await waitFor( () => !wrapper.vm.open );

		// Expect the dialog has been closed, and the preference has been set to
		// indicate that the dialog been seen.
		expect( wrapper.vm.open ).toEqual( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'checkuser-temporary-accounts-onboarding-dialog-seen', 1
		);
	} );

	it( 'Closes dialog if Escape key is pressed and marks dialog as seen', async () => {
		const mockSaveOption = mockApiSaveOption();
		const wrapper = renderComponent(
			{ step1: '<div class="step1">Test content for step 1</div>' },
			{ totalSteps: 1 }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Simulate an Escape keypress and wait for the open property to be updated.
		const event = new KeyboardEvent( 'keyup', { key: 'Escape' } );
		window.dispatchEvent( event );
		await waitFor( () => !wrapper.vm.open );

		// Expect the dialog has been closed, and the preference has been set to
		// indicate that the dialog been seen.
		expect( wrapper.vm.open ).toEqual( false );
		expect( mockSaveOption ).toHaveBeenCalledWith(
			'checkuser-temporary-accounts-onboarding-dialog-seen', 1
		);
	} );
} );
