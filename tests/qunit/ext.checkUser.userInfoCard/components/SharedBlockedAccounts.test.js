'use strict';

const { mount } = require( 'vue-test-utils' );
const SharedBlockedAccounts = require( 'ext.checkUser.userInfoCard/components/SharedBlockedAccounts.vue' );

const ENDPOINT = '/checkuser/v0/userinfo/sharedblocked/Test%20User?uselang=en';

let server;

QUnit.module( 'ext.checkUser.userInfoCard.SharedBlockedAccounts', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;

		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );
		this.sandbox.stub( mw.config, 'get' ).callsFake( ( key ) => {
			if ( key === 'wgUserLanguage' ) {
				return 'en';
			}
		} );
		this.sandbox.stub( mw.Title, 'makeTitle' ).callsFake( ( namespace, title ) => ( {
			getUrl: ( query ) => `/${ namespace }/${ title }?user=${ query.user }`
		} ) );
	},
	afterEach: function () {
		server.restore();
	}
} ) );

function mountComponent() {
	return mount( SharedBlockedAccounts, {
		propsData: { username: 'Test User' },
		global: {
			stubs: { InfoRowWithLinks: true },
			mocks: {
				$i18n: ( key ) => ( { text: () => '(' + key + ')' } )
			}
		}
	} );
}

/**
 * Makes the fake server answer requests to the endpoint, and fail the test on other requests.
 *
 * @param {Object} assert
 * @param {number} status
 * @param {Object} body
 * @return {{ calls: number }} Counter of the requests to the endpoint
 */
function respondToEndpoint( assert, status, body ) {
	const counter = { calls: 0 };
	server.respond( ( request ) => {
		if ( request.url.endsWith( ENDPOINT ) ) {
			counter.calls++;
			request.respond( status, { 'Content-Type': 'application/json' }, JSON.stringify( body ) );
		} else if ( request.url.includes( 'type=csrf' ) ) {
			request.respond( 200, { 'Content-Type': 'application/json' }, JSON.stringify( {
				query: { tokens: { csrftoken: 'token' } }
			} ) );
		} else {
			assert.true( false, 'Unexpected API request to ' + request.url );
		}
	} );
	return counter;
}

/**
 * Waits for the return value of a given function to be true, for at most 1 second.
 *
 * @param {Function} conditionCheck
 */
async function waitFor( conditionCheck ) {
	let tries = 0;
	while ( !conditionCheck() && tries < 20 ) {
		tries++;
		await new Promise( ( resolve ) => {
			setTimeout( () => resolve(), 50 );
		} );
	}
}

function getRowProps( wrapper ) {
	return wrapper.findAllComponents( { name: 'InfoRowWithLinks' } ).map( ( row ) => ( {
		messageKey: row.props( 'messageKey' ),
		mainValue: row.props( 'mainValue' ),
		mainLink: row.props( 'mainLink' ),
		mainLinkLogId: row.props( 'mainLinkLogId' ),
		iconClass: row.props( 'iconClass' )
	} ) );
}

QUnit.test( 'shows only the button before a click', ( assert ) => {
	const counter = respondToEndpoint( assert, 200, {} );
	const wrapper = mountComponent();

	const button = wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' );
	assert.true( button.exists(), 'Button is displayed' );
	assert.strictEqual(
		button.text(),
		'(checkuser-userinfocard-sharedblocked-button)',
		'Button has the correct label'
	);
	assert.strictEqual(
		wrapper.findAllComponents( { name: 'InfoRowWithLinks' } ).length,
		0,
		'No rows are displayed'
	);
	assert.strictEqual( counter.calls, 0, 'No request is sent before a click' );
} );

QUnit.test( 'shows the counts after a click', async ( assert ) => {
	const counter = respondToEndpoint( assert, 200, {
		ip: { count: 3, complete: true },
		browserVersion: { count: 0, complete: true }
	} );
	const wrapper = mountComponent();

	await wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).trigger( 'click' );
	await waitFor( () => wrapper.findAllComponents( { name: 'InfoRowWithLinks' } ).length > 0 );

	assert.strictEqual( counter.calls, 1, 'One request is sent' );
	assert.false(
		wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).exists(),
		'Button is removed'
	);
	assert.deepEqual(
		getRowProps( wrapper ),
		[
			{
				messageKey: 'checkuser-userinfocard-sharedblocked-ip',
				mainValue: '3',
				mainLink: '/-1/CheckUser?user=Test User',
				mainLinkLogId: 'shared_blocked_ip',
				iconClass: 'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks'
			},
			{
				messageKey: 'checkuser-userinfocard-sharedblocked-browser-version',
				mainValue: '0',
				mainLink: '',
				mainLinkLogId: 'shared_blocked_browser_version',
				iconClass: 'ext-checkuser-userinfocard-icon'
			}
		],
		'Rows show the counts, and only a non-zero count has a link to Special:CheckUser and the warning icon'
	);
} );

QUnit.test( 'marks incomplete counts', async ( assert ) => {
	respondToEndpoint( assert, 200, {
		ip: { count: 2, complete: true },
		browserVersion: { count: 5, complete: false }
	} );
	const wrapper = mountComponent();

	await wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).trigger( 'click' );
	await waitFor( () => wrapper.findAllComponents( { name: 'InfoRowWithLinks' } ).length > 0 );

	assert.deepEqual(
		getRowProps( wrapper ).map( ( row ) => row.mainValue ),
		[ '2', '(checkuser-userinfocard-sharedblocked-count-incomplete: 5)' ],
		'Only the incomplete count uses the incomplete message'
	);
} );

QUnit.test( 'shows the error message from the server', async ( assert ) => {
	respondToEndpoint( assert, 403, {
		messageTranslations: { en: 'Mocked error message' }
	} );
	const wrapper = mountComponent();

	await wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).trigger( 'click' );
	await waitFor( () => wrapper.text().includes( 'Mocked error message' ) );

	assert.true( wrapper.text().includes( 'Mocked error message' ), 'Server error message is displayed' );
	assert.false(
		wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).exists(),
		'Button is removed'
	);
} );

QUnit.test( 'shows a generic error message if the server sends none', async ( assert ) => {
	respondToEndpoint( assert, 500, {} );
	const wrapper = mountComponent();

	await wrapper.find( '.ext-checkuser-userinfocard-sharedblocked-button' ).trigger( 'click' );
	await waitFor( () => wrapper.text().includes( 'checkuser-userinfocard-error-generic' ) );

	assert.true(
		wrapper.text().includes( '(checkuser-userinfocard-error-generic)' ),
		'Generic error message is displayed'
	);
} );
