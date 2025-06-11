'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const UserCardMenu = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserCardMenu.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardMenu', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;

		// Stub mw.msg
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key ) => key );

		// Stub mw.Title.makeTitle
		this.sandbox.stub( mw.Title, 'makeTitle' ).callsFake( ( namespace, title ) => ( {
			getUrl: ( query ) => {
				let url = `/${ namespace }/${ title }`;
				if ( query ) {
					const params = Object.entries( query )
						.map( ( [ key, value ] ) => `${ key }=${ value }` )
						.join( '&' );
					url += `?${ params }`;
				}
				return url;
			},
			getPrefixedText: () => {
				const nsText = namespace === 0 ? '' : `Namespace${ namespace }:`;
				return `${ nsText }${ title }`;
			}
		} ) );
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( UserCardMenu, {
		propsData: {
			userId: '123',
			username: 'TestUser',
			...props
		}
	} );
}

QUnit.test( 'renders correctly with default props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
} );

QUnit.test( 'computes menu items correctly', ( assert ) => {
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual( menuItems.length, 7, 'Menu has 7 items' );

	assert.strictEqual(
		menuItems[ 0 ].value,
		'view-contributions',
		'First item is view-contributions'
	);
	assert.strictEqual(
		menuItems[ 0 ].label,
		'checkuser-userinfocard-menu-view-contributions',
		'Contributions label is correct'
	);
	assert.strictEqual(
		menuItems[ 0 ].link,
		'/-1/Contributions/TestUser',
		'Contributions link is correct'
	);

	assert.strictEqual(
		menuItems[ 1 ].value,
		'view-global-account',
		'Second item is view-global-account'
	);
	assert.strictEqual(
		menuItems[ 1 ].label,
		'checkuser-userinfocard-menu-view-global-account',
		'Global account label is correct'
	);
	assert.strictEqual(
		menuItems[ 1 ].link,
		'/-1/CentralAuth/TestUser',
		'Global account link is correct'
	);

	assert.strictEqual( menuItems[ 2 ].value, 'toggle-watchlist', 'Third item is toggle-watchlist' );
	assert.strictEqual(
		menuItems[ 2 ].label,
		'checkuser-userinfocard-menu-add-to-watchlist',
		'Watchlist label is correct'
	);

	assert.strictEqual( menuItems[ 3 ].value, 'check-ip', 'Fourth item is check-ip' );
	assert.strictEqual(
		menuItems[ 3 ].label,
		'checkuser-userinfocard-menu-check-ip',
		'Check IP label is correct'
	);
	assert.strictEqual(
		menuItems[ 3 ].link,
		'/-1/CheckUser?user=TestUser',
		'Check IP link is correct'
	);

	assert.strictEqual( menuItems[ 4 ].value, 'block-user', 'Fifth item is block-user' );
	assert.strictEqual(
		menuItems[ 4 ].label,
		'checkuser-userinfocard-menu-block-user',
		'Block user label is correct'
	);
	assert.strictEqual( menuItems[ 4 ].link, '/-1/Block/TestUser', 'Block user link is correct' );

	assert.strictEqual( menuItems[ 5 ].value, 'provide-feedback', 'Sixth item is provide-feedback' );
	assert.strictEqual(
		menuItems[ 5 ].label,
		'checkuser-userinfocard-menu-provide-feedback',
		'Provide feedback label is correct'
	);
	assert.strictEqual(
		menuItems[ 5 ].link,
		'https://www.mediawiki.org/w/index.php?title=Help_talk:Extension:CheckUser',
		'Provide feedback link is correct'
	);

	assert.strictEqual( menuItems[ 6 ].value, 'turn-off', 'Seventh item is turn-off' );
	assert.strictEqual(
		menuItems[ 6 ].label,
		'checkuser-userinfocard-menu-turn-off',
		'Turn off label is correct'
	);
	assert.strictEqual(
		menuItems[ 6 ].link,
		'/-1/Special:Preferences#mw-prefsection-rendering-advancedrendering',
		'Turn off link is correct'
	);
} );

QUnit.test( 'watchlist label changes based on initial state', ( assert ) => {
	const wrapper = mountComponent( { userPageWatched: true } );
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual(
		menuItems[ 2 ].label,
		'checkuser-userinfocard-menu-remove-from-watchlist',
		'Watchlist label is correct'
	);
} );
