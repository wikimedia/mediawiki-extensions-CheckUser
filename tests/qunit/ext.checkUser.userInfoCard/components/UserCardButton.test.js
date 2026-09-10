'use strict';

const { nextTick } = require( 'vue' );
const { mount } = require( 'vue-test-utils' );
const UserCardButton = require( 'ext.checkUser.userInfoCard/components/UserCardButton.vue' );
const {
	cdxIconSuggestedInvestigations,
	cdxIconUserAvatar,
	cdxIconUserBlocked,
	cdxIconUserTemporary
} = require( './icons.json' );

let server;
let sandbox;

QUnit.module( 'ext.checkUser.userInfoCard.UserCardButton', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;
		sandbox = this.sandbox;
	},
	afterEach: function () {
		server.restore();
	}
} ) );

/**
 * Tells the code under test whether the viewer wants the card.
 *
 * QUnit.newMwEnvironment does not restore mw.user.options, so stub it instead of setting
 * the preference.
 *
 * @param {boolean} wanted
 */
function setCardEnabled( wanted ) {
	sandbox.stub( mw.user, 'isNamed' ).returns( wanted );
	sandbox.stub( mw.user.options, 'get' )
		.withArgs( 'checkuser-userinfocard-enable' ).returns( wanted );
}

/**
 * Answers icon requests with the variant of an icon for each requested user.
 *
 * Call this before mounting, or the request never settles and the button stays hidden.
 *
 * @param {Object} assert
 * @param {Object<string,string>} iconsByUser Icon to answer for a user. Users which are
 *   absent from this map get 'userAvatar'.
 * @param {number} [status=200] Status code to answer with
 * @return {Array<Object>} Bodies of the requests, in the order in which they arrived
 */
function respondToIconRequests( assert, iconsByUser, status = 200 ) {
	const requestBodies = [];

	sandbox.stub( mw.Rest.prototype, 'post' ).callsFake( ( path, body ) => {
		// The icons endpoint needs no CSRF token, so a request for one, or any other
		// request, means that the component does more than it should.
		assert.strictEqual(
			path,
			'/checkuser/v0/userinfo/icons',
			'The component should only ask for icons'
		);
		requestBodies.push( body );

		if ( status !== 200 ) {
			return $.Deferred().reject( 'error-code', { xhr: { status: status } } ).promise();
		}

		const icons = {};
		body.users.forEach( ( username ) => {
			icons[ username ] = iconsByUser[ username ] || 'userAvatar';
		} );
		return $.Deferred().resolve( { icons: icons } ).promise();
	} );

	return requestBodies;
}

/**
 * Waits until the component handled the answers to its icon requests.
 *
 * mw.Rest answers with a jQuery promise, and jQuery calls its handlers in a later task.
 * The first task lets the answer out. The second task lets the component receive it.
 */
async function flushIconRequests() {
	await new Promise( ( resolve ) => {
		setTimeout( resolve );
	} );
	await new Promise( ( resolve ) => {
		setTimeout( resolve );
	} );
	await nextTick();
}

function mountComponent( username = 'TestUser' ) {
	return mount( UserCardButton, { props: { username } } );
}

QUnit.test( 'button label contains username', async ( assert ) => {
	setCardEnabled( true );
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	assert.true( wrapper.getComponent( { name: 'CdxButton' } )
		.attributes( 'aria-label' )
		.includes( 'TestUser' ) );
} );

QUnit.test( 'button click toggles popover', async ( assert ) => {
	setCardEnabled( true );
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	let value = false;
	wrapper.vm.togglePopover = () => {
		value = !value;
		assert.step( value ? 'opened' : 'closed' );
	};

	const button = wrapper.getComponent( { name: 'CdxButton' } );
	button.trigger( 'click' );
	button.trigger( 'click' );

	assert.verifySteps( [ 'opened', 'closed' ] );
} );

QUnit.test( 'icon matches user state', async ( assert ) => {
	setCardEnabled( true );
	const wrapper = mountComponent();
	await wrapper.setData( { ready: true } );

	const icon = wrapper.getComponent( { name: 'CdxIcon' } );
	assert.strictEqual( icon.props( 'icon' ), cdxIconUserAvatar, 'normal user' );

	await wrapper.setProps( { username: '~2026-1' } );
	assert.propEqual( icon.props( 'icon' ), cdxIconUserTemporary, 'temporary user' );

	await wrapper.setData( { iconVariant: 'userBlocked' } );
	assert.propEqual( icon.props( 'icon' ), cdxIconUserBlocked, 'blocked user' );
} );

