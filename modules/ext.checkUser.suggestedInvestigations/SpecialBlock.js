/**
 * Run code for use when the Special:Block page loads.
 *
 * This adds the specialBlockRelatedCasesMessage component to the page if
 * SuggestedInvestigations is loaded. The component is responsible for flagging
 * and indicating if the block target was found on any SI cases.
 */
function onLoad() {
	// This code is also loaded on the "block succeeded" page where there is no form,
	// so check for block target widget; if it exists, the form is present
	if (
		$( '#mw-bi-target' ).length &&
		mw.config.get( 'wgUseCodexSpecialBlock' ) &&
		mw.config.get( 'wgCheckUserSuggestedInvestigationsEnabled' ) &&
		mw.config.get( 'wgCheckUserCanViewSuggestedInvestigations' )
	) {
		mw.hook( 'codex.userlookup' ).add( ( components ) => {
			// Codex and Vue are fully loaded at this point.
			const RelatedCasesMessage = require( './components/RelatedCasesMessage.vue' );
			components.value.push( RelatedCasesMessage );
		} );

		$( 'body' ).on( 'click', '.mw-checkuser-si-relatedaccounts-message a', () => {
			mw.track( 'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total', 1, {
				action: 'link_click'
			} );
		} );
	}
}

module.exports = {
	onLoad: onLoad
};
