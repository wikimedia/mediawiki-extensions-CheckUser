/* eslint-disable no-jquery/no-global-selector */

( function () {
	var setupTables = require( './ext.checkuser.investigate.tables.js' ),
		addCopyFeature = require( './ext.checkuser.investigate.copy.js' );

	setupTables();

	if (
		$( '.ext-checkuser-investigate-table-compare' ).length > 0 &&
		mw.config.get( 'wgVisualEditorConfig' ) &&
		mw.config.get( 'wgVisualEditorConfig' ).fullRestbaseUrl
	) {
		addCopyFeature();
	}

}() );
