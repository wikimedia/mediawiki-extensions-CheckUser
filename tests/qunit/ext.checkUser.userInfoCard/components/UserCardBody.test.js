'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const UserCardBody = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserCardBody.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardBody', QUnit.newMwEnvironment( {
	beforeEach: function () {
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
	}
} ) );

// Sample data for testing
const sampleRecentEdits = [
	{ date: new Date( '2025-01-01' ), count: 5 },
	{ date: new Date( '2025-01-02' ), count: 3 },
	{ date: new Date( '2025-01-03' ), count: 7 }
];

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( UserCardBody, {
		propsData: {
			userId: '123',
			username: 'TestUser',
			joinedDate: '2020-01-01',
			joinedRelative: '5 years ago',
			activeBlocks: 2,
			pastBlocks: 3,
			globalEdits: 1000,
			localEdits: 500,
			localEditsReverted: 10,
			newArticles: 20,
			thanksReceived: 30,
			thanksSent: 15,
			checks: 5,
			lastChecked: '2024-12-31',
			activeWikis: [],
			recentLocalEdits: [],
			totalLocalEdits: 500,
			...props
		}
	} );
}

QUnit.test( 'renders correctly with required props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
	assert.true(
		wrapper.classes().includes( 'ext-checkuser-userinfocard-body' ),
		'Body has correct class'
	);
} );

QUnit.test( 'displays joined date information correctly', ( assert ) => {
	const wrapper = mountComponent();

	const joinedParagraph = wrapper.find( '.ext-checkuser-userinfocard-joined' );
	assert.true( joinedParagraph.exists(), 'Joined paragraph exists' );
	assert.strictEqual(
		joinedParagraph.text(),
		'checkuser-userinfocard-joined-label: 2020-01-01 (5 years ago)',
		'Joined paragraph displays correct information'
	);
} );

QUnit.test( 'renders correct number of InfoRowWithLinks components', ( assert ) => {
	const wrapper = mountComponent();

	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	assert.strictEqual( infoRows.length, 6, 'Renders 6 InfoRowWithLinks components' );
} );

QUnit.test( 'passes correct props to first InfoRowWithLinks component (blocks)', ( assert ) => {
	const wrapper = mountComponent();

	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	const blocksRow = infoRows[ 0 ];

	assert.strictEqual(
		blocksRow.props( 'mainLabel' ),
		'checkuser-userinfocard-active-blocks-row-main-label',
		'Blocks row has correct main label'
	);

	assert.strictEqual(
		blocksRow.props( 'mainValue' ),
		2,
		'Blocks row has correct main value'
	);

	assert.strictEqual(
		blocksRow.props( 'mainLink' ),
		'/-1/BlockList?wpTarget=TestUser&limit=50&wpFormIdentifier=blocklist',
		'Blocks row has correct main link'
	);

	assert.strictEqual(
		blocksRow.props( 'suffixLabel' ),
		'checkuser-userinfocard-active-blocks-row-suffix-label',
		'Blocks row has correct suffix label'
	);

	assert.strictEqual(
		blocksRow.props( 'suffixValue' ),
		3,
		'Blocks row has correct suffix value'
	);

	assert.strictEqual(
		blocksRow.props( 'suffixLink' ),
		'/-1/Log/block?user=TestUser',
		'Blocks row has correct suffix link'
	);
} );

QUnit.test( 'does not render active wikis paragraph when activeWikis is empty', ( assert ) => {
	const wrapper = mountComponent();

	const activeWikisParagraph = wrapper.find( '.ext-checkuser-userinfocard-active-wikis' );
	assert.false( activeWikisParagraph.exists(), 'Active wikis paragraph does not exist when activeWikis is empty' );
} );

QUnit.test( 'renders active wikis paragraph when activeWikis is not empty', ( assert ) => {
	const wrapper = mountComponent( { activeWikis: [ 'enwiki', 'dewiki', 'frwiki' ] } );

	const activeWikisParagraph = wrapper.find( '.ext-checkuser-userinfocard-active-wikis' );
	assert.true( activeWikisParagraph.exists(), 'Active wikis paragraph exists when activeWikis is not empty' );
	assert.strictEqual(
		activeWikisParagraph.text(),
		'checkuser-userinfocard-active-wikis-label: enwiki, dewiki, frwiki',
		'Active wikis paragraph displays correct information'
	);
} );

QUnit.test( 'renders UserActivityChart when recentLocalEdits is not empty', ( assert ) => {
	const wrapper = mountComponent( { recentLocalEdits: sampleRecentEdits } );

	const activityChart = wrapper.findComponent( { name: 'UserActivityChart' } );
	assert.true( activityChart.exists(), 'UserActivityChart exists when recentLocalEdits is not empty' );
	assert.strictEqual(
		activityChart.props( 'userId' ),
		'123',
		'UserActivityChart has correct userId'
	);
	assert.deepEqual(
		activityChart.props( 'recentLocalEdits' ),
		sampleRecentEdits,
		'UserActivityChart has correct recentLocalEdits'
	);
	assert.strictEqual(
		activityChart.props( 'totalLocalEdits' ),
		500,
		'UserActivityChart has correct totalLocalEdits'
	);
} );

QUnit.test( 'setup function returns correct values', ( assert ) => {
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.vm.joinedLabel,
		'checkuser-userinfocard-joined-label',
		'joinedLabel is set correctly'
	);

	assert.strictEqual(
		wrapper.vm.activeWikisLabel,
		'checkuser-userinfocard-active-wikis-label',
		'activeWikisLabel is set correctly'
	);

	assert.strictEqual(
		wrapper.vm.infoRows.length,
		6,
		'infoRows has correct length'
	);
} );
