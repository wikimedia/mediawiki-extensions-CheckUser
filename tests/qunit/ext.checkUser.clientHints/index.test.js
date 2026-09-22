'use strict';

const clientHints = require( 'ext.checkUser.clientHints/index.js' );

QUnit.module( 'ext.checkUser.clientHints', QUnit.newMwEnvironment( {} ) );

// The test page has no config, so tell init() to register the edit listeners.
function setUpEditListeners() {
	mw.config.set( 'wgCheckUserClientHintsInEditRequest', true );
	clientHints.init( {
		userAgentData: { getHighEntropyValues: () => $.Deferred().resolve( {} ) }
	} );
}

QUnit.test( 'Client hints code is setup if navigator.userAgentData.getHighEntropyValues() is available', function ( assert ) {
	const responseMock = {
		platform: 'macOS'
	};
	const navigatorData = {
		userAgentData: {
			getHighEntropyValues: this.sandbox.stub().returns(
				$.Deferred().resolve( responseMock )
			)
		}
	};
	assert.true( clientHints.init( navigatorData ) );
} );

QUnit.test( 'Client hints code is not setup if navigator.userAgentData is available but navigator.userAgentData.getHighEntropyValues() is not available', ( assert ) => {
	const navigatorData = { userAgentData: {} };
	assert.false( clientHints.init( navigatorData ) );
} );
QUnit.test( 'Client hints code is not setup if navigator.userAgentData is not defined', ( assert ) => {
	const navigatorData = {};
	assert.false( clientHints.init( navigatorData ) );
} );

QUnit.test( 'A MobileFrontend source editor save gets the client hints field', ( assert ) => {
	setUpEditListeners();
	const payload = JSON.stringify( { architecture: 'arm', model: '红米' } );
	clientHints.setCollectedClientHints( payload );

	const saveOptions = {};
	mw.hook( 'mobileFrontend.sourceEditor.saveBegin' ).fire( { options: saveOptions } );

	assert.strictEqual( saveOptions.checkuserclienthints, payload );
} );

QUnit.test.each( 'A save through an extensible API request gets the client hints field', {
	DiscussionTools: 'discussionToolsExtendSave',
	'ProofreadPage EditInSequence': 'ext.proofreadpage.editinsequence-extend-save'
}, ( assert, hookName ) => {
	setUpEditListeners();
	clientHints.setCollectedClientHints( null );

	return clientHints.startCollection(
		() => Promise.resolve( { architecture: 'arm' } )
	).then( () => {
		const data = { params: {}, promise: Promise.resolve() };
		mw.hook( hookName ).fire( data );

		return data.promise.then( () => {
			assert.strictEqual(
				data.params.checkuserclienthints,
				JSON.stringify( { architecture: 'arm' } )
			);
		} );
	} );
} );

QUnit.test( 'No field is added before the browser has answered', ( assert ) => {
	setUpEditListeners();
	clientHints.setCollectedClientHints( null );

	const saveOptions = {};
	mw.hook( 'mobileFrontend.sourceEditor.saveBegin' ).fire( { options: saveOptions } );

	assert.strictEqual( saveOptions.checkuserclienthints, undefined );
} );

QUnit.test( 'The wikitext editor form gets a hidden client hints field', ( assert ) => {
	const payload = JSON.stringify( { architecture: 'arm' } );
	clientHints.setCollectedClientHints( payload );
	const $editForm = $( '<form>' );

	clientHints.addClientHintsToEditForm( $editForm );
	clientHints.addClientHintsToEditForm( $editForm );

	const $fields = $editForm.find( '[name="checkuserclienthints"]' );
	assert.strictEqual( $fields.length, 1, 'The field is added once' );
	assert.strictEqual( $fields.val(), payload );
} );

QUnit.test( 'No hidden field is added before the browser has answered', ( assert ) => {
	clientHints.setCollectedClientHints( null );
	const $editForm = $( '<form>' );

	clientHints.addClientHintsToEditForm( $editForm );

	assert.strictEqual( $editForm.find( '[name="checkuserclienthints"]' ).length, 0 );
} );

QUnit.test( 'Nothing throws when there is no edit form', ( assert ) => {
	// Core fires wikipage.editform even when the page has no form.
	assert.expect( 0 );
	clientHints.setCollectedClientHints( JSON.stringify( { architecture: 'arm' } ) );

	clientHints.addClientHintsToEditForm( $( [] ) );
} );

QUnit.test( 'Collection runs once per page view', ( assert ) => {
	clientHints.setCollectedClientHints( null );

	let calls = 0;
	const collect = () => {
		calls++;
		return Promise.resolve( { architecture: 'arm' } );
	};

	return clientHints.startCollection( collect )
		.then( () => clientHints.startCollection( collect ) )
		.then( () => {
			assert.strictEqual( calls, 1, 'The browser is asked once' );
			const $editForm = $( '<form>' );
			clientHints.addClientHintsToEditForm( $editForm );
			assert.strictEqual(
				$editForm.find( '[name="checkuserclienthints"]' ).val(),
				JSON.stringify( { architecture: 'arm' } )
			);
		} );
} );

QUnit.test( 'An edit goes ahead if the browser refuses during collection', ( assert ) => {
	setUpEditListeners();
	clientHints.setCollectedClientHints( null );

	return clientHints.startCollection(
		() => Promise.reject( new Error( 'NotAllowedError' ) )
	).then( () => {
		const saveOptions = {};
		mw.hook( 'mobileFrontend.sourceEditor.saveBegin' ).fire( { options: saveOptions } );
		assert.strictEqual(
			saveOptions.checkuserclienthints, undefined, 'Nothing is added and nothing throws'
		);
	} );
} );
