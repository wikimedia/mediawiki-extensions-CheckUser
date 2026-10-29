'use strict';

const { nextTick } = require( 'vue' );
const { shallowMount, mount } = require( 'vue-test-utils' );
const InfoRowWithLinks = require( 'ext.checkUser.userInfoCard/components/InfoRowWithLinks.vue' );
const stubInstrument = require( '../stubInstrument.js' );

QUnit.module( 'ext.checkUser.userInfoCard.InfoRowWithLinks', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Mock mw.message to return a mock object with parse method
		this.sandbox.stub( mw, 'message' ).callsFake( ( key, ...args ) => ( {
			parse: () => {
				// Simple mock implementation that includes the arguments. Parameters are
				// either DOM nodes (main and suffix values) or plain strings.
				const format = ( arg ) => ( arg && arg.outerHTML ) || String( arg );
				if ( args.length === 0 ) {
					return key;
				}
				return `${ key } with ${ args.map( format ).join( ', ' ) }`;
			}
		} ) );

		mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', false );
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( InfoRowWithLinks, {
		propsData: {
			messageKey: 'checkuser-userinfocard-active-blocks',
			mainValue: 'Test Value',
			...props
		}
	} );
}

QUnit.test( 'formattedMessage computed property creates correct HTML for main value only', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-global-edits',
		mainValue: 'Test Value',
		mainLink: 'https://example.com'
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( 'checkuser-userinfocard-global-edits' ),
		'Formatted message includes message key'
	);
	assert.true(
		formattedMessage.includes( 'Test Value' ),
		'Formatted message includes main value'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.com"' ),
		'Formatted message includes main link'
	);
} );

QUnit.test( 'formattedMessage computed property creates correct HTML for main and suffix values', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-active-blocks',
		mainValue: 'Main Value',
		mainLink: 'https://example.com',
		suffixValue: 'Suffix Value',
		suffixLink: 'https://example.org'
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( 'checkuser-userinfocard-active-blocks' ),
		'Formatted message includes message key'
	);
	assert.true(
		formattedMessage.includes( 'Main Value' ),
		'Formatted message includes main value'
	);
	assert.true(
		formattedMessage.includes( 'Suffix Value' ),
		'Formatted message includes suffix value'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.com"' ),
		'Formatted message includes main link'
	);
	assert.true(
		formattedMessage.includes( 'href="https://example.org"' ),
		'Formatted message includes suffix link'
	);
} );

QUnit.test( 'creates span instead of link when no URL is provided', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-global-edits',
		mainValue: 'Test Value'
		// No mainLink provided
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( '<span>Test Value</span>' ),
		'Creates span when no link is provided'
	);
	assert.false(
		formattedMessage.includes( '<a' ),
		'Does not create link when no URL is provided'
	);
} );

QUnit.test( 'does not include suffix when suffixValue is empty, null, or undefined', ( assert ) => {
	const testCases = [ '', null, undefined ];

	testCases.forEach( ( suffixValue ) => {
		const wrapper = mountComponent( {
			messageKey: 'checkuser-userinfocard-active-blocks',
			mainValue: 'Main Value',
			suffixValue: suffixValue
		} );

		const formattedMessage = wrapper.vm.formattedMessage;
		// Should only have one argument (main) passed to mw.message
		assert.true(
			formattedMessage.includes( 'checkuser-userinfocard-active-blocks with' ),
			`Formatted message is created for suffixValue: ${ suffixValue }`
		);
	} );
} );

QUnit.test( 'passes message parameters for a label without a value', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-has-checkuser-data',
		mainValue: '',
		messageParams: [ '90', 'female' ]
	} );

	assert.strictEqual(
		wrapper.vm.formattedMessage,
		'checkuser-userinfocard-has-checkuser-data with 90, female',
		'Message parameters are passed to the message'
	);
} );

QUnit.test( 'handles numeric values correctly', ( assert ) => {
	const wrapper = mountComponent( {
		messageKey: 'checkuser-userinfocard-active-blocks',
		mainValue: 42,
		suffixValue: 123
	} );

	const formattedMessage = wrapper.vm.formattedMessage;
	assert.true(
		formattedMessage.includes( '42' ),
		'Numeric main value is converted to string'
	);
	assert.true(
		formattedMessage.includes( '123' ),
		'Numeric suffix value is converted to string'
	);
} );

QUnit.test( 'renders formatted message using v-html', ( assert ) => {
	const wrapper = mount( InfoRowWithLinks, {
		propsData: {
			messageKey: 'checkuser-userinfocard-global-edits',
			mainValue: 'Test Value'
		}
	} );

	const span = wrapper.find( 'span' );
	assert.true( span.exists(), 'Span element exists for v-html content' );
	// The actual HTML content is set via v-html, so we check the computed property
	assert.true(
		wrapper.vm.formattedMessage.includes( 'Test Value' ),
		'Formatted message contains expected content'
	);
} );

/**
 * Mount the component in the document, because the click handlers are found by element ID.
 *
 * @param {Object} props
 * @return {Promise<Object>} The wrapper, after the click handlers are attached
 */
function mountComponentInDocument( props ) {
	const container = document.createElement( 'div' );
	// Do not follow the links when the test clicks them
	container.addEventListener( 'click', ( event ) => event.preventDefault() );
	document.getElementById( 'qunit-fixture' ).appendChild( container );

	const wrapper = mount( InfoRowWithLinks, {
		propsData: {
			messageKey: 'checkuser-userinfocard-active-blocks',
			username: 'TestUser',
			...props
		},
		attachTo: container
	} );
	return nextTick().then( () => wrapper );
}

/**
 * @param {Object} assert
 * @param {Object} sendStub
 * @param {string} expectedSubType
 */
function assertLinkClickEvent( assert, sendStub, expectedSubType ) {
	assert.strictEqual( sendStub.callCount, 1, 'send is called once' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'link_click',
		'First argument is "link_click"'
	);
	/* eslint-disable camelcase */
	assert.deepEqual(
		sendStub.firstCall.args[ 1 ],
		{
			funnel_entry_token: 'test-session-id',
			action_subtype: expectedSubType,
			action_source: 'card_body',
			action_context: JSON.stringify( { username: 'TestUser' } )
		},
		'Interaction data is correct'
	);
	/* eslint-enable camelcase */
}

QUnit.test( 'logs an event when the main link is clicked', function ( assert ) {
	const sendStub = stubInstrument( this.sandbox );

	return mountComponentInDocument( {
		mainValue: 'Test Value',
		mainLink: 'https://example.com',
		mainLinkLogId: 'main_link_id'
	} ).then( ( wrapper ) => {
		wrapper.find( '#info-row-main_link_id' ).trigger( 'click' );

		assertLinkClickEvent( assert, sendStub, 'main_link_id' );
		wrapper.unmount();
	} );
} );