QUnit.test( 'icon comes from the server', async ( assert ) => {
	setCardEnabled( true );
	const requestBodies = respondToIconRequests( assert, {
		TestUser: 'suggestedInvestigations'
	} );

	const wrapper = mountComponent();
	await flushIconRequests();

	assert.deepEqual(
		requestBodies,
		[ { users: [ 'TestUser' ] } ],
		'The component should ask about the user which it shows'
	);
	assert.true(
		wrapper.findComponent( { name: 'CdxButton' } ).exists(),
		'The button should be shown once the server answered'
	);
	assert.propEqual(
		wrapper.getComponent( { name: 'CdxIcon' } ).props( 'icon' ),
		cdxIconSuggestedInvestigations,
		'The icon should be the one which the server named'
	);
} );

QUnit.test( 'the icon from the server is recorded for later buttons', async ( assert ) => {
	setCardEnabled( true );
	const requestBodies = respondToIconRequests( assert, { TestUser: 'userBlocked' } );

	mountComponent();
	await flushIconRequests();

	assert.deepEqual(
		mw.config.get( 'wgCheckUserUserInfoCardCustomIcons' ),
		{ TestUser: 'userBlocked' },
		'The icon which the server named should be recorded'
	);

	const secondWrapper = mountComponent();
	await flushIconRequests();

	assert.deepEqual(
		requestBodies,
		[ { users: [ 'TestUser' ] } ],
		'A later button should reuse the icon instead of asking again'
	);
	assert.propEqual(
		secondWrapper.getComponent( { name: 'CdxIcon' } ).props( 'icon' ),
		cdxIconUserBlocked,
		'The later button should show the icon which the server named'
	);
} );

QUnit.test( 'an icon which is already known needs no request', async ( assert ) => {
	setCardEnabled( true );
	const requestBodies = respondToIconRequests( assert, {} );
	mw.config.set( 'wgCheckUserUserInfoCardCustomIcons', {
		TestUser: 'suggestedInvestigations'
	} );

	const wrapper = mountComponent();
	await flushIconRequests();

	assert.deepEqual( requestBodies, [], 'No request should be made' );
	assert.propEqual(
		wrapper.getComponent( { name: 'CdxIcon' } ).props( 'icon' ),
		cdxIconSuggestedInvestigations,
		'The icon should be the one which the server named before'
	);
} );

QUnit.test( 'server error keeps the icon which the name implies', async ( assert ) => {
	setCardEnabled( true );
	respondToIconRequests( assert, {}, 500 );

	const wrapper = mountComponent( '~2026-1' );
	await flushIconRequests();

	assert.true(
		wrapper.findComponent( { name: 'CdxButton' } ).exists(),
		'The button should be shown even if the request failed'
	);
	assert.propEqual(
		wrapper.getComponent( { name: 'CdxIcon' } ).props( 'icon' ),
		cdxIconUserTemporary,
		'The icon should be the one which the name implies'
	);
} );

QUnit.test( 'no button and no request for a viewer who turned the card off', async ( assert ) => {
	setCardEnabled( false );
	const requestBodies = respondToIconRequests( assert, {} );

	// The check of the preference happens before the request, so there is nothing to wait for.
	const wrapper = mountComponent();
	await nextTick();

	assert.deepEqual( requestBodies, [], 'No request should be made' );
	assert.false(
		wrapper.findComponent( { name: 'CdxButton' } ).exists(),
		'No button should be shown'
	);
} );

QUnit.test( 'icon follows a change of the username', async ( assert ) => {
	setCardEnabled( true );
	const requestBodies = respondToIconRequests( assert, { OtherUser: 'userBlocked' } );

	const wrapper = mountComponent();
	await flushIconRequests();

	await wrapper.setProps( { username: 'OtherUser' } );
	await flushIconRequests();

	assert.deepEqual(
		requestBodies,
		[ { users: [ 'TestUser' ] }, { users: [ 'OtherUser' ] } ],
		'The component should ask about the new user'
	);
	assert.propEqual(
		wrapper.getComponent( { name: 'CdxIcon' } ).props( 'icon' ),
		cdxIconUserBlocked,
		'The icon should be the one which the server named for the new user'
	);
} );
