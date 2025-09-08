'use strict';

const { mount } = require( '@vue/test-utils' );

jest.mock( 'mediawiki.String', () => ( {
	codePointLength: ( str ) => str.length,
	trimCodePointLength: ( safeVal, newVal, codePointLimit ) => ( {
		newVal: newVal.slice( 0, codePointLimit )
	} )
} ), { virtual: true } );

const CharacterLimitedTextArea = require( '../../../modules/ext.checkUser.suggestedInvestigations/components/CharacterLimitedTextArea.vue' );

describe( 'CharacterLimitedTextArea', () => {
	beforeEach( () => {
		const mwConvertNumber = jest.fn();
		mwConvertNumber.mockImplementation( ( number ) => String( number ) );
		mw.language.convertNumber = mwConvertNumber;
	} );

	function render( props = {} ) {
		const wrapper = mount( CharacterLimitedTextArea, {
			props: Object.assign( {
				codePointLimit: 600,
				textContent: '',
				'onUpdate:text-content': ( textContent ) => wrapper.setProps( { textContent } )
			}, props )
		} );

		return wrapper;
	}

	it( 'should update content and not show character count when far from the limit', async () => {
		const wrapper = render();

		const initialCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );

		await wrapper.find( 'textarea' ).setValue( 'test' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );

		const emitted = wrapper.emitted();

		expect( emitted[ 'update:text-content' ] ).toStrictEqual( [ [ 'test' ] ] );
		expect( initialCharacterCount.exists() ).toBe( false );
		expect( newCharacterCount.exists() ).toBe( false );
	} );

	it( 'shows character count when near the limit', async () => {
		const wrapper = render( { codePointLimit: 100 } );

		const initialCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );

		await wrapper.find( 'textarea' ).setValue( 'test' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );

		expect( initialCharacterCount.exists() ).toBe( false );
		expect( newCharacterCount.text() ).toBe( '96' );
	} );

	it( 'limits character count after exceeding the limit', async () => {
		const wrapper = render( { codePointLimit: 6 } );

		await wrapper.find( 'textarea' ).setValue( 'abcdefghi' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );
		const newValue = wrapper.find( 'textarea' ).element.value;

		expect( newCharacterCount.text() ).toBe( '0' );
		expect( newValue ).toBe( 'abcdef' );
	} );

	it( 'does not show character count without input even if initial limit is small', async () => {
		const wrapper = render( { codePointLimit: 50 } );

		const initialCharacterCount = wrapper.find( '.ext-checkuser-dialog__textarea-character-count' );

		expect( initialCharacterCount.exists() ).toBe( false );
	} );

	it( 'should forward other props to textarea wrapper component', () => {
		const wrapper = render( { class: 'foo' } );

		expect( wrapper.find( '.cdx-text-area' ).classes() ).toContain( 'foo' );
	} );
} );
