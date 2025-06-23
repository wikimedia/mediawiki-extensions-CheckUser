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

		// Force permission configs
		mw.config.set( 'wgCheckUserCanViewCheckUserLog', true );
		mw.config.set( 'wgCheckUserCanBlock', true );
		mw.config.set( 'wgCheckUserGEUserImpactMaxEdits', 1000 );
		mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', false );
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
			activeWikis: {},
			recentLocalEdits: [],
			hasEditInLast60Days: false,
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

QUnit.test( 'renders correct number of InfoRowWithLinks components with all permissions', ( assert ) => {
	const wrapper = mountComponent();

	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	assert.strictEqual( infoRows.length, 6, 'Renders 6 InfoRowWithLinks components when all permissions are granted' );
} );

QUnit.test( 'renders correct number of InfoRowWithLinks components with no permissions', ( assert ) => {
	mw.config.set( 'wgCheckUserCanViewCheckUserLog', false );
	mw.config.set( 'wgCheckUserCanBlock', false );
	const wrapper = mountComponent();

	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	assert.strictEqual( infoRows.length, 4, 'Renders 4 InfoRowWithLinks components when no permissions are granted' );
} );

QUnit.test( 'passes correct props to blocks row when permission is granted', ( assert ) => {
	const wrapper = mountComponent();

	// Find the blocks row by its icon class
	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	const blocksRow = infoRows.find( ( row ) => row.props( 'iconClass' ).includes( 'ext-checkuser-userinfocard-icon-blocks' ) );

	assert.true( blocksRow !== undefined, 'Blocks row exists when permission is granted' );

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
		'/-1/Log/block?page=TestUser',
		'Blocks row has correct suffix link'
	);
} );

QUnit.test( 'does not render blocks row when permission is not granted', ( assert ) => {
	mw.config.set( 'wgCheckUserCanBlock', false );
	const wrapper = mountComponent();

	// Try to find the blocks row by its icon class
	const infoRows = wrapper.findAllComponents( { name: 'InfoRowWithLinks' } );
	const blocksRow = infoRows.find( ( row ) => row.props( 'iconClass' ).includes( 'ext-checkuser-userinfocard-icon-blocks' ) );

	assert.strictEqual( blocksRow, undefined, 'Blocks row does not exist when permission is not granted' );
} );

QUnit.test( 'renders user groups', ( assert ) => {
	const wrapper = mountComponent( { groups: 'Administrators, Check users' } );
	const groupsParagraph = wrapper.find( '.ext-checkuser-userinfocard-groups' );
	assert.true( groupsParagraph.exists() );

	const paragraphText = groupsParagraph.text();
	assert.true( paragraphText.includes( 'Administrators, Check users' ) );
} );

QUnit.test( 'does not render active wikis paragraph when activeWikis is empty', ( assert ) => {
	const wrapper = mountComponent();

	const activeWikisParagraph = wrapper.find( '.ext-checkuser-userinfocard-active-wikis' );
	assert.false( activeWikisParagraph.exists(), 'Active wikis paragraph does not exist when activeWikis is empty' );
} );

QUnit.test( 'renders active wikis paragraph when activeWikis is not empty', ( assert ) => {
	const activeWikisObj = {
		enwiki: 'https://en.wikipedia.org',
		dewiki: 'https://de.wikipedia.org',
		frwiki: 'https://fr.wikipedia.org'
	};
	const wrapper = mountComponent( { activeWikis: activeWikisObj } );

	const activeWikisParagraph = wrapper.find( '.ext-checkuser-userinfocard-active-wikis' );
	assert.true( activeWikisParagraph.exists(), 'Active wikis paragraph exists when activeWikis is not empty' );

	// Check that the paragraph contains the wiki IDs
	const paragraphText = activeWikisParagraph.text();
	assert.true( paragraphText.includes( 'enwiki' ), 'Paragraph includes enwiki' );
	assert.true( paragraphText.includes( 'dewiki' ), 'Paragraph includes dewiki' );
	assert.true( paragraphText.includes( 'frwiki' ), 'Paragraph includes frwiki' );
} );

