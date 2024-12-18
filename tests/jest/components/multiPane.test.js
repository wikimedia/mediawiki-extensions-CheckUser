'use strict';

/**
 * This test file is a modified copy of multiPane.test.js from the
 * mediawiki/extensions/GrowthExperiments repository.
 */

const MultiPane = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/MultiPane.vue' ),
	utils = require( '@vue/test-utils' );

const steps = {
	step1: '<p>This is step 1</p>',
	step2: '<p>This is step 2</p>',
	step3: '<p>This is step 3</p>'
};

const swipeToRight = ( wrapper ) => {
	// Start the touch
	const touchStartPromise = wrapper.trigger( 'touchstart', {
		touches: [ { clientX: 100 } ]
	} );
	// Then finish the touch simulating a move to the right.
	return touchStartPromise.then( () => wrapper.trigger( 'touchmove', {
		touches: [ { clientX: 150 } ]
	} ) );
};

const swipeToLeft = ( wrapper ) => {
	// Start the touch
	const touchStartPromise = wrapper.trigger( 'touchstart', {
		touches: [ { clientX: 100 } ]
	} );
	// Then finish the touch simulating a move to the right.
	return touchStartPromise.then( () => wrapper.trigger( 'touchmove', {
		touches: [ { clientX: 50 } ]
	} ) );
};

const renderComponent = ( props, slots ) => {
	const defaultProps = { currentStep: 1, totalSteps: 3 };
	return utils.mount( MultiPane, {
		props: Object.assign( {}, defaultProps, props ),
		slots: Object.assign( {}, slots )
	} );
};

describe( 'MultiPane component', () => {
	it( 'should render default slot content', () => {
		const wrapper = renderComponent(
			{ totalSteps: 1 }, { default: '<h3>Multipane component</h3>' }
		);
		expect( wrapper.text() ).toContain( 'Multipane component' );
	} );

	it( 'should render steps when slot content provided', () => {
		const wrapper = renderComponent( {}, steps );
		expect( wrapper.text() ).toContain( 'This is step 1' );
	} );

	it( 'should react to current step prop changes', async () => {
		const wrapper = renderComponent( {}, steps );
		expect( wrapper.text() ).toContain( 'This is step 1' );
		await wrapper.setProps( { currentStep: 2 } );
		expect( wrapper.text() ).toContain( 'This is step 2' );
		await wrapper.setProps( { currentStep: 1 } );
		expect( wrapper.text() ).toContain( 'This is step 1' );
	} );

	it( 'should emit update:currentStep event on touch events', async () => {
		const wrapper = renderComponent( {}, steps );
		await swipeToLeft( wrapper );
		expect( wrapper.emitted() ).toHaveProperty( 'update:currentStep' );
		expect( wrapper.emitted( 'update:currentStep' ) ).toEqual( [ [ 2 ] ] );
	} );

	it( 'currentStep should react to left swipe gestures and navigate next', async () => {
		const wrapper = renderComponent(
			{
				currentStep: 2,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } )
			},
			steps
		);
		await swipeToLeft( wrapper );
		expect( wrapper.vm.$props.currentStep ).toBe( 3 );
		expect( wrapper.text() ).toContain( 'step 3' );
	} );

	it( 'currentStep prop value should react to right swipe gestures and navigate back', async () => {
		const wrapper = renderComponent(
			{
				currentStep: 2,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } )
			},
			steps
		);
		await swipeToRight( wrapper );
		expect( wrapper.vm.$props.currentStep ).toBe( 1 );
		expect( wrapper.text() ).toContain( 'step 1' );
	} );

	it( 'should not react to right swipe gestures if there is no prev step', async () => {
		const wrapper = renderComponent(
			{
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } )
			},
			steps
		);
		await swipeToRight( wrapper );
		expect( wrapper.vm.$props.currentStep ).toBe( 1 );
		expect( wrapper.text() ).toContain( 'step 1' );
	} );

	it( 'should not react to left swipe gestures if there is no next step', async () => {
		const wrapper = renderComponent(
			{
				currentStep: 3,
				'onUpdate:currentStep': ( newVal ) => wrapper.setProps( { currentStep: newVal } )
			},
			steps
		);
		await swipeToLeft( wrapper );
		expect( wrapper.vm.$props.currentStep ).toBe( 3 );
		expect( wrapper.text() ).toContain( 'step 3' );
	} );

	it( 'should apply correct transition name on swipe to right', async () => {
		const wrapper = renderComponent( { currentStep: 2 }, steps );
		await swipeToRight( wrapper );
		expect( wrapper.vm.computedTransitionName ).toBe(
			'ext-checkuser-temp-account-onboarding-left'
		);
	} );

	it( 'should apply correct transition name on swipe to left', async () => {
		const wrapper = renderComponent( { currentStep: 1 }, steps );
		await swipeToLeft( wrapper );
		expect( wrapper.vm.computedTransitionName ).toBe(
			'ext-checkuser-temp-account-onboarding-right'
		);
	} );

	it( 'should apply correct transition name on call to navigatePrev', async () => {
		const wrapper = renderComponent( { currentStep: 2 }, steps );
		wrapper.vm.navigatePrev();
		expect( wrapper.vm.computedTransitionName ).toBe(
			'ext-checkuser-temp-account-onboarding-left'
		);
	} );

	it( 'should apply correct transition name on call to navigateNext', async () => {
		const wrapper = renderComponent( { currentStep: 2 }, steps );
		wrapper.vm.navigateNext();
		expect( wrapper.vm.computedTransitionName ).toBe(
			'ext-checkuser-temp-account-onboarding-right'
		);
	} );
} );
