'use strict';

const specialBlock = require( 'ext.checkUser.suggestedInvestigations/SpecialBlock.js' );

QUnit.module( 'ext.checkUser.suggestedInvestigations.SpecialBlock', QUnit.newMwEnvironment( {
	afterEach: function () {
		// Remove the 'click' listener attached onLoad if it exists to avoid double-counting events.
		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'body' ).off( 'click' );
	}
} ) );

/**
 * Adds the block target input to the QUnit fixture.
 *
 * @param {string} targetValue The initial value of the block target input
 */
function addBlockInputToQUnitTestFixture( targetValue ) {
	const $blockTargetInput = new mw.widgets.UserInputWidget( {
		label: 'test', classes: [], value: targetValue, id: 'mw-bi-target'
	} ).$element;
	// We have to hardcode the infusion data, as there isn't an easier way to get it
	// in a QUnit context.
	$blockTargetInput.attr(
		'data-ooui',
		'{"_":"mw.widgets.UserInputWidget","$overlay":true,"placeholder":"UserName, ' +
		'1.1.1.42, or 1.1.1.42/16","autofocus":true,"name":"wpTarget","inputId":"ooui-php-1"' +
		',"indicator":"required","required":true}'
	);
	const $container = $( '<div>' ).attr( 'id', 'mw-htmlform-target' );
	$container.append( $blockTargetInput );
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	$qunitFixture.append( $container );
}

QUnit.test( 'Test onLoad when conditions aren\'t met', ( assert ) => {
	mw.config.set( 'wgUseCodexSpecialBlock', true );
	mw.config.set( 'wgCheckUserSuggestedInvestigationsEnabled', false );
	mw.config.set( 'wgCheckUserCanViewSuggestedInvestigations', false );
	// Add a mock block target input to simulate that the page is the block page.
	addBlockInputToQUnitTestFixture( 'User' );
	// Call the method under test
	specialBlock.onLoad();
	const customComponents = { value: [] };
	mw.hook( 'codex.userlookup' ).fire( customComponents );
	const wasLoaded = customComponents.value.some( ( component ) => component.name === 'RelatedCasesMessage' );
	assert.false(
		wasLoaded,
		'RelatedCasesMessage component was not added'
	);
} );

QUnit.test( 'Test onLoad when all conditions are met', ( assert ) => {
	mw.config.set( 'wgUseCodexSpecialBlock', true );
	mw.config.set( 'wgCheckUserSuggestedInvestigationsEnabled', true );
	mw.config.set( 'wgCheckUserCanViewSuggestedInvestigations', true );
	// Add a mock block target input to simulate that the page is the block page.
	addBlockInputToQUnitTestFixture( 'User' );
	// Call the method under test
	specialBlock.onLoad();
	const customComponents = { value: [] };
	mw.hook( 'codex.userlookup' ).fire( customComponents );
	const wasLoaded = customComponents.value.some( ( component ) => component.name === 'RelatedCasesMessage' );
	assert.true(
		wasLoaded,
		'RelatedCasesMessage component was added'
	);
} );
