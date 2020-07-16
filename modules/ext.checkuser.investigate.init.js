( function () {
	var addBlockForm = require( './ext.checkuser.investigate.blockform.js' ),
		setupTables = require( './ext.checkuser.investigate.tables.js' ),
		addCopyFeature = require( './ext.checkuser.investigate.copy.js' );

	if ( $( '.ext-checkuser-investigate-subtitle-block-button' ).length > 0 ) {
		addBlockForm();
	}

	setupTables();

	if (
		$( '.ext-checkuser-investigate-table-compare' ).length > 0 &&
		mw.config.get( 'wgVisualEditorConfig' ) &&
		mw.config.get( 'wgVisualEditorConfig' ).fullRestbaseUrl
	) {
		addCopyFeature();
	}

}() );
