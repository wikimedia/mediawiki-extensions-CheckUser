'use strict';

const { nextTick } = require( 'vue' );
const { mount } = require( 'vue-test-utils' );
const App = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/App.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.App', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	const mounted = mount( App, { propsData: props } );
	mounted.vm.setUserInfo( 'a username' );
	return mounted;
}

QUnit.test( 'renders closed by default', ( assert ) => {
	const wrapper = mountComponent();

	const popover = wrapper.findComponent( { name: 'CdxPopover' } );
	assert.true( popover.exists(), 'Popover component is rendered' );
	assert.strictEqual( popover.props( 'open' ), false, 'Popover is closed by default' );

	const userCard = wrapper.findComponent( { name: 'UserCardView' } );
	assert.false( userCard.exists(), 'UserCardView is not rendered when closed' );
} );

QUnit.test( 'open method opens the popover with the correct trigger', ( assert ) => {
	const wrapper = mountComponent();
	const triggerElement = document.createElement( 'button' );

	wrapper.vm.open( triggerElement );

	nextTick( () => {
		const popover = wrapper.findComponent( { name: 'CdxPopover' } );
		assert.strictEqual( popover.props( 'open' ), true, 'Popover is open after calling open()' );
		assert.strictEqual(
			popover.props( 'anchor' ),
			triggerElement,
			'Popover anchor is set to the trigger element'
		);

		const userCard = wrapper.findComponent( { name: 'UserCardView' } );
		assert.true( userCard.exists(), 'UserCardView is rendered when open' );
	} );
} );

QUnit.test( 'setUserInfo method sets the user ID and wiki ID', ( assert ) => {
	const wrapper = mountComponent();

	wrapper.vm.setUserInfo( 'username' );

	assert.strictEqual(
		wrapper.vm.username,
		'username',
		'Username is set correctly'
	);
} );

QUnit.test( 'componentKey is based on username', ( assert ) => {
	const wrapper = mountComponent();

	wrapper.vm.setUserInfo( null );

	assert.strictEqual(
		wrapper.vm.componentKey,
		'default',
		'Component key is "default" when username is null'
	);

	wrapper.vm.setUserInfo( 'username' );

	assert.strictEqual(
		wrapper.vm.componentKey,
		'1umww5y',
		'Component key is based on the username when it is not null'
	);
} );

QUnit.test( 'container div is rendered only when popover is open', ( assert ) => {
	const wrapper = mountComponent();

	let container = wrapper.find( '.ext-checkuser-userinfocard-container' );
	assert.false( container.exists(), 'Container div is not rendered when closed' );

	wrapper.vm.open( document.createElement( 'button' ) );

	nextTick( () => {
		container = wrapper.find( '.ext-checkuser-userinfocard-container' );
		assert.true( container.exists(), 'Container div is rendered when open' );
	} );
} );

QUnit.test( 'close method closes the popover', ( assert ) => {
	const wrapper = mountComponent();
	const triggerElement = document.createElement( 'button' );

	wrapper.vm.open( triggerElement );

	nextTick( () => {
		const popoverBefore = wrapper.findComponent( { name: 'CdxPopover' } );
		assert.strictEqual( popoverBefore.props( 'open' ), true, 'Popover is open before calling close()' );

		// Now close it
		wrapper.vm.close();

		nextTick( () => {
			const popoverAfter = wrapper.findComponent( { name: 'CdxPopover' } );
			assert.strictEqual( popoverAfter.props( 'open' ), false, 'Popover is closed after calling close()' );
			assert.strictEqual( wrapper.vm.currentTrigger, null, 'Current trigger is reset after closing' );
		} );
	} );
} );

QUnit.test( 'isPopoverOpen method returns the correct state', ( assert ) => {
	const wrapper = mountComponent();
	const triggerElement = document.createElement( 'button' );

	// Initially closed
	assert.strictEqual( wrapper.vm.isPopoverOpen(), false, 'isPopoverOpen returns false when popover is closed' );

	wrapper.vm.open( triggerElement );

	nextTick( () => {
		assert.strictEqual( wrapper.vm.isPopoverOpen(), true, 'isPopoverOpen returns true when popover is open' );

		// Close the popover
		wrapper.vm.close();

		nextTick( () => {
			assert.strictEqual( wrapper.vm.isPopoverOpen(), false, 'isPopoverOpen returns false after closing' );
		} );
	} );
} );

QUnit.test( 'exposed methods are available', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( typeof wrapper.vm.open === 'function', 'open method is exposed' );
	assert.true( typeof wrapper.vm.close === 'function', 'close method is exposed' );
	assert.true( typeof wrapper.vm.setUserInfo === 'function', 'setUserInfo method is exposed' );
	assert.true( typeof wrapper.vm.isPopoverOpen === 'function', 'isPopoverOpen method is exposed' );
} );
