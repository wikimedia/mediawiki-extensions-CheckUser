'use strict';

// Mock utils methods to check they are called properly
const mockSetAutoRevealStatus = jest.fn();
jest.mock( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js', () => ( {
	setAutoRevealStatus: mockSetAutoRevealStatus
} ) );

const IPAutoRevealOnDialog = require( '../../../modules/ext.checkUser.tempAccounts/components/IPAutoRevealOnDialog.vue' );
const utils = require( '@vue/test-utils' );
const { CdxDialog, CdxSelect } = require( '@wikimedia/codex' );

describe( 'IP auto-reveal On dialog', () => {
	let wrapper;

	beforeEach( () => {
		wrapper = utils.mount( IPAutoRevealOnDialog );
	} );

	it( 'mounts correctly', () => {
		expect( wrapper.exists() ).toEqual( true );
	} );

	it( 'disables the primary action button initially', () => {
		expect( wrapper.findComponent( CdxDialog ).props( 'primaryAction' ).disabled ).toEqual( true );
	} );

	it( 'enables the primary action button when a selection is made', async () => {
		const select = wrapper.findComponent( CdxSelect );
		await select.vm.$emit( 'update:selected', '1800' );

		expect( wrapper.findComponent( CdxDialog ).props( 'primaryAction' ).disabled ).toEqual( false );
	} );

	it( 'calls setAutoRevealStatus and reloads the page on submit', async () => {
		global.window = Object.create( window );
		Object.defineProperty( window, 'location', {
			value: { reload: jest.fn() }
		} );

		const select = wrapper.findComponent( CdxSelect );
		await select.vm.$emit( 'update:selected', '1800' );
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );

		expect( mockSetAutoRevealStatus ).toHaveBeenCalledWith( '1800' );
		expect( window.location.reload ).toHaveBeenCalled();
	} );
} );
