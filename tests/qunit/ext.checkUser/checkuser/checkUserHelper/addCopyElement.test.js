'use strict';

const addCopyElement = require( 'ext.checkUser/checkuser/checkUserHelper/addCopyElement.js' );

QUnit.module( 'ext.checkUser.checkuser.checkUserHelper.addCopyElement', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.notifyStub = this.sandbox.stub( mw, 'notify' );
		// `navigator.clipboard` is only exposed in secure contexts and cannot
		// be stubbed in place, so stub it in place for the duration of the test.
		this.writeText = this.sandbox.stub();
		Object.defineProperty( navigator, 'clipboard', {
			value: { writeText: this.writeText },
			configurable: true
		} );
	},
	afterEach: function () {
		delete navigator.clipboard;
	}
} ) );

QUnit.test( 'writeText is undefined', function ( assert ) {
	Object.defineProperty( navigator, 'clipboard', {
		value: { writeText: undefined },
		configurable: true
	} );

	const qunitFixture = document.getElementById( 'qunit-fixture' );
	const div = document.createElement( 'div' );
	div.classList.add( 'mw-checkuser-helper-table' );
	qunitFixture.appendChild( div );

	addCopyElement( 'test text' );

	assert.strictEqual(
		qunitFixture.querySelector( '.mw-checkuser-helper-copy-button' ),
		null,
		'No copy button is added when writeText is undefined'
	);
	assert.true( this.notifyStub.notCalled, 'mw.notify is not called when writeText is undefined' );
} );

QUnit.test( 'writeText resolves', function ( assert ) {
	const done = assert.async();

	this.writeText.resolves();
	const qunitFixture = document.getElementById( 'qunit-fixture' );
	const div = document.createElement( 'div' );
	div.classList.add( 'mw-checkuser-helper-table' );
	qunitFixture.appendChild( div );

	addCopyElement( 'test text' );

	const copyButton = qunitFixture.querySelector( '.mw-checkuser-helper-copy-button' );
	assert.notStrictEqual(
		copyButton,
		null,
		'Copy button is added when writeText is defined'
	);

	assert.true(
		copyButton.textContent.includes( '(ooui-copytextlayout-copy)' ),
		'Copy button has the correct label'
	);

	const copyButtonLink = copyButton.querySelector( 'a' );
	copyButtonLink.click();

	setTimeout( () => {
		assert.true(
			this.notifyStub.calledOnce,
			'mw.notify is called when writeText resolves'
		);
		assert.true(
			this.notifyStub.calledWith( mw.msg( 'checkuser-helper-copy-success' ), { type: 'success' } ),
			'mw.notify is called with the correct message'
		);
		done();
	} );
} );

QUnit.test( 'writeText rejects', function ( assert ) {
	const done = assert.async();

	this.writeText.rejects( new Error( 'Test error' ) );

	const qunitFixture = document.getElementById( 'qunit-fixture' );
	const div = document.createElement( 'div' );
	div.classList.add( 'mw-checkuser-helper-table' );
	qunitFixture.appendChild( div );

	addCopyElement( 'test text' );

	const copyButton = qunitFixture.querySelector( '.mw-checkuser-helper-copy-button' );
	assert.notStrictEqual(
		copyButton,
		null,
		'Copy button is added when writeText is defined'
	);

	const copyButtonLink = copyButton.querySelector( 'a' );
	copyButtonLink.click();

	setTimeout( () => {
		assert.true(
			this.notifyStub.calledOnce,
			'mw.notify is called when writeText rejects'
		);
		assert.true(
			this.notifyStub.calledWith( mw.msg( 'checkuser-helper-copy-failed' ), { type: 'error' } ),
			'mw.notify is called with the correct message'
		);
		done();
	} );
} );
