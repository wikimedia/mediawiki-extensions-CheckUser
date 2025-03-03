'use strict';

const { shallowMount } = require( '@vue/test-utils' );
const mockPerformFullRevealRequest = jest.fn();
global.mw.util.isTemporaryUser = jest.fn().mockImplementation(
	( username ) => String( username ).startsWith( '~' )
);
global.mw.language.listToText = jest.fn().mockImplementation(
	( list ) => list.join( ', ' )
);
jest.mock(
	'../../../modules/ext.checkUser.tempAccounts/rest.js',
	() => ( { performFullRevealRequest: mockPerformFullRevealRequest } ),
	{ virtual: true }
);
const ShowIPButton = require( '../../../modules/ext.checkUser.tempAccounts/ShowIPButton.vue' );

const renderComponent = ( propsData ) => shallowMount( ShowIPButton, { propsData } );

describe( 'ShowIPButton', () => {
	it( 'renders a button when given a valid temporary user', () => {
		let wrapper = renderComponent( { targetUser: null } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( false );

		wrapper = renderComponent( { targetUser: '192.168.0.1' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( false );

		wrapper = renderComponent( { targetUser: '~2025' } );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( true );
	} );

	it( 'should show checkuser-tempaccount-specialblock-ips for returned ips', async () => {
		mockPerformFullRevealRequest.mockResolvedValue( { ips: [ '1.2.3.4', '5.6.7.8' ] } );
		const wrapper = renderComponent( { targetUser: '~2025' } );

		await wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).trigger( 'click' );

		expect( mockPerformFullRevealRequest ).toHaveBeenCalledWith( '~2025', [], [] );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).text() )
			.toStrictEqual( '(checkuser-tempaccount-specialblock-ips, 2, 1.2.3.4, 5.6.7.8)' );
	} );

	it( 'should show checkuser-tempaccount-no-ip-results when there are no results', async () => {
		mockPerformFullRevealRequest.mockResolvedValue( { ips: [] } );
		const wrapper = renderComponent( { targetUser: '~2025' } );

		await wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).trigger( 'click' );

		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).text() )
			.toStrictEqual( '(checkuser-tempaccount-no-ip-results, NaN)' );
	} );

	it( 'should show checkuser-tempaccount-reveal-ip-error for a failed request', async () => {
		mockPerformFullRevealRequest.mockRejectedValue( {} );
		const wrapper = renderComponent( { targetUser: '~2025' } );

		await wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).trigger( 'click' );

		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips' ).text() )
			.toStrictEqual( '(checkuser-tempaccount-reveal-ip-error)' );
	} );

	it( 'should clear the message and show the button when the target changes', async () => {
		const wrapper = renderComponent( { targetUser: '~2025' } );
		wrapper.vm.message = 'foo';
		await wrapper.setProps( { targetUser: '~2026' } );
		expect( wrapper.vm.message ).toStrictEqual( '' );
		expect( wrapper.find( '.ext-checkuser-tempaccount-specialblock-ips-link' ).exists() )
			.toStrictEqual( true );
	} );
} );
