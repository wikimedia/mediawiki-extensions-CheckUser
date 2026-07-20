'use strict';

const utils = require( '@vue/test-utils' );

const RelatedCasesMessage = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/RelatedCasesMessage.vue' );

describe( 'Suggested Investigations related messages notice', () => {
	const hooks = {};
	/**
	 * Return the component under test with appropriate stubs
	 *
	 * @param {Object} [propsState={}]
	 * @param {Object} [configState={}]
	 * @param {Object|string} [restResult={}]
	 *
	 * @return {utils.VueWrapper}
	 */
	function setupAndMount( propsState = {}, configState = {}, restResult = {} ) {
		global.mw = {
			user: {
				tokens: { get: jest.fn().mockReturnValue( 'csrf-token' ) }
			},
			hook: ( name ) => ( {
				add: ( callback ) => {
					hooks[ name ] = callback;
				}
			} ),
			util: {
				getUrl() {}
			},
			config: { get: () => {} },
			track: () => {},
			Rest: class {
				async post() {}

				async abort() {}
			}
		};

		const config = {
			wgCheckUserSuggestedInvestigationsEnabled: true,
			wgCheckUserCanViewSuggestedInvestigations: true,
			...configState
		};
		jest.spyOn( mw.config, 'get' ).mockImplementation( ( key ) => {
			if ( key in config ) {
				return config[ key ];
			} else {
				return null;
			}
		} );
		jest.spyOn( mw, 'track' ).mockImplementation( () => {} );
		if ( typeof restResult === 'string' ) {
			jest.spyOn( mw.Rest.prototype, 'post' ).mockRejectedValue(
				new Error( restResult )
			);
		} else {
			jest.spyOn( mw.Rest.prototype, 'post' ).mockResolvedValue( restResult );
		}
		return utils.mount( RelatedCasesMessage, {
			props: {
				targetUser: '',
				...propsState
			}
		} );
	}

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Should do nothing if view permission conditions are not met', async () => {
		const wrapper = setupAndMount( { targetUser: 'User' }, {
			wgCheckUserCanViewSuggestedInvestigations: false
		}, {} );
		await wrapper.vm.$nextTick();
		expect( mw.track ).not.toHaveBeenCalled();
		expect( mw.Rest.prototype.post ).not.toHaveBeenCalled();
		expect( wrapper.vm.visible ).toBe( false );
		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( false );
	} );

	it( 'Should be displayed if results are found', async () => {
		const wrapper = setupAndMount( { targetUser: 'User' }, {}, {
			relatedCasesCount: 1,
			relatedUserIdsCount: 2
		} );
		await wrapper.vm.$nextTick();
		expect( mw.Rest.prototype.post ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.vm.visible ).toBe( true );
		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( true );

		// Instrumentation should track check and hit
		expect( mw.track ).toHaveBeenCalledTimes( 2 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
				1,
				{ action: 'lookup' }
			],
			[
				'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
				1,
				{
					action: 'hit',
					relateduserscount: 2,
					relatedcasescount: 1
				}
			]
		] );
	} );

	it( 'Should not be displayed if no results are found', async () => {
		const wrapper = setupAndMount( { targetUser: 'User' }, {}, {
			relatedCasesCount: 0,
			relatedUserIdsCount: 0
		} );
		await wrapper.vm.$nextTick();
		expect( mw.Rest.prototype.post ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.vm.visible ).toBe( 0 );
		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( false );

		// Instrumentation should only track the lookup attempt
		expect( mw.track ).toHaveBeenCalledTimes( 1 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
				1,
				{ action: 'lookup' }
			]
		] );
	} );

	it( 'Should not be displayed if the api returns an error', async () => {
		const wrapper = setupAndMount( { targetUser: 'User' }, {}, 'http' );
		await wrapper.vm.$nextTick();
		expect( mw.Rest.prototype.post ).toHaveBeenCalledTimes( 1 );
		expect( wrapper.vm.visible ).toBe( 0 );
		expect( wrapper.find( '.cdx-message' ).exists() ).toBe( false );

		// Instrumentation should track the lookup attempt and failure
		expect( mw.track ).toHaveBeenCalledTimes( 2 );
		expect( mw.track.mock.calls ).toEqual( [
			[
				'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
				1,
				{ action: 'lookup' }
			],
			[
				'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
				1,
				{ action: 'apierror' }
			]
		] );
	} );

	it( 'Should only update when user is passed through', async () => {
		const wrapper = setupAndMount( {}, {}, {} );
		await wrapper.vm.$nextTick();
		expect( mw.Rest.prototype.post ).toHaveBeenCalledTimes( 0 );

		await wrapper.setProps( { targetUser: 'User' } );
		await wrapper.vm.$nextTick();
		expect( mw.Rest.prototype.post ).toHaveBeenCalledTimes( 1 );
	} );
} );
