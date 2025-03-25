'use strict';

// Mock utils methods to check they are called properly. Set up fake time now
// and fake expiry time 1 hour later.
const mockGetAutoRevealStatus = jest.fn();
const mockNowInSeconds = 1741604813;
const mockExpiryInSeconds = mockNowInSeconds + 3600;
mockGetAutoRevealStatus.mockReturnValue( String( mockExpiryInSeconds ) );
const mockSetAutoRevealStatus = jest.fn();
jest.mock( '../../../modules/ext.checkUser.tempAccounts/ipRevealUtils.js', () => ( {
	getAutoRevealStatus: mockGetAutoRevealStatus,
	setAutoRevealStatus: mockSetAutoRevealStatus
} ) );

const IPAutoRevealOffDialog = require( '../../../modules/ext.checkUser.tempAccounts/components/IPAutoRevealOffDialog.vue' );
const { nextTick } = require( 'vue' );
const utils = require( '@vue/test-utils' );
const { CdxDialog } = require( '@wikimedia/codex' );

describe( 'IP auto-reveal Off dialog', () => {
	let wrapper;

	beforeEach( () => {
		jest.useFakeTimers();
		jest.spyOn( Date, 'now' ).mockReturnValue( mockNowInSeconds * 1000 );
		wrapper = utils.mount( IPAutoRevealOffDialog );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();

		// In case a test overrides the mock expiry, restore it again
		mockGetAutoRevealStatus.mockReturnValue( mockExpiryInSeconds );
	} );

	it( 'mounts correctly', () => {
		expect( wrapper.exists() ).toEqual( true );
	} );

	it( 'displays the expiry time correctly', async () => {
		const expiryText = wrapper.find( 'p' ).html();
		expect( expiryText ).toContain( 'checkuser-ip-auto-reveal-off-dialog-text-expiry' );
		expect( expiryText ).toContain( '1:00:00' );
	} );

	it( 'updates the displayed time correctly', async () => {
		const initialExpiryText = wrapper.find( 'p' ).html();
		expect( initialExpiryText ).toContain( '1:00:00' );

		jest.advanceTimersByTime( 1001 );
		await nextTick();

		const updatedExpiryText = wrapper.find( 'p' ).html();
		expect( updatedExpiryText ).toContain( '0:59:59' );
	} );

	it( 'calls setAutoRevealStatus with extended time on default action', async () => {
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		expect( mockSetAutoRevealStatus ).toHaveBeenCalled();
		const calledWith = mockSetAutoRevealStatus.mock.calls[ 0 ][ 0 ];

		// Check that time is extended by 600 seconds
		const expectedExpiryInSeconds = mockExpiryInSeconds + 600 - mockNowInSeconds;
		expect( calledWith ).toBeCloseTo( expectedExpiryInSeconds, 0 );
	} );

	it( 'calls setAutoRevealStatus with extended time on default action after expiry', async () => {
		mockGetAutoRevealStatus.mockReturnValue( null );
		await wrapper.findComponent( CdxDialog ).vm.$emit( 'default' );

		const expectedExpiryInSeconds = 600;

		// Check that expiry is set to 600 seconds from now
		expect( mockSetAutoRevealStatus ).toHaveBeenCalledTimes( 1 );
		expect( mockSetAutoRevealStatus ).toHaveBeenLastCalledWith( expectedExpiryInSeconds );
	} );

	it( 'calls setAutoRevealStatus with empty string and reloads on primary action', async () => {
		global.window = Object.create( window );
		Object.defineProperty( window, 'location', {
			value: { reload: jest.fn() }
		} );

		await wrapper.findComponent( CdxDialog ).vm.$emit( 'primary' );

		expect( mockSetAutoRevealStatus ).toHaveBeenCalledWith( '' );
		expect( window.location.reload ).toHaveBeenCalled();
	} );
} );