QUnit.test( 'renders active wikis as links with correct URLs', ( assert ) => {
	const activeWikisObj = {
		enwiki: 'https://en.wikipedia.org',
		dewiki: 'https://de.wikipedia.org'
	};
	const wrapper = mountComponent( { activeWikis: activeWikisObj } );

	const wikiLinks = wrapper.findAll( '.ext-checkuser-userinfocard-active-wikis a' );
	assert.strictEqual( wikiLinks.length, 2, 'Renders correct number of wiki links' );

	// Check first link
	assert.strictEqual( wikiLinks[ 0 ].text(), 'enwiki', 'First link has correct text' );
	assert.strictEqual( wikiLinks[ 0 ].attributes( 'href' ), 'https://en.wikipedia.org', 'First link has correct URL' );

	// Check second link
	assert.strictEqual( wikiLinks[ 1 ].text(), 'dewiki', 'Second link has correct text' );
	assert.strictEqual( wikiLinks[ 1 ].attributes( 'href' ), 'https://de.wikipedia.org', 'Second link has correct URL' );
} );

QUnit.test( 'renders UserActivityChart when recentLocalEdits is not empty', ( assert ) => {
	const wrapper = mountComponent( {
		recentLocalEdits: sampleRecentEdits,
		hasEditInLast60Days: true
	} );

	const activityChart = wrapper.findComponent( { name: 'UserActivityChart' } );
	assert.true( activityChart.exists(), 'UserActivityChart exists when hasEditInLast60Days is true' );
	assert.strictEqual(
		activityChart.props( 'username' ),
		'TestUser',
		'UserActivityChart has correct username'
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

QUnit.test( 'does not render UserActivityChart when hasEditInLast60Days is false', ( assert ) => {
	const wrapper = mountComponent( {
		hasEditInLast60Days: false
	} );

	const activityChart = wrapper.findComponent( { name: 'UserActivityChart' } );
	assert.false( activityChart.exists(), 'UserActivityChart exists when hasEditInLast60Days is false' );
} );

QUnit.test( 'setup function returns correct values with all permissions', ( assert ) => {
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
		'infoRows has correct length with all permissions'
	);
} );

QUnit.test( 'setup function returns correct values with no permissions', ( assert ) => {
	mw.config.set( 'wgCheckUserCanViewCheckUserLog', false );
	mw.config.set( 'wgCheckUserCanBlock', false );
	const wrapper = mountComponent();

	assert.strictEqual(
		wrapper.vm.infoRows.length,
		4,
		'infoRows has correct length with no permissions'
	);
} );

QUnit.test( 'activeWikisList computed property transforms object to array correctly', ( assert ) => {
	const activeWikisObj = {
		enwiki: 'https://en.wikipedia.org',
		dewiki: 'https://de.wikipedia.org'
	};
	const wrapper = mountComponent( { activeWikis: activeWikisObj } );

	const activeWikisList = wrapper.vm.activeWikisList;
	assert.strictEqual( activeWikisList.length, 2, 'activeWikisList has correct length' );

	// Check that the array contains objects with wikiId and url properties
	assert.deepEqual(
		activeWikisList[ 0 ],
		{ wikiId: 'enwiki', url: 'https://en.wikipedia.org' },
		'First item in activeWikisList has correct structure'
	);

	assert.deepEqual(
		activeWikisList[ 1 ],
		{ wikiId: 'dewiki', url: 'https://de.wikipedia.org' },
		'Second item in activeWikisList has correct structure'
	);
} );

// TODO: T386440 - Fix the test and remove the skip
// This test fails when running in conjunction with the other test components in this folder.
// When running this test file alone, this test is passing.
QUnit.test.skip( 'logs an event when onWikiLinkClick is called', function ( assert ) {
	mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', true );
	this.sandbox.stub( mw.user, 'sessionId' ).returns( 'test-session-id' );
	this.sandbox.stub( mw.user, 'getId' ).returns( 123 );
	const submitInteractionStub = this.sandbox.stub();
	const instrumentStub = { submitInteraction: submitInteractionStub };
	this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );

	const activeWikisObj = {
		enwiki: 'https://en.wikipedia.org',
		dewiki: 'https://de.wikipedia.org'
	};

	const wrapper = mountComponent( { activeWikis: activeWikisObj } );
	wrapper.vm.onWikiLinkClick( 'enwiki' );

	assert.strictEqual( submitInteractionStub.callCount, 1, 'submitInteraction is called once' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'link_click',
		'First argument is "link_click"'
	);

	const interactionData = submitInteractionStub.firstCall.args[ 1 ];
	assert.strictEqual(
		interactionData.funnel_entry_token,
		'test-session-id',
		'Includes session token in interaction data'
	);
	assert.strictEqual(
		interactionData.action_subtype,
		'active_wiki',
		'Includes correct subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'card_body',
		'Includes correct source in interaction data'
	);
	assert.strictEqual(
		interactionData.action_context,
		'enwiki',
		'Includes correct action_context (wiki ID) in interaction data'
	);
} );
