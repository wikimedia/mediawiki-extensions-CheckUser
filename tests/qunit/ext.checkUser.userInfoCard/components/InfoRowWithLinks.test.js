'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const InfoRowWithLinks = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/InfoRowWithLinks.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.InfoRowWithLinks', QUnit.newMwEnvironment() );

// Sample icon for testing
const sampleIcon = {
	path: 'M10 10 H 90 V 90 H 10 Z',
	width: 24,
	height: 24
};

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( InfoRowWithLinks, {
		propsData: {
			mainLabel: 'Test Label',
			mainValue: 'Test Value',
			...props
		}
	} );
}

QUnit.test( 'renders correctly with minimal props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.classes().includes( 'ext-checkuser-userinfocard-short-paragraph' ),
		'Paragraph has correct class'
	);
} );

QUnit.test( 'does not render icon when icon prop is not provided', ( assert ) => {
	const wrapper = mountComponent();

	const icon = wrapper.findComponent( { name: 'CdxIcon' } );
	assert.false( icon.exists(), 'Icon does not exist when icon prop is not provided' );
} );

QUnit.test( 'renders icon when icon prop is provided', ( assert ) => {
	const wrapper = mountComponent( { icon: sampleIcon, iconClass: 'test-icon-class' } );

	const icon = wrapper.findComponent( { name: 'CdxIcon' } );
	assert.true( icon.exists(), 'Icon exists when icon prop is provided' );
	assert.deepEqual( icon.props( 'icon' ), sampleIcon, 'Icon has correct icon prop' );
	assert.true( icon.classes().includes( 'test-icon-class' ), 'Icon has correct class' );
} );

QUnit.test( 'displays main label correctly', ( assert ) => {
	const wrapper = mountComponent( { mainLabel: 'Custom Label' } );

	assert.true( wrapper.text().includes( 'Custom Label:' ), 'Main label is displayed correctly' );
} );

QUnit.test( 'renders span for main value when mainLink is not provided', ( assert ) => {
	const wrapper = mountComponent( { mainValue: 'Custom Value' } );

	const mainValueSpan = wrapper.find( 'span' );
	assert.true( mainValueSpan.exists(), 'Main value span exists' );
	assert.strictEqual( mainValueSpan.text(), 'Custom Value', 'Main value span has correct text' );

	const mainValueLink = wrapper.find( 'a' );
	assert.false( mainValueLink.exists(), 'Main value link does not exist' );
} );

QUnit.test( 'renders link for main value when mainLink is provided', ( assert ) => {
	const wrapper = mountComponent( {
		mainValue: 'Custom Value',
		mainLink: 'https://example.com'
	} );

	const mainValueLink = wrapper.find( 'a' );
	assert.true( mainValueLink.exists(), 'Main value link exists' );
	assert.strictEqual( mainValueLink.text(), 'Custom Value', 'Main value link has correct text' );
	assert.strictEqual(
		mainValueLink.attributes( 'href' ),
		'https://example.com',
		'Main value link has correct href'
	);
} );

QUnit.test( 'does not render suffix section when suffixLabel is not provided', ( assert ) => {
	const wrapper = mountComponent( { suffixValue: 'Suffix Value' } );

	assert.false( wrapper.text().includes( 'Suffix Value' ), 'Suffix section is not rendered' );
} );

QUnit.test( 'does not render suffix section when suffixValue is empty', ( assert ) => {
	const wrapper = mountComponent( { suffixLabel: 'Suffix Label', suffixValue: '' } );

	assert.false( wrapper.text().includes( 'Suffix Label' ), 'Suffix section is not rendered' );
} );

QUnit.test( 'renders suffix section when both suffixLabel and suffixValue are provided', ( assert ) => {
	const wrapper = mountComponent( {
		suffixLabel: 'Suffix Label',
		suffixValue: 'Suffix Value'
	} );

	assert.true(
		wrapper.text().includes( '(Suffix Label:' ),
		'Suffix label is displayed correctly'
	);
	assert.true(
		wrapper.text().includes( 'Suffix Value' ),
		'Suffix value is displayed correctly'
	);
} );

QUnit.test( 'renders span for suffix value when suffixLink is not provided', ( assert ) => {
	const wrapper = mountComponent( {
		suffixLabel: 'Suffix Label',
		suffixValue: 'Suffix Value'
	} );

	// Find all spans, the second one should be for the suffix value
	const spans = wrapper.findAll( 'span' );
	assert.strictEqual( spans.length, 2, 'Two spans exist' );
	assert.strictEqual( spans[ 1 ].text(), 'Suffix Value', 'Suffix value span has correct text' );
} );

QUnit.test( 'renders link for suffix value when suffixLink is provided', ( assert ) => {
	const wrapper = mountComponent( {
		suffixLabel: 'Suffix Label',
		suffixValue: 'Suffix Value',
		suffixLink: 'https://example.org'
	} );

	const links = wrapper.findAll( 'a' );
	assert.strictEqual( links.length, 1, 'One link exists' );
	assert.strictEqual( links[ 0 ].text(), 'Suffix Value', 'Suffix value link has correct text' );
	assert.strictEqual(
		links[ 0 ].attributes( 'href' ),
		'https://example.org',
		'Suffix value link has correct href'
	);
} );

QUnit.test( 'renders both main and suffix links when both are provided', ( assert ) => {
	const wrapper = mountComponent( {
		mainValue: 'Main Value',
		mainLink: 'https://example.com',
		suffixLabel: 'Suffix Label',
		suffixValue: 'Suffix Value',
		suffixLink: 'https://example.org'
	} );

	const links = wrapper.findAll( 'a' );
	assert.strictEqual( links.length, 2, 'Two links exist' );

	assert.strictEqual( links[ 0 ].text(), 'Main Value', 'Main value link has correct text' );
	assert.strictEqual(
		links[ 0 ].attributes( 'href' ),
		'https://example.com',
		'Main value link has correct href'
	);

	assert.strictEqual( links[ 1 ].text(), 'Suffix Value', 'Suffix value link has correct text' );
	assert.strictEqual(
		links[ 1 ].attributes( 'href' ),
		'https://example.org',
		'Suffix value link has correct href'
	);
} );

QUnit.test( 'handles numeric values correctly', ( assert ) => {
	const wrapper = mountComponent( {
		mainValue: 42,
		suffixLabel: 'Suffix Label',
		suffixValue: 123
	} );

	assert.true( wrapper.text().includes( '42' ), 'Numeric main value is displayed correctly' );
	assert.true( wrapper.text().includes( '123' ), 'Numeric suffix value is displayed correctly' );
} );
