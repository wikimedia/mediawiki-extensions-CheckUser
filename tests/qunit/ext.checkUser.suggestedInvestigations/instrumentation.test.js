'use strict';

const instrumentation = require( 'ext.checkUser.suggestedInvestigations/instrumentation.js' );

// Store stubs for use in arrow functions
let sendStub;

QUnit.module( 'ext.checkUser.suggestedInvestigations.instrumentation', QUnit.newMwEnvironment( {
	beforeEach: function () {
		// Create stub for the instrument
		sendStub = this.sandbox.stub();
		const setSchemaStub = this.sandbox.stub().returns( { send: sendStub } );
		const instrumentStub = { send: sendStub, setSchema: setSchemaStub };

		if ( mw.testKitchen ) {
			this.sandbox.stub( mw.testKitchen, 'getInstrument' ).returns( instrumentStub );
		} else {
			const getInstrumentStub = this.sandbox.stub().returns( instrumentStub );
			// Stub missing mw.testKitchen
			delete mw.testKitchen;
			this.sandbox.define( mw, 'testKitchen', { getInstrument: getInstrumentStub } );
		}
	}
} ) );

QUnit.test( 'Instruments clicks on "SI cases" contributions special page toollink', ( assert ) => {
	mw.config.set( 'wgRelevantUserName', 'TestUser1234' );

	// Set up the DOM to include the "SI cases" link
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-contributions-link-suggested-investigations' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $link );

	// Execute the code under test
	instrumentation();

	// Simulate a click on the link
	$link.trigger( 'click' );

	// Verify send is called with correct parameters
	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'contributions_toollink_click',
		'Passes correct action to send'
	);
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_context,
		'TestUser1234',
		'Includes context in interaction data'
	);
} );

QUnit.test( 'Instruments clicks on "SI cases" links in CheckUser Get Users results', ( assert ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $container = $( '<div>' ).addClass( 'mw-checkuser-get-users-results' );
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-checkuser-si-cases-link' )
		.text( 'SI cases' );
	$container.append( $link );
	$qunitFixture.append( $container );

	instrumentation();
	$link.trigger( 'mousedown' );

	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 0 ],
		'checkuser_si_cases_link_click',
		'Passes correct action to send'
	);
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_context,
		'special_checkuser_get_users',
		'Includes get_users context in interaction data'
	);
} );

QUnit.test.each(
	'Instruments clicks on links in the main table',
	{
		'User page': [ 'mw-userlink', 'user-page' ],
		Contribs: [ 'mw-usertoollinks-contribs', 'contributions' ],
		Block: [ 'mw-usertoollinks-block', 'block' ],
		'Past checks': [ 'mw-usertoollinks-past-checks', 'past-checks' ],
		'Check user': [ 'mw-usertoollinks-checkuser', 'check-user' ],
		Investigate: [ 'mw-checkuser-suggestedinvestigations-investigate-action', 'investigate' ],
		'Past cases': [ 'mw-usertoollinks-suggestedinvestigations-cases', 'past-cases' ]
	},
	( assert, [ linkClass, expectedSubtype ] ) => {
		mw.config.set( 'wgPageName', 'Special:SuggestedInvestigations' );

		// eslint-disable-next-line no-jquery/no-global-selector
		const $qunitFixture = $( '#qunit-fixture' );
		const $table = $( '<table>' )
			.addClass( 'ext-checkuser-suggestedinvestigations-table' )
			.attr( 'data-username', 'Username 123' );
		const $link = $( '<a>' ).attr( 'href', '#' );

		$link.addClass( linkClass );
		$table.append( $link );
		$qunitFixture.append( $table );

		instrumentation();
		$link.trigger( 'click' );

		assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
		assert.strictEqual(
			sendStub.firstCall.args[ 0 ],
			'link_click',
			'Passes the link_click action to send'
		);
		assert.strictEqual(
			sendStub.firstCall.args[ 1 ].action_subtype,
			expectedSubtype,
			'Reports the link type as action_subtype'
		);
		assert.strictEqual(
			sendStub.firstCall.args[ 1 ].action_source,
			'main',
			'Reports the main view as action_source'
		);
		assert.strictEqual(
			sendStub.firstCall.args[ 1 ].action_context,
			'Username 123',
			'Reports the relevant user as action_context'
		);
	}
);

QUnit.test( 'Reports the detail view as the source for main-table link clicks', ( assert ) => {
	mw.config.set( 'wgPageName', 'Special:SuggestedInvestigations/detail/2a' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $table = $( '<table>' )
		.addClass( 'ext-checkuser-suggestedinvestigations-table ext-checkuser-suggestedinvestigations-table-main' );
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-userlink' );
	$table.append( $link );
	$qunitFixture.append( $table );

	instrumentation();
	$link.trigger( 'click' );

	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_source,
		'details',
		'Reports the detail view as action_source'
	);
} );

QUnit.test( 'Reports the detail view as the source for additional table link clicks', ( assert ) => {
	mw.config.set( 'wgPageName', 'Special:SuggestedInvestigations/detail/2a' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $table = $( '<table>' )
		.addClass( 'ext-checkuser-suggestedinvestigations-table' );
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-userlink' );
	$table.append( $link );
	$qunitFixture.append( $table );

	instrumentation();
	$link.trigger( 'click' );

	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_source,
		'details_sub',
		'Reports the detail_sub view as action_source'
	);
} );

QUnit.test( 'Does not instrument link clicks outside the main table', ( assert ) => {
	mw.config.set( 'wgPageName', 'Special:SuggestedInvestigations' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	// A user link that is not inside the main table should be ignored.
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.addClass( 'mw-userlink' );
	$qunitFixture.append( $link );

	instrumentation();
	$link.trigger( 'click' );

	assert.strictEqual( sendStub.callCount, 0, 'Does not call send' );
} );

QUnit.test( 'Reports the custom instrumentation element with proper subtype', ( assert ) => {
	mw.config.set( 'wgPageName', 'Special:SuggestedInvestigations' );

	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const $table = $( '<table>' )
		.addClass( 'ext-checkuser-suggestedinvestigations-table' );
	const $link = $( '<a>' )
		.attr( 'href', '#' )
		.attr( 'data-subtype', 'lorem-ipsum' )
		.addClass( 'mw-checkuser-suggestedinvestigations-custom-instrument' );
	$table.append( $link );
	$qunitFixture.append( $table );

	instrumentation();
	$link.trigger( 'click' );

	assert.strictEqual( sendStub.callCount, 1, 'Calls send' );
	assert.strictEqual(
		sendStub.firstCall.args[ 1 ].action_subtype,
		'lorem-ipsum',
		'Reports lorem-ipsum as action_subtype'
	);
} );
